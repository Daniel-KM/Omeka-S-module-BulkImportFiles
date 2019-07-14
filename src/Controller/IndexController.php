<?php

namespace BulkImportFiles\Controller;

use BulkImportFiles\Form\ImportForm;
use BulkImportFiles\Form\SettingsForm;
use GetId3\GetId3Core as GetId3;
use Omeka\Entity\Media;
use Omeka\File\TempFileFactory;
use Omeka\Form\ResourceForm;
use Omeka\Mvc\Exception\NotFoundException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\JsonModel;
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
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $this->prepareFilesMaps();

        $request = $this->getRequest();
        $files = $request->getFiles()->toArray();
        // Skip dot files.
        $files['files'] = array_filter($files['files'], function($v) {
            return strpos($v['name'], '.') !== 0;
        });

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

        // The simplest way to get the list of all properties by vocabulary.
        // TODO Use a true form element and use chosen dynamically.
        $factory = new \Zend\Form\Factory($this->services->get('FormElementManager'));
        $element = $factory->createElement([
            'type' => \Omeka\Form\Element\PropertySelect::class,
        ]);
        $listTerms = $element->getValueOptions();
        // Convert the list to a list for select whit option group.
        // TODO Keep the full select array, compatible with js chosen.
        $result = [];
        foreach ($listTerms as $vocabulary) {
            foreach ($vocabulary['options'] as $property) {
                $result[$vocabulary['label']][$property['attributes']['data-term']] = $property['label'];
            }
        }
        $listTerms = $result;

        $this->layout()
            ->setTemplate('bulk-import-files/index/get-files')
            ->setVariable('files_data_for_view', $files_data_for_view)
            ->setVariable('listTerms', $listTerms)
            ->setVariable('filesMaps', $this->filesMaps);
    }

    public function checkFilesAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $this->prepareFilesMaps();

        $request = $this->getRequest();
        $files = $request->getFiles()->toArray();
        // Skip dot files.
        $files['files'] = array_filter($files['files'], function($v) {
            return strpos($v['name'], '.') !== 0;
        });

        $files_data = [];
        $total_files = 0;
        $total_files_can_recognized = 0;
        $error = '';

        // Save the files temporary for the next request.
        $dest = sys_get_temp_dir() . '/bulkimportfiles_upload/';
        if (!file_exists($dest)) {
            mkdir($dest, 0775, true);
        }

        if (!empty($files['files'])) {
            foreach ($files['files'] as $file) {
                // Check name for security.
                if (basename($file['name']) !== $file['name']) {
                    $error = $this->translate('All files must have a regular name. Check ended.'); // @translate
                    break;
                }

                if ($file['error'] === UPLOAD_ERR_OK) {
                    $getId3 = new GetId3();
                    // TODO Fix GetId3 that uses create_function(), deprecated.
                    $file_source = @$getId3
                        ->setOptionMD5Data(true)
                        ->setOptionMD5DataSource(true)
                        ->setEncoding('UTF-8')
                        ->analyze($file['tmp_name']);

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
                        'source' => $file['name'],
                        'filename' => basename($file['tmp_name']),
                        'file_size' => $file_source['filesize'],
                        'file_type' => $media_type,
                        'file_isset_maps' => $file_isset_maps,
                        'has_error' => $file['error'],
                    ];

                    $full_file_path = $dest . basename($file['tmp_name']);
                    move_uploaded_file($file['tmp_name'], $full_file_path);
                } else {
                    if (isset($this->filesMapsArray[$file['type']])) {
                        $file_isset_maps = 'yes';
                        ++$total_files_can_recognized;
                    } else {
                        $file_isset_maps = 'no';
                    }

                    $files_data[] = [
                        'source' => $file['name'],
                        'filename' => basename($file['tmp_name']),
                        'file_size' => $file['size'],
                        'file_type' => $file['type'],
                        'file_isset_maps' => $file_isset_maps,
                        'has_error' => $file['error'],
                    ];
                }
            }

            if (!$error && count($files_data) == 0) {
                $error = $this->translate('Folder is empty'); // @translate
            }
        } else {
            $error = $this->translate('Can’t check empty folder'); // @translate
        }

        // This is not a full view, only a partial html.
        $this->layout()
            ->setTemplate('bulk-import-files/index/check-files')
            ->setVariable('files_data', $files_data)
            ->setVariable('total_files', $total_files)
            ->setVariable('total_files_can_recognized', $total_files_can_recognized)
            ->setVariable('error', $error)
            ->setVariable('is_server', false);
    }

    public function checkFolderAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $this->prepareFilesMaps();

        $files_data = [];
        $total_files = 0;
        $total_files_can_recognized = 0;
        $error = '';

        $params = $this->params()->fromPost();
        if (!empty($params['folder'])) {
            if (file_exists($params['folder']) && is_dir($params['folder'])) {
                $files = $this->listFilesInDir($params['folder']);
                // Skip dot files.
                $files = array_filter($files, function($v) {
                    return strpos($v, '.') !== 0;
                });
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
                        'source' => $file,
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
            ->setVariable('error', $error)
            ->setVariable('is_server', true);
    }

    public function processImportAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $this->prepareFilesMaps();

        // Because there is no ingester for server, the ingester "url" is used
        // with a local folder inside "files/bulkimportfiles_temp", that is
        // available via https.

        $config = $this->services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: OMEKA_PATH . '/files';
        $baseUri = $config['file_store']['local']['base_uri'];
        if (!$baseUri) {
            $helpers = $this->services->get('ViewHelperManager');
            $serverUrlHelper = $helpers->get('ServerUrl');
            $basePathHelper = $helpers->get('BasePath');
            $baseUri = $serverUrlHelper($basePathHelper('files'));
        }

        $params = $this->params()->fromPost();
        $isServer = $params['is_server'] === 'true';
        $params['delete_file'] = !$isServer || $params['delete_file'];

        $row_id = $params['row_id'];
        $notice = null;
        $warning = null;
        $error = null;

        if (isset($params['filename'])) {
            if ($isServer) {
                $full_file_path = $params['directory'] . '/' . $params['filename'];
            } else {
                $full_file_path = sys_get_temp_dir() . '/bulkimportfiles_upload/' . $params['filename'];
            }

            $delete_file_action = $params['delete_file'];

            // TODO Use api standard method, not direct creation.
            // Create new media via temporary factory.

            $getId3 = new GetId3();
            // TODO Fix GetId3 that uses create_function(), deprecated.
            $file_source = @$getId3
                ->setOptionMD5Data(true)
                ->setOptionMD5DataSource(true)
                ->setEncoding('UTF-8')
                ->analyze($full_file_path);

            $media_type = isset($file_source['mime_type']) ? $file_source['mime_type'] : 'undefined';
            if ($media_type == 'undefined') {
                $file_extension = pathinfo($full_file_path, PATHINFO_EXTENSION);
                $file_extension = strtolower($file_extension);

                // TODO Why pdf is an exception ?
                if ($file_extension == 'pdf') {
                    $media_type = 'application/pdf';
                }
            }

            $isMapped = isset($this->filesMapsArray[$media_type]);
            if (!$isMapped) {
                if (!$params['import_unmapped']) {
                    $this->layout()
                        ->setTemplate('bulk-import-files/index/process-import')
                        ->setVariable('row_id', $row_id)
                        ->setVariable('error', sprintf($this->translate('The media type "%s" is not managed or has no mapping.'), $media_type)); // @translate
                    return;
                }

                $data = [];
                $notice = $this->translate('No mapping for this file.'); // @translate
            } else {
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
            }

            if (empty($data['dcterms:title'])) {
                $data['dcterms:title'][] = [
                    'property_id' => 1,
                    'type' => 'literal',
                    '@language' => null,
                    '@value' =>  $isServer ? $params['filename'] : $params['source'],
                    'is_public' => '1',
                ];
            }

            // Create the item with the data and the file.

            // Save the file if not to be deleted.
            $tmpDir = $basePath . '/bulkimportfiles_temp';
            if (!file_exists($tmpDir)) {
                mkdir($tmpDir, 0775, true);
            }
            $tmpPath = tempnam($tmpDir, 'omk_bif_');
            copy($full_file_path, $tmpPath);
            @chmod($tmpPath, 0775);

            $url = $baseUri . '/bulkimportfiles_temp/' . basename($tmpPath);

            // Append default metadata and media with url.
            $data += [
                'o:resource_template' => ['o:id' => ''],
                'o:resource_class' => ['o:id' => ''],
                'o:thumbnail' => ['o:id' => ''],
                'o:media' => [[
                    'o:is_public' => '1',
                    'dcterms:title' => [[
                        'property_id' => 1,
                        'type' => 'literal',
                        '@language' => null,
                        '@value' => $isServer ? $params['filename'] : $params['source'],
                        'is_public' => '1',
                    ]],
                    'o:ingester' => 'url',
                    'ingest_url' => $url,
                    'o:source' => $isServer ? $params['filename'] : $params['source'],
                ]],
                'o:is_public' => '1',
            ];

            $newItem = $this->api()->create('items', $data);

            // The temp file is removed in all cases.
            @unlink($tmpPath);
            if ($newItem && $delete_file_action) {
                $result = @unlink($full_file_path);
                if (!$result) {
                    $warning = error_get_last()['message'];
                }
            }
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/process-import')
            ->setVariable('row_id', $row_id)
            ->setVariable('notice', $notice)
            ->setVariable('warning', $warning)
            ->setVariable('error', $error);
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
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $request = [];
        $request['state'] = false;
        $request['reloadURL'] = $this->url()->fromRoute(null, ['action' => 'map-edit'], true);

        $mediaType = $this->params()->fromPost('media_type');
        if (empty($mediaType)) {
            $request['msg'] = $this->translate('Request empty.'); // @translate
        } else {
            $filename = 'map_' . explode('/', $mediaType)[0] . '_' . explode('/', $mediaType)[1] . '.ini';
            $filepath = dirname(dirname(__DIR__)) . '/data/mapping/' . $filename;
            if (($handle = fopen($filepath, 'w')) === false) {
                $request['msg'] = $this->translate(sprintf('Could not save file "%s" for writing.', $filepath)); // @translate
            } else {
                $content = "$mediaType = media_type\n";
                fwrite($handle, $content);
                fclose($handle);
                $request['state'] = true;
                $request['msg'] = $this->translate('File successfully added!'); // @translate
            }
        }

        return new JsonModel($request);
    }

    public function deleteFileTypeAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $request = [];
        $request['state'] = false;
        $request['reloadURL'] = $this->url()->fromRoute(null, ['action' => 'map-edit'], true);

        $mediaType = $this->params()->fromPost('media_type');
        if (empty($mediaType)) {
            $request['msg'] = $this->translate('Request empty.'); // @translate
        } else {
            $filename = 'map_' . explode('/', $mediaType)[0] . '_' . explode('/', $mediaType)[1] . '.ini';
            $filepath = dirname(dirname(__DIR__)) . '/data/mapping/' . $filename;
            if (!strlen($filepath)) {
                $request['msg'] = $this->translate('Filepath string should be longer that zero character.'); // @translate
            } elseif (!is_writeable($filepath)) {
                $request['msg'] = $this->translate(sprintf('File "%s" is not writeable. Check rights.', $filepath)); // @translate
            } elseif (($handle = fopen($filepath, 'w')) === false) {
                $request['msg'] = $this->translate(sprintf('Could not save file "%s" for writing.', $filepath)); // @translate
            } else {
                fclose($handle);
                $result = unlink($filepath);
                if (!$result) {
                    $request['msg'] = $this->translate(sprintf('Could not delete file "%s".', $filepath)); // @translate
                } else {
                    $request['state'] = true;
                    $request['msg'] = $this->translate('File successfully deleted!'); // @translate
                }
            }
        }

        return new JsonModel($request);
    }

    public function saveOptionsAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $params = [];
        $params['omeka_file_id'] = $this->params()->fromPost('omeka_file_id');
        $params['media_type'] = $this->params()->fromPost('media_type');
        $params['listterms_select'] = $this->params()->fromPost('listterms_select');

        $error = '';
        $request = '';

        if (!empty($params['omeka_file_id'])) {
            $omeka_file_id = $params['omeka_file_id'];
            $media_type = $params['media_type'];
            $listterms_select = $params['listterms_select'];

            $file_content = "$media_type = media_type\n";

            /** @var \BulkImport\Mvc\Controller\Plugin\Bulk $bulk */
            $bulk = $this->bulk();
            foreach ($listterms_select as $term_item_name) {
                foreach ($term_item_name['property'] as $term) {
                    if (!$bulk->getPropertyTerm($term)) {
                        continue;
                    }
                    $file_content .= $term_item_name['field'] . ' = ' . $term . "\n";
                }
            }

            $folder_path = dirname(dirname(__DIR__)) . '/data/mapping';
            $response = false;
            if (!empty($folder_path)) {
                if (file_exists($folder_path) && is_dir($folder_path)) {
                    $files = $this->listFilesInDir($folder_path);
                    $file_path = $folder_path . '/';
                    foreach ($files as $file) {
                        if ($file != $omeka_file_id) {
                            continue;
                        }

                        if (!is_writeable($file_path . $file)) {
                            $error = $this->translate('Filepath "%s" is not writeable.', $file_path . $file); // @translate
                        }

                        $response = file_put_contents($file_path . $file, $file_content);
                    }
                } else {
                    $error = $this->translate('Folder not exist'); // @translate;
                }
            } else {
                $error = $this->translate('Can’t check empty folder'); // @translate;
            }

            if ($response) {
                $request = $this->translate('Mapping of properties successfully updated.'); // @translate
            } else {
                $request = $this->translate('Can’t update mapping.'); // @translate
            }
        } else {
            $request = $this->translate('Request empty.'); // @translate
        }

        $result = $error
            ? ['state' => false, 'msg' => $error]
            : ['state' => true, 'msg' => $request];
        return new JsonModel($result);
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

    protected function prepareFilesMaps()
    {
        $this->filesMaps = [];
        $folder_path = dirname(dirname(__DIR__)) . '/data/mapping';

        if (!empty($folder_path)) {
            if (file_exists($folder_path) && is_dir($folder_path)) {
                /** @var \BulkImport\Mvc\Controller\Plugin\Bulk $bulk */
                $bulk = $this->bulk();

                $files = $this->listFilesInDir($folder_path);
                $file_path = $folder_path . '/';
                foreach ($files as $file) {
                    $data = file_get_contents($file_path . $file);
                    $data = trim($data);
                    if (empty($data)) {
                        continue;
                    }

                    $data_rows = array_filter(array_map('trim', preg_split('/\n|\r\n?/', $data)));

                    $mediaType = null;
                    $current_maps = [];
                    foreach ($data_rows as $value) {
                        $value = array_map('trim', explode('=', $value));
                        if (count($value) !== 2) {
                            continue;
                        }

                        if (in_array('media_type', $value)) {
                            $mediaType = $value[0] === 'media_type' ? $value[1] : $value[0];
                            continue;
                        }

                        // Reorder as mapping = term.
                        // A term has no "/" and no ".", but requires a ":".
                        if (strpos($value[0], '/') === false
                            && strpos($value[0], '.') === false
                            && strpos($value[0], ':') !== false
                        ) {
                            $term = $value[0];
                            $map = $value[1];
                        } else {
                            $term = $value[1];
                            $map = $value[0];
                        }

                        if (strpos($term, ':') === false || count(explode(':', $term)) !== 2) {
                            continue;
                        }
                        $term = $bulk->getPropertyTerm($term);
                        if (!$term) {
                            continue;
                        }

                        $current_maps[$term][] = $map;
                    }

                    if ($mediaType) {
                        $current_maps['item_id'] = $file;
                        $this->filesMapsArray[$mediaType] = $current_maps;
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
}
