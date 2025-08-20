<?php declare(strict_types=1);

namespace BulkImportFiles\Mvc\Controller\Plugin;

use BulkImportFiles\Mvc\Controller\Plugin\ExtractDataFromPdf as ExtractDataFromPdfFiles;
use BulkImport\Mvc\Controller\Plugin\ExtractDataFromPdf as ExtractDataFromPdfCore;
use ArrayObject;
use Common\Stdlib\EasyMeta;
use DOMDocument;
use DOMXPath;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Extract data from a string with a mapping.
 */
class MapData extends AbstractPlugin
{
    /** @var EasyMeta */
    protected $easyMeta;

    // Dopo:
    /** @var ExtractDataFromPdfFiles|ExtractDataFromPdfCore */
    protected $extractDataFromPdf;

    /** @var ExtractStringFromFile */
    protected $extractStringFromFile;

    /**
     * Temporary flat array.
     * @var array
     */
    private $flatArray;

    /**
     * Keys to ignore for getid3.
     * @var array
     */
    protected $getId3IgnoredKeys = [
        'GETID3_VERSION',
        'filesize',
        'filename',
        'filepath',
        'filenamepath',
        'avdataoffset',
        'avdataend',
        'fileformat',
        'encoding',
        'mime_type',
        'md5_data',
    ];

       public function __construct(
           EasyMeta $easyMeta,
           ExtractDataFromPdfFiles|ExtractDataFromPdfCore $extractDataFromPdf,
           ExtractStringFromFile $extractStringFromFile
       ) {
           $this->easyMeta = $easyMeta;
           $this->extractDataFromPdf = $extractDataFromPdf;
           $this->extractStringFromFile = $extractStringFromFile;
       }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Extract data from an array with a mapping.
     *
     * @param array $input Array of metadata.
     * @param array $mapping The mapping adapted to the input.
     * @param bool $simpleExtract Only extract metadata, don't map them.
     * @return array A resource array by property, suitable for api creation or update.
     */
    public function array(array $input, array $mapping, $simpleExtract = false): array
    {
        $mapping = $this->normalizeMapping($mapping);
        if (empty($input) || empty($mapping)) {
            return [];
        }

        $result = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);

        foreach ($mapping as $map) {
            $target = reset($map);
            $query  = key($map);

            $queryMapping = explode('.', $query);
            $input_fields = $input;
            foreach ($queryMapping as $qm) {
                if (isset($input_fields[$qm])) {
                    $input_fields = $input_fields[$qm];
                }
            }

            if (!is_array($input_fields)) {
                $simpleExtract
                    ? $this->simpleExtract($result, $input_fields, $target, $query)
                    : $this->appendValueToTarget($result, $input_fields, $target);
            }
        }

        return $result->exchangeArray([]);
    }

    /**
     * Extract data from a xml file with a mapping.
     *
     * @param string $filepath
     * @param array  $mapping
     * @param bool   $simpleExtract
     * @return array
     */
    public function xml($filepath, array $mapping, $simpleExtract = false)
    {
        $mapping = $this->normalizeMapping($mapping);
        if (empty($mapping)) {
            return [];
        }

        $xml = $this->extractStringFromFile->__invoke($filepath, '<x:xmpmeta', '</x:xmpmeta>');
        if (empty($xml)) {
            return [];
        }

        // Ensure XML header.
        $xml = trim($xml);
        if (strpos($xml, '<?xml ') !== 0) {
            $xml = '<?xml version="1.1" encoding="utf-8"?>' . $xml;
        }

        $result = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);

        // Register all namespaces to allow prefixes.
        $xpathN = new DOMXPath($doc);
        foreach ($xpathN->query('//namespace::*') as $node) {
            $xpath->registerNamespace($node->prefix, $node->nodeValue);
        }

        foreach ($mapping as $map) {
            $target   = reset($map);
            $query    = key($map);
            $nodeList = $xpath->query($query);
            if (!$nodeList || !$nodeList->length) {
                continue;
            }

            foreach ($nodeList as $node) {
                $simpleExtract
                    ? $this->simpleExtract($result, $node->nodeValue, $target, $query)
                    : $this->appendValueToTarget($result, $node->nodeValue, $target);
            }
        }

        return $result->exchangeArray([]);
    }

    public function pdf($filepath, array $mapping, $simpleExtract = false)
    {
        $mapping = $this->normalizeMapping($mapping);
        if (empty($mapping)) {
            return [];
        }

        $input = $this->extractDataFromPdf->__invoke($filepath);
        return $this->array($input, $mapping, $simpleExtract);
    }

    protected function simpleExtract(ArrayObject $result, $value, $target, $source): void
    {
        $result[] = [
            'field'  => $source,
            'target' => $target,
            'value'  => $value,
        ];
    }

    private function resolvePropertyTermCompat($maybeTerm): ?string
    {
        if (is_string($maybeTerm) && strpos($maybeTerm, ':') !== false) {
            return $maybeTerm;
        }
        $prop = $this->getController()->api()->searchOne('properties', ['term' => $maybeTerm])->getContent();
        if ($prop) {
            return $prop->term();
        }
        $prop = $this->getController()->api()->searchOne('properties', ['label' => $maybeTerm])->getContent();
        return $prop ? $prop->term() : null;
    }

    private function resolvePropertyIdCompat($termOrLabel): ?int
    {
        if (is_string($termOrLabel) && strpos($termOrLabel, ':') !== false) {
            $prop = $this->getController()->api()->searchOne('properties', ['term' => $termOrLabel])->getContent();
            return $prop ? (int) $prop->id() : null;
        }
        $prop = $this->getController()->api()->searchOne('properties', ['term' => $termOrLabel])->getContent();
        if ($prop) {
            return (int) $prop->id();
        }
        $prop = $this->getController()->api()->searchOne('properties', ['label' => $termOrLabel])->getContent();
        return $prop ? (int) $prop->id() : null;
    }

    protected function appendValueToTarget(ArrayObject $result, $value, $target): void
    {
        static $targets = [];

        // Pattern: term/keyword, optional @language, optional ^^datatype.
        $pattern = '~'
            . '^([a-zA-Z][^@^]*)'
            . '\s*(?:@\s*([a-zA-Z]+-[a-zA-Z]+|[a-zA-Z]+|))?'
            . '\s*(?:\^\^\s*([a-zA-Z][a-zA-Z0-9]*:[a-zA-Z][\w-]*|[a-zA-Z][\w-]*|))?$'
            . '~';
        $matches = [];

        if (isset($targets[$target])) {
            if (empty($targets[$target])) {
                return;
            }
        } else {
            $meta = preg_match($pattern, $target, $matches);
            if (!$meta) {
                $targets[$target] = false;
                return;
            }
            $targets[$target] = [];
            $targets[$target]['field']      = trim($matches[1]);
            $targets[$target]['@language']  = empty($matches[2]) ? null : trim($matches[2]);
            $targets[$target]['type']       = empty($matches[3]) ? null : trim($matches[3]);
            $targets[$target]['is']         = $this->isField($targets[$target]['field']);
            if ($targets[$target]['is'] === 'property') {
                // id via EasyMeta, fallback via API se serve
                $targets[$target]['property_id'] = $this->easyMeta->propertyId($targets[$target]['field'])
                    ?: $this->resolvePropertyIdCompat($targets[$target]['field']);
            }
        }

        switch ($targets[$target]['is']) {
            case 'property':
                if (empty($targets[$target]['property_id'])) {
                    return;
                }
                $v = [];
                $v['property_id'] = $targets[$target]['property_id'];
                $v['type']        = $targets[$target]['type'] ?: 'literal';
                switch ($v['type']) {
                    case 'literal':
                    default:
                        $v['@value']    = $value;
                        $v['@language'] = $targets[$target]['@language'];
                        break;
                    case 'uri':
                    case (strpos((string) $targets[$target]['type'], 'valuesuggest:') === 0):
                        $v['o:label']   = null;
                        $v['@language'] = $targets[$target]['@language'];
                        $v['@id']       = $value;
                        break;
                    case 'resource':
                    case 'resource:item':
                    case 'resource:media':
                    case 'resource:itemset':
                        $id = $this->findResourceFromIdentifier($value, null, $targets[$target]['type']);
                        if ($id) {
                            $v['value_resource_id'] = $id;
                            $v['@language'] = null;
                        } else {
                            $v['has_error'] = true;
                            if (property_exists($this, 'logger') && property_exists($this, 'indexResource')) {
                                $this->logger->err(
                                    'Index #{index}: Resource id for value "{value}" cannot be found: the entry is skipped.',
                                    ['index' => $this->indexResource, 'value' => $value]
                                );
                            }
                        }
                        break;
                }
                if (empty($v['has_error'])) {
                    $result[$targets[$target]['field']][] = $v;
                }
                break;

            case $targets[$target]['field'] === 'o:item':
            case 'id':
                $result[$targets[$target]['field']] = ['o:id' => $value];
                break;

            case 'resource':
                $result[$targets[$target]['field']][] = ['o:id' => $value];
                break;

            case 'boolean':
                $result[$targets[$target]['field']] = in_array($value, ['false', false, 0, '0', 'off', 'close'], true)
                    ? false
                    : (bool) $value;
                break;

            case 'single':
                $v = [];
                $v['value'] = $value;
                $result[$targets[$target]['field']] = $v;
                break;

            case 'custom':
            default:
                $v = [];
                $v['value'] = $value;
                if (isset($targets[$target]['@language'])) {
                    $v['@language'] = $targets[$target]['@language'];
                }
                $v['type'] = empty($targets[$target]['type']) ? 'literal' : $targets[$target]['type'];
                $result[$targets[$target]['field']][] = $v;
                break;
        }
    }

    /**
     * Determine the type of field.
     *
     * @param string $field
     * @return string
     */
    protected function isField($field)
    {
        $resources = ['o:item', 'o:item_set', 'o:media'];
        if (in_array($field, $resources, true)) {
            return 'resource';
        }
        $ids = ['o:resource_template', 'o:resource_class', 'o:owner'];
        if (in_array($field, $ids, true)) {
            return 'id';
        }
        $booleans = ['o:is_open', 'o:is_public'];
        if (in_array($field, $booleans, true)) {
            return 'boolean';
        }
        $singleData = ['o:email'];
        if (in_array($field, $singleData, true)) {
            return 'single';
        }
        return $this->easyMeta->propertyId($field) ? 'property' : 'custom';
    }

    /**
     * Normalize a mapping.
     *
     * @param array $mapping
     * @return array
     */
    protected function normalizeMapping(array $mapping)
    {
        if (empty($mapping)) {
            return $mapping;
        }

        $keyValue = reset($mapping);
        $isMultipleMapping = is_numeric(key($mapping));
        if (!$isMultipleMapping) {
            $mapping  = $this->multipleFromSingle($mapping);
            $keyValue = reset($mapping);
        }

        $value = reset($keyValue);
        if (is_array($value)) {
            $mapping  = $this->multipleFromMultiple($mapping);
            $keyValue = reset($mapping);
        }

        $key = key($keyValue);
        $isTargetKey = strpos((string) $key, ':') && strpos((string) $key, '::') === false;
        if ($isTargetKey) {
            $mapping = $this->flipTargetToValues($mapping);
        }

        return $mapping;
    }

    protected function multipleFromSingle(array $mapping)
    {
        $result = [];
        foreach ($mapping as $key => $value) {
            $result[] = [$key => $value];
        }
        return $result;
    }

    protected function multipleFromMultiple(array $mapping)
    {
        $result = [];
        foreach ($mapping as $value) {
            foreach ($value as $key => $val) {
                foreach ($val as $v) {
                    $result[] = [$key => $v];
                }
            }
        }
        return $result;
    }

    protected function flipTargetToValues(array $mapping)
    {
        $result = [];
        foreach ($mapping as $value) {
            $result[] = [reset($value) => key($value)];
        }
        return $result;
    }

    /**
     * Create a flat array from a recursive array.
     *
     * @param array $data
     * @param array $ignoredKeys
     * @return array
     */
    protected function flatArray(array $data, array $ignoredKeys = [])
    {
        $this->flatArray = [];
        $this->_flatArray($data, $ignoredKeys);
        $result = $this->flatArray;
        $this->flatArray = [];
        return $result;
    }

    /**
     * Recursive helper to flat an array with separator ".".
     *
     * @param array  $data
     * @param array  $ignoredKeys
     * @param string $keys
     */
    private function _flatArray(array $data, array $ignoredKeys = [], $keys = null): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->_flatArray($value, $ignoredKeys, $keys . '.' . $key);
            } elseif (!in_array($key, $ignoredKeys, true)) {
                $this->flatArray[] = [
                    'key'   => trim((string) ($keys . '.' . $key), '.'),
                    'value' => $value,
                ];
            }
        }
    }
}
