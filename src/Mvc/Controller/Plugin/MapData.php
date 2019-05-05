<?php
namespace BulkImportFiles\Mvc\Controller\Plugin;

use ArrayObject;
use BulkImport\Mvc\Controller\Plugin\Bulk;
use DOMDocument;
use DOMXPath;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Extract data from a string with a mapping.
 */
class MapData extends AbstractPlugin
{
    /**
     * @var \BulkImport\Mvc\Controller\Plugin\Bulk
     */
    protected $bulk;

    /**
     * @param Bulk $bulk
     */
    public function __construct(Bulk $bulk)
    {
        $this->bulk = $bulk;
    }

    /**
     * Extract data from a string with a mapping.
     *
     * Mapping is either a single or a multiple list, either a target
     * key or value, and either a xpath or a array:
     * [dcterms:title => /xpath/to/data]
     * [dcterms:title => object.to.data]
     * [/xpath/to/data => dcterms:title]
     * [object.to.data => dcterms:title]
     * [[dcterms:title => /xpath/to/data]]
     * [[dcterms:title => object.to.data]]
     * [[/xpath/to/data => dcterms:title]]
     * [[object.to.data => dcterms:title]]
     *
     * And the same mappings with a value as an array, for example:.
     * [[object.to.data => [dcterms:title]]]
     * The format is normalized into [[path/object => dcterms:title]].
     *
     * @param string $input
     * @param array $mapping The mapping adapted to the input.
     * @return array A resource array by property, suitable for api creation
     * or update.
     */
    public function __invoke($input, array $mapping)
    {
        if (empty($input) || empty($mapping)) {
            return [];
        }

        // Normalize the mapping to multiple data with source to target.
        $keyValue = reset($mapping);
        $isMultipleMapping = is_numeric(key($mapping));
        if (!$isMultipleMapping) {
            $mapping = $this->multipleFromSingle($mapping);
            $keyValue = reset($mapping);
        }

        $value = reset($keyValue);
        if (is_array($value)) {
            $mapping = $this->multipleFromMultiple($mapping);
            $keyValue = reset($mapping);
        }

        $key = key($keyValue);
        $isTargetKey = strpos($key, ':') && strpos($key, '::') === false;
        if ($isTargetKey) {
            $mapping = $this->flipTargetToValues($mapping);
            $keyValue = reset($mapping);
            $key = key($keyValue);
        }

        $isArray = strpos($key, '/') === false;
        $result = $isArray
            ? $this->arrayExtract($input, $mapping)
            : $this->xpathExtract($input, $mapping);
        return $result->exchangeArray([]);
    }

    /**
     * Extract metadata via array keys.
     *
     * @todo Move code from index controller to here.
     *
     * @param array $data
     * @param array $mapping
     * @return array
     */
    protected function arrayExtract($data, array $mapping)
    {
        $result = [];

        return $result;
    }

    /**
     * Extract metadata via xpath.
     *
     * @param string $xml
     * @param array $mapping
     * @return array
     */
    protected function xpathExtract($xml, array $mapping)
    {
        $result = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);

        // Check if the xml is fully formed.
        $xml = trim($xml);
        if (strpos($xml, '<?xml ') !== 0) {
            $xml = '<?xml version="1.1" encoding="utf-8"?>' . $xml;
        }

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);

        // Register namespaces to allow prefixes.
        $xpathN = new DOMXPath($doc);
        foreach ($xpathN->query('//namespace::*') as $node) {
            $xpath->registerNamespace($node->prefix, $node->nodeValue);
        }

        foreach ($mapping as $map) {
            $target = reset($map);
            $query = key($map);
            $nodeList = $xpath->query($query);
            if (!$nodeList || !$nodeList->length) {
                continue;
            }

            // The answer has many nodes.
            foreach ($nodeList as $node) {
                $value = $node->nodeValue;
                $this->appendValueToTarget($result, $value, $target);
            }
        }

        return $result;
    }

    protected function appendValueToTarget(ArrayObject $result, $value, $target)
    {
        static $targets = [];

        // First prepare the target keys.
        // TODO This normalization of the mapping can be done one time outside.

        // @see BulkImport\View\Helper\AutomapFields
        // The pattern checks a term or keyword, then an optional @language, then
        // an optional ^^ data type.
        $pattern = '~'
            // Check a term/keyword.
            . '^([a-zA-Z][^@^]*)'
            // Check a language + country.
            . '\s*(?:@\s*([a-zA-Z]+-[a-zA-Z]+|[a-zA-Z]+|))?'
            // Check a data type.
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
            $targets[$target]['field'] = trim($matches[1]);
            $targets[$target]['@language'] = empty($matches[2]) ? null : trim($matches[2]);
            $targets[$target]['type'] = empty($matches[3]) ? null : trim($matches[3]);
            $targets[$target]['is'] = $this->isField($targets[$target]['field']);
            if ($targets[$target]['is'] === 'property') {
                $targets[$target]['property_id'] = $this->bulk->getPropertyId($targets[$target]['field']);
            }
        }

        // Second, fill the result with the value.
        switch ($targets[$target]['is']) {
            case 'property':
                $v = [];
                $v['property_id'] = $targets[$target]['property_id'];
                $v['type'] = $targets[$target]['type'] ?: 'literal';
                switch ($v['type']) {
                    case 'literal':
                    // case strpos($resourceValue['type'], 'customvocab:') === 0:
                    default:
                        $v['@value'] = $value;
                        $v['@language'] = $targets[$target]['@language'];
                        break;
                    case 'uri':
                    case strpos($targets[$target]['type'], 'valuesuggest:') === 0:
                        $v['o:label'] = null;
                        $v['@language'] = $targets[$target]['@language'];
                        $v['@id'] = $value;
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
                            $this->logger->err(
                                'Index #{index}: Resource id for value "{value}" cannot be found: the entry is skipped.', // @translate
                                ['index' => $this->indexResource, 'value' => $value]
                            );
                        }
                        break;
                }
                if (empty($v['has_error'])) {
                    $result[$targets[$target]['field']][] = $v;
                }
                break;
            // Item is used only for media, that has only one item.
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
                // TODO Check email and owner.
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
                $v['type'] = empty($targets[$target]['type'])
                    ? 'literal'
                    : $targets[$target]['type'];
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
        $resources = [
            'o:item',
            'o:item_set',
            'o:media',
        ];
        if (in_array($field, $resources)) {
            return 'resource';
        }
        $ids = [
            'o:resource_template',
            'o:resource_class',
            'o:owner',
        ];
        if (in_array($field, $ids)) {
            return 'id';
        }
        $booleans = [
            'o:is_open',
            'o:is_public',
        ];
        if (in_array($field, $booleans)) {
            return 'boolean';
        }
        $singleData = [
            'o:email',
        ];
        if (in_array($field, $singleData)) {
            return 'single';
        }
        return $this->bulk->isPropertyTerm($field)
            ? 'property'
            : 'custom';
    }

    /**
     * Convert a single mapping to a multiple mapping.
     *
     * @param array $mapping
     * @return array
     */
    protected function multipleFromSingle(array $mapping)
    {
        $result = [];
        foreach ($mapping as $key => $value) {
            $result[] = [$key => $value];
        }
        return $result;
    }

    /**
     * Convert a multiple level mapping to a multiple mapping.
     *
     * @param array $mapping
     * @return array
     */
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

    /**
     * Flip keys and values of a full mapping.
     *
     * @param array $mapping
     * @return array
     */
    protected function flipTargetToValues(array $mapping)
    {
        $result = [];
        foreach ($mapping as $value) {
            $result[] = [reset($value) => key($value)];
        }
        return $result;
    }
}
