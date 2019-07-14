<?php

namespace BulkImportFiles\Controller;

use BulkImportFiles\Form\ImportForm;
use BulkImportFiles\Form\SettingsForm;
use GetId3\GetId3Core as GetId3;
use Omeka\Entity\Media;
use Omeka\File\TempFileFactory;
use Omeka\Form\ResourceForm;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * Mapping by item id.
     *
     * @var array
     */
    protected $filesMaps;

    /**
     * @var string
     */
    protected $resourceTemplateLabel = 'Bulk import files';

    /**
     * Mapping by media type like 'dcterms:created' => ['jpg/exif/IFD0/DateTime'].
     *
     * @var array
     */
    protected $filesMapsArray;

    private $flatArray;

    protected $parsed_data;

    protected $filesData;

    protected $basePath;

    protected $directory;

    protected $ignoredKeys = [
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

    protected $tempFileFactory;

    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->services = $serviceLocator;
    }

    public function indexAction()
    {
        return $this->forward()->dispatch('BulkImportFiles\Controller\Index', [
            '__NAMESPACE__' => 'BulkImportFiles\Controller',
            '__ADMIN__' => true,
            'controller' => 'BulkImportFiles\Controller\Index',
            'action' => 'make-import',
        ]);
    }

    public function makeImportAction()
    {
    }

    public function getFilesAction()
    {
        $this->prepareFilesMaps();

        $request = $this->getRequest();

        $files = $request->getFiles()->toArray();

        $files_data_for_view = [];

        foreach ($files['files'] as $file) {
            $media_type = $file['type'];
            $data = [];
            $this->parsed_data = [];
            $errors = '';

            if (isset($this->filesMapsArray[$media_type])) {
                $filesMapsArray = $this->filesMapsArray[$media_type];
                $file['item_id'] = $filesMapsArray['item_id'];
                unset($filesMapsArray['media_type']);
                unset($filesMapsArray['item_id']);

                switch ($media_type) {
                    case 'application/pdf':
                        $data = $this->extractDataFromPdf($file['tmp_name']);
                        $this->parsed_data = $this->flatArray($data);
                        $data = $this->mapData()->array($data, $filesMapsArray, true);
                        break;

                    default:
                        $getId3 = new GetId3();
                        // TODO Fix GetId3 that uses create_function(), deprecated.
                        $file_source = @$getId3
                            ->setOptionMD5Data(true)
                            ->setOptionMD5DataSource(true)
                            ->setEncoding('UTF-8')
                            ->analyze($file['tmp_name']);
                        $this->parsed_data = $this->flatArray($file_source, $this->ignoredKeys);
                        $data = $this->mapData()->array($file_source, $filesMapsArray, true);
                        break;
                }
            }

            /*
             * selected files for uploads
             * $file array
             *      'name' => string
             *      'type' => string
             *      'tmp_name' => string
             *      'error' => int
             *      'size' => int
             *
             * $source_data = $this->parsed_data
             * all available meta data for current file with value
             * example:
             *      'key' => string '/video/dataformat'
             *      'value' => string 'jpg'
             *
             * config saved by user for current file type
             * $recognized_data
             * example:
             * 'dcterms:created' => array
             *        'field' => string 'jpg/exif/IFD0/DateTime'
             *        'value' => string '2014:03:12 15:03:25'
             */
            $files_data_for_view[] = [
                'file' => $file,
                'source_data' => $this->parsed_data,
                'recognized_data' => $data,
                'errors' => $errors,
            ];
        }

        $this->layout()
            ->setVariable('files_data_for_view', $files_data_for_view)
            ->setVariable('listTerms', $this->bulk()->getPropertyTerms())
            ->setVariable('filesMaps', $this->filesMaps);
    }

    public function checkFilesAction()
    {
    }

    public function checkFolderAction()
    {
        $this->prepareFilesMaps();

        $files_data = [];
        $total_files = 0;
        $total_files_can_recognized = 0;
        $error = '';

        $params = $this->params()->fromPost();

        if (!empty($params['folder'])) {
            if (file_exists($params['folder']) && is_dir($params['folder'])) {
                $files = $this->listFilesInDir($params['folder']);
                $file_path = $params['folder'] . '/';
                foreach ($files as $file) {
                    $getId3 = new GetId3();
                    // TODO Fix GetId3 that uses create_function(), deprecated.
                    $file_source = @$getId3
                        ->setOptionMD5Data(true)
                        ->setOptionMD5DataSource(true)
                        ->setEncoding('UTF-8')
                        ->analyze($file_path . $file);

                    ++$total_files;

                    $media_type = 'undefined';
                    $file_isset_maps = 'no';

                    if (isset($file_source['mime_type'])) {
                        $media_type = $file_source['mime_type'];
                        if (isset($this->filesMapsArray[$media_type])) {
                            $file_isset_maps = 'yes';
                            ++$total_files_can_recognized;
                        }
                    }

                    $files_data[] = [
                        'filename' => $file_source['filename'],
                        'file_size' => $file_source['filesize'],
                        'file_type' => $media_type,
                        'file_isset_maps' => $file_isset_maps,
                    ];
                }

                if (count($files_data) == 0) {
                    $error = $this->translate('Folder is empty'); // @translate;
                }
            } else {
                $error = $this->translate('Folder not exist'); // @translate;
            }
        } else {
            $error = $this->translate('Can’t check empty folder'); // @translate;
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/check-folder')
            ->setVariable('files_data', $files_data)
            ->setVariable('total_files', $total_files)
            ->setVariable('total_files_can_recognized', $total_files_can_recognized)
            ->setVariable('error', $error);
    }

    public function processImportAction()
    {
        $this->prepareFilesMaps();

        $config = $this->services->get('Config');
        $baseUri = $config['file_store']['local']['base_uri'];
        if (!$baseUri) {
            $helpers = $this->services->get('ViewHelperManager');
            $serverUrlHelper = $helpers->get('ServerUrl');
            $basePathHelper = $helpers->get('BasePath');
            $baseUri = $serverUrlHelper($basePathHelper('files'));
        }

        $params = $this->params()->fromPost();
        $data_for_recognize_row_id = $params['data_for_recognize_row_id'];
        $notice = null;
        $warning = null;
        $error = null;

        if (isset($params['data_for_recognize_single'])) {
            $full_file_path = $params['directory'] . '/' . $params['data_for_recognize_single'];
            $delete_file_action = $params['delete_file'] === 'true';

            // TODO Use api standard method, not direct creation.
            // Create new media via temporary factory.

            $fileinfo = new \SplFileInfo($full_file_path);
            $tempPath = $fileinfo->getRealPath();

            $this->tempFileFactory = new TempFileFactory($this->services);

            $tempFile = $this->tempFileFactory->build();
            $tempFile->setTempPath($tempPath);
            $tempFile->setSourceName($full_file_path);

            $media = new Media();
            $media->setStorageId($tempFile->getStorageId());
            $media->setExtension($tempFile->getExtension());
            $media->setMediaType($tempFile->getMediaType());
            $media->setSha256($tempFile->getSha256());
            $media->setSize($tempFile->getSize());

            $hasThumbnails = $tempFile->storeThumbnails();
            $media->setHasThumbnails($hasThumbnails);
            $media->setSource($full_file_path);

            $tempFile->storeOriginal();
            $media->setHasOriginal(true);

            // Create new Item.

            // Get metadata from $full_file_path
            $url = $baseUri . '/original/' . $tempFile->getStorageId() . '.' . $tempFile->getExtension();

            $getId3 = new GetId3();
            // TODO Fix GetId3 that uses create_function(), deprecated.
            $file_source = @$getId3
                ->setOptionMD5Data(true)
                ->setOptionMD5DataSource(true)
                ->setEncoding('UTF-8')
                ->analyze($full_file_path);

            $media_type = isset($file_source['mime_type']) ? $file_source['mime_type'] : 'undefined';
            if (!isset($this->filesMapsArray[$media_type])) {
                $this->layout()
                    ->setVariable('data_for_recognize_row_id', $data_for_recognize_row_id)
                    ->setVariable('error', sprintf($this->translate('The media type "%s" is not managed or has no mapping.'), $media_type));
                return;
            }

            $filesMapsArray = $this->filesMapsArray[$media_type];
            unset($filesMapsArray['media_type']);
            unset($filesMapsArray['item_id']);

            // Use xml or array according to item mapping.
            $query = reset($filesMapsArray);
            $query = $query ? reset($query) : null;
            $isXpath = $query && strpos($query, '/') !== false;
            if ($isXpath) {
                $data = $this->mapData()->xml($full_file_path, $filesMapsArray);
            } else {
                switch ($media_type) {
                    case 'application/pdf':
                        $data = $this->mapData()->pdf($full_file_path, $filesMapsArray);
                        break;
                    default:
                        $data = $this->mapData()->array($file_source, $filesMapsArray);
                        break;
                }
            }

            if (count($data) <= 0) {
                if ($query) {
                    $warning = $this->translate('No metadata to import. You may see log for more info.'); // @translate
                } else {
                    $notice = $this->translate('No metadata: mapping is empty.'); // @translate
                }
            }

            // Append default metadata if needed.
            $data += [
                'o:resource_template' => ['o:id' => ''],
                'o:resource_class' => ['o:id' => ''],
                'o:thumbnail' => ['o:id' => ''],
                'o:media' => [[
                    'o:is_public' => '1',
                    'ingest_url' => $url,
                    'o:ingester' => 'url',
                ]],
                'o:is_public' => '1',
            ];

            if (!isset($data['dcterms:title'][0])) {
                $item_title = $params['data_for_recognize_single'];
                $data['dcterms:title'] = [[
                    'property_id' => 1,
                    'type' => 'literal',
                    '@language' => '',
                    '@value' => $item_title,
                    'is_public' => '1',
                ]];
            }

            /** @var \Omeka\Form\ResourceForm $form */
            $form = $this->getForm(ResourceForm::class);
            $form
                ->setAttribute('action', $this->url()->fromRoute(null, [], true))
                ->setAttribute('enctype', 'multipart/form-data')
                ->setAttribute('id', 'add-item');
            $form->setData($data);
            $hasNewItem = $this->api($form)->create('items', $data);
            if ($hasNewItem && $delete_file_action) {
                $tempFile->delete();
            }
        }

        $this->layout()
            ->setVariable('data_for_recognize_row_id', $data_for_recognize_row_id)
            ->setVariable('notice', empty($notice) ? null : $notice)
            ->setVariable('warning', empty($warning) ? null : $warning)
            ->setVariable('error', empty($error) ? null : $error);
    }

    public function mapShowAction()
    {
        $this->prepareFilesMaps();

        $form = $this->getForm(SettingsForm::class);

        $view = new ViewModel;
        $view->setVariable('filesMaps', $this->filesMaps);
        $view->setVariable('form', $form);
        return $view;
    }

    public function mapEditAction()
    {
        $form = $this->getForm(ImportForm::class);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function addFileTypeAction()
    {
        $params = $this->params()->fromPost();

        if (!empty($params['media_type'])) {

            $media_type = $params['media_type'];

            $file_name = 'map_' . explode('/', $media_type)[0] . '_' . explode('/', $media_type)[1] . '.ini';
            $filepath = dirname(dirname(__FILE__)) . '/data/mapping/' . $file_name;

            if (!strlen($filepath)) {
                throw new \RuntimeException('Filepath string should be longer that zero character.');
            }

            if (($handle = fopen($filepath, 'w')) === false) {
                throw new \RuntimeException(sprintf('Could not save file "%s" for reading/'), $filepath);
            }

            $buffer = '';
            $hasString = false;

            $file_content = "";

            $file_content = "<map>\n";
                $file_content .= "$media_type = dcterms:title\n";
            $file_content .= "</map>";

            fwrite($handle, $file_content);
            fclose($handle);
            $request = $this->translate('File successfully added!');
        }else {
            $request = $this->translate('Request empty.'); // @translate
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/add-file')
            ->setVariable('request', $request);
    }

    public function deleteFileTypeAction()
    {
        $params = $this->params()->fromPost();

        $helpers = $this->services->get('ViewHelperManager');
        $url = $helpers->get('url');
        $reloadURL = $url(
            'admin/bulk-import-files',
            ['action' => 'index']
        );

        if (!empty($params['media_type'])) {

            $file_name = $params['media_type'];

            $filepath = dirname(__DIR__)."/../data/mapping/".$file_name;

            if (!strlen($filepath)) {
                throw new \RuntimeException('Filepath string should be longer that zero character.');
            }

            if (($handle = fopen($filepath, 'w')) === false) {
                throw new \RuntimeException(sprintf('Could not save file "%s" for reading/'), $filepath);
            }

            fclose($handle);
            unlink($filepath) or die("Couldn't delete file");

            $request['state'] = true;
            $request['reloadURL'] = $reloadURL;
            $request['msg'] = $this->translate('File successfully deleted!');
        }else {
            $request['state'] = false;
            $request['reloadURL'] = $reloadURL;
            $request['msg'] = $this->translate('Request empty.');
        }

        $request = json_encode($request);
        $this->layout()
            ->setTemplate('bulk-import-files/index/add-file')
            ->setVariable('request', $request);
    }

    public function saveOptionsAction()
    {
        $params = $this->params()->fromPost();

        if (!empty($params['omeka_file_id'])) {
            $omeka_file_id = $params['omeka_file_id'];
            $media_type = $params['media_type'];
            $listterms_select = $params['listterms_select'];

            /** @var \Omeka\Api\Representation\ItemRepresentation $item */

            $file_content = "";

            $file_content = "<map>\n";
            $file_content .= "$media_type = dcterms:title\n";
            foreach ($listterms_select as $term_item_name) {
                foreach ($term_item_name['property'] as $term) {
                    $file_content .= $term_item_name['field']." = ".$term."\n";
                }
            }
            $file_content .= "</map>";

            $folder_path = dirname(__DIR__)."/../data/mapping";
            $response = false;
            if (!empty($folder_path)) {
                if (file_exists($folder_path) && is_dir($folder_path)) {
                    $files = $this->listFilesInDir($folder_path);
                    $file_path = $folder_path . '/';
                    foreach ($files as $file_index => $file) {
                        $getId3 = new GetId3();
                        // TODO Fix GetId3 that uses create_function(), deprecated.
                        $file_source = @$getId3
                            ->setOptionMD5Data(true)
                            ->setOptionMD5DataSource(true)
                            ->setEncoding('UTF-8')
                            ->analyze($file_path . $file);

                        if ($file != $omeka_file_id) {
                            continue;
                        }

                        $response = $this->extractStringToFile($file_path . $file, $file_content);
                    }
                } else {
                    $error = $this->translate('Folder not exist'); // @translate;
                }
            } else {
                $error = $this->translate('Can’t check empty folder'); // @translate;
            }

            if ($response) {
                $request = $this->translate('Item property successfully updated'); // @translate
            } else {
                $request = $this->translate('Can’t update item property'); // @translate
            }
        } else {
            $request = $this->translate('Request empty.'); // @translate
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/save-options')
            ->setVariable('request', $request);
    }

    public function saveOptionsAction1()
    {
        $params = $this->params()->fromPost();

        if (!empty($params['omeka_file_id'])) {
            $omeka_file_id = $params['omeka_file_id'];
            $media_type = $params['media_type'];
            // $file_field_property = $params['file_field_property'];
            $listterms_select = $params['listterms_select'];

            /** @var \Omeka\Api\Representation\ItemRepresentation $item */

            $item = $this->api()->read('items', ['id' => $omeka_file_id])->getContent();

            $resourceTemplate = $this->api()
                ->read('resource_templates', ['label' => $this->resourceTemplateLabel])
                ->getContent();

            $data = [
                'o:resource_template' => ['o:id' => $resourceTemplate->id()],
                'o:resource_class' => ['o:id' => ''],
                'dcterms:title' => [[
                    'property_id' => '1',
                    'type' => 'literal',
                    '@language' => '',
                    '@value' => $media_type,
                    'is_public' => '1',
                ]],
                'o:thumbnail' => ['o:id' => ''],
                'o:is_public' => '0',
            ];

            $bulk = $this->bulk();
            foreach ($listterms_select as $term_item_name) {
                if (isset($term_item_name['property'])) {
                    foreach ($term_item_name['property'] as $term) {
                        $data[$term][] = [
                            'property_id' => $bulk->getPropertyId($term),
                            'type' => 'literal',
                            '@language' => '',
                            '@value' => $term_item_name['field'],
                            'is_public' => '1',
                        ];
                    }
                }
            }

            $form = $this->getForm(ResourceForm::class)
                ->setAttribute('action', $this->url()->fromRoute(null, [], true))
                ->setAttribute('enctype', 'multipart/form-data')
                ->setAttribute('id', 'edit-item');
            $form->setData($data);
            $response = $this->api($form)->update('items', $omeka_file_id, $data);

            if ($response) {
                $request = $this->translate('Item property successfully updated'); // @translate
            } else {
                $request = $this->translate('Can’t update item property'); // @translate
            }
        } else {
            $request = $this->translate('Request empty.'); // @translate
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/save-options')
            ->setVariable('request', $request);
    }

    /**
     * Create a flat array from a recursive array.
     *
     * @example
     * ```
     * // The following recursive array:
     * 'video' => [
     *      'dataformat' => 'jpg',
     *      'bits_per_sample' => 24;
     * ]
     * // is converted into:
     * [
     *     'video.dataformat' => 'jpg',
     *     'video.bits_per_sample' => 24,
     * ]
     * ```
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
     * @param array $data
     * @param array $ignoredKeys
     * @param string $keys
     */
    private function _flatArray(array $data, array $ignoredKeys = [], $keys = null)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->_flatArray($value, $ignoredKeys, $keys . '.' . $key);
            } elseif (!in_array($key, $ignoredKeys)) {
                $this->flatArray[] = [
                    'key' => trim($keys . '.' . $key, '.'),
                    'value' => $value,
                ];
            }
        }
    }

    /**
     * List files in a directory, not recursively, and without subdirs, and sort
     * them alphabetically (case insenitive and natural order).
     *
     * @param string $dir
     * @return array
     */
    protected function listFilesInDir($dir)
    {
        if (empty($dir) || !file_exists($dir) || !is_dir($dir) || !is_readable($dir)) {
            return [];
        }
        $result = array_values(array_filter(scandir($dir), function ($file) use ($dir) {
            return is_file($dir . DIRECTORY_SEPARATOR . $file);
        }));
        natcasesort($result);
        return $result;
    }

    /**
     * Set filesMaps as object (stdClass) for all Items with template "Bulk import files"
     * (ex: public 'dcterms:created' => string '/x:xmpmeta/rdf:RDF/rdf:Description/@xmp:CreateDate')
     *
     * Set filesMapsArray as array with key "Item title" it's type of files
     * (ex: 'image/jpeg' => 'dcterms:created' => string '/x:xmpmeta/rdf:RDF/rdf:Description/@xmp:CreateDate')
     */

    protected function prepareFilesMaps()
    {
        $this->filesMaps = [];
        $folder_path = dirname(__DIR__)."/../data/mapping";

        if (!empty($folder_path)) {
            if (file_exists($folder_path) && is_dir($folder_path)) {
                $files = $this->listFilesInDir($folder_path);
                $file_path = $folder_path . '/';
                foreach ($files as $file_index => $file) {
                    $getId3 = new GetId3();
                    // TODO Fix GetId3 that uses create_function(), deprecated.
                    $file_source = @$getId3
                        ->setOptionMD5Data(true)
                        ->setOptionMD5DataSource(true)
                        ->setEncoding('UTF-8')
                        ->analyze($file_path . $file);

                    $data = $this->extractStringFromFile($file_path . $file, '<map', '</map>');

                    $data = trim($data);
                    if (empty($data)) {
                        continue;
                    }

                    $data_rows = array_map('trim',preg_split('/\n|\r\n?/', $data));
                    foreach ($data_rows as $key => $value) {
                        if( trim($value) == "" ){
                            array_splice($data_rows, $key, 1);
                        }
                    }
                    array_splice($data_rows, 0, 1);
                    array_splice($data_rows, count($data_rows)-1, 1);

                    $current_maps = [];
                    foreach ($data_rows as $key => $value) {

                        $value_key = array_map('trim',explode(' = ', $value));
                        $current_maps[$value_key[1]][] = $value_key[0];
                    }
                    // var_dump($current_maps);
                    if (isset($current_maps['dcterms:title'][0])) {
                        $mediaType = $current_maps['dcterms:title'][0];
                        if (count($current_maps['dcterms:title']) <= 1) {
                            unset($current_maps['dcterms:title']);
                        } else {
                            unset($current_maps['dcterms:title'][0]);
                        }
                        $current_maps['item_id'] = $file;
                        $this->filesMapsArray[$mediaType] = $current_maps;
                    } else {
                        $mediaType = null;
                    }

                    $current_maps['media_type'] = $mediaType;
                    $this->filesMaps[$file] = $current_maps;
                }
            } else {
                $error = $this->translate('Folder not exist'); // @translate;
            }
        } else {
            $error = $this->translate('Can’t check empty folder'); // @translate;
        }
    }

    /**
     * Set filesMaps as object (stdClass) for all Items with template "Bulk import files"
     * (ex: public 'dcterms:created' => string '/x:xmpmeta/rdf:RDF/rdf:Description/@xmp:CreateDate')
     *
     * Set filesMapsArray as array with key "Item title" it's type of files
     * (ex: 'image/jpeg' => 'dcterms:created' => string '/x:xmpmeta/rdf:RDF/rdf:Description/@xmp:CreateDate')
     */
    protected function prepareFilesMaps1()
    {
        $this->filesMaps = [];

        try {
            $resourceTemplate = $this->api()
                ->read('resource_templates', ['label' => $this->resourceTemplateLabel])
                ->getContent();
        } catch (\Exception $e) {
            $this->messenger()->addError('The required resource template "Bulk import files" has been removed or renamed.'); // @translate
            return;
        }

        $items = $this->api()
            ->search('items', ['resource_template_id' => $resourceTemplate->id()])
            ->getContent();

        $options = [];
        $options['viewName'] = 'common/item-resource-values';

        foreach ($items as $item) {
            $current_maps = json_decode($item->displayValues($options), true);
            if (isset($current_maps['dcterms:title'][0])) {
                $mediaType = $current_maps['dcterms:title'][0];
                if (count($current_maps['dcterms:title']) <= 1) {
                    unset($current_maps['dcterms:title']);
                } else {
                    unset($current_maps['dcterms:title'][0]);
                }
                $current_maps['item_id'] = $item->id();
                $this->filesMapsArray[$mediaType] = $current_maps;
            } else {
                $mediaType = null;
            }

            unset($current_maps['item_id']);
            $current_maps['media_type'] = $mediaType;
            $this->filesMaps[$item->id()] = $current_maps;
        }
    }
}
