<?php

namespace BulkImportFiles\Controller;

// The autoload doesn’t work with GetId3.
if (!class_exists(\JamesHeinrich\GetID3\GetId3::class)) {
    require dirname(dirname(__DIR__)) . '/vendor/james-heinrich/getid3/src/GetID3.php';
}

use BulkImportFiles\Form\ImportForm;
use BulkImportFiles\Form\SettingsForm;
use JamesHeinrich\GetID3\GetId3;
use Omeka\File\TempFile;
use Omeka\File\TempFileFactory;
use Omeka\File\Uploader;
use Omeka\Mvc\Exception\NotFoundException;
use Zend\Form\FormElementManager\FormElementManagerV3Polyfill as FormElementManager;
use Zend\Mvc\Controller\AbstractActionController;
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

    protected $parsedData;

    protected $filesData;

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

    /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * @var Uploader
     */
    protected $uploader;

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @param TempFileFactory $tempFileFactory
     * @param Uploader $uploader,
     * @param FormElementManager $formElementManager
     */
    public function __construct(
        TempFileFactory $tempFileFactory,
        Uploader $uploader,
        FormElementManager $formElementManager
    ) {
        $this->tempFileFactory = $tempFileFactory;
        $this->uploader = $uploader;
        $this->formElementManager = $formElementManager;
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
        // Simply display the template, that is managed by ajax.
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
        $files['files'] = array_filter($files['files'], function ($v) {
            return strpos($v['name'], '.') !== 0;
        });

        $filesDataForView = [];
        $error = '';

        if (!empty($files['files'])) {
            foreach ($files['files'] as $file) {
                // TODO Manage the error store.
                $errorStore = null;
                $tempFile = $this->uploader->upload($file, $errorStore);
                if (!$tempFile) {
                    $error = $this->translate('Upload issue. Check file size and source.'); // @translate,
                    $filesDataForView[] = [
                        'file' => $file,
                        'errors' => [
                            $this->translate('Unable to upload the file. Check file size and source.'),
                        ],
                    ];
                    continue;
                }
                $file['tmp_name'] = $tempFile->getTempPath();
                $file['type'] = $tempFile->getMediaType();

                $filesDataForView[] = $this->getDataForFile($file, $tempFile);
            }
        } else {
            $error = $this->translate('Can’t check empty folder. Check file size and source.'); // @translate,
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/get-files')
            ->setVariable('files_data_for_view', $filesDataForView)
            ->setVariable('listTerms', $this->listTerms())
            ->setVariable('filesMaps', $this->filesMaps)
            ->setVariable('error', $error);
    }

    public function getFolderAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $this->prepareFilesMaps();

        $filesDataForView = [];
        $error = '';

        $params = $this->params()->fromPost();
        if (!empty($params['folder'])) {
            $folder = $this->verifyDirectory($params['folder']);
            if ($folder) {
                $params['folder'] = $folder;
                $files = $this->listFilesInDir($folder);
                // Skip dot files.
                $files = array_filter($files, function ($v) {
                    return strpos($v, '.') !== 0;
                });
                $filePath = $folder . '/';
                foreach ($files as $file) {
                    $fullFilePath = $filePath . $file;
                    // TODO Manage the error store.
                    $tempFile = $this->tempFileFactory->build();
                    $tempFile->setTempPath($fullFilePath);

                    $mediaType = $tempFile->getMediaType();

                    $file = [];
                    $file['name'] = basename($fullFilePath);
                    $file['type'] = $mediaType;
                    $file['tmp_name'] = $fullFilePath;
                    $file['error'] = 0;
                    $file['size'] = filesize($fullFilePath);

                    $filesDataForView[] = $this->getDataForFile($file, $tempFile);
                }
            } else {
                $error = $this->translate('The folder is not inside the configured Bulk Import directory.'); // @translate,
            }
        } else {
            $error = $this->translate('The folder is missing.'); // @translate,
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/get-folder')
            ->setVariable('files_data_for_view', $filesDataForView)
            ->setVariable('listTerms', $this->listTerms())
            ->setVariable('filesMaps', $this->filesMaps)
            ->setVariable('error', $error);
    }

    protected function getDataForFile(array $file, TempFile $tempFile)
    {
        $mediaType = $tempFile->getMediaType();
        $data = [];
        $parsedData = [];
        $errors = '';

        if (isset($this->filesMapsArray[$mediaType])) {
            $filesMapsArray = $this->filesMapsArray[$mediaType];
            $file['item_id'] = $filesMapsArray['item_id'];
            unset($filesMapsArray['media_type']);
            unset($filesMapsArray['item_id']);
        } else {
            $filesMapsArray = null;
            $file['item_id'] = null;
        }

        switch ($mediaType) {
            case 'application/pdf':
                $data = $this->extractDataFromPdf($tempFile->getTempPath());
                $parsedData = $this->flatArray($data);
                $data = $filesMapsArray
                    ? $this->mapData()->array($data, $filesMapsArray, true)
                    : [];
                break;

            default:
                $getId3 = new GetId3();
                $fileSource = $getId3
                    ->analyze($tempFile->getTempPath());
                $parsedData = $this->flatArray($fileSource, $this->ignoredKeys);
                $data = $filesMapsArray
                    ? $this->mapData()->array($fileSource, $filesMapsArray, true)
                    : [];
                break;
        }

        /*
         * selected files for uploads
         * $file array
         *      'name' => string
         *      'type' => string
         *      'tmp_name' => string
         *      'error' => int
         *      'size' => int
         *      'item_id' => string (map file)
         *
         * $source_data = $parsedData
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
        return [
            'file' => $file,
            'source_data' => $parsedData,
            'recognized_data' => $data,
            'errors' => $errors,
        ];
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
        $files['files'] = array_filter($files['files'], function ($v) {
            return strpos($v['name'], '.') !== 0;
        });

        $filesData = [];
        $totalFiles = 0;
        $totalFilesCanRecognized = 0;
        $error = '';

        // Save the files temporary for the next request.
        $dest = sys_get_temp_dir() . '/bulkimportfiles_upload/';
        if (!file_exists($dest)) {
            mkdir($dest, 0775, true);
        }

        if (!empty($files['files'])) {
            foreach ($files['files'] as $file) {
                // TODO Manage the error store.
                $errorStore = null;
                $tempFile = $this->uploader->upload($file, $errorStore);
                if (!$tempFile) {
                    $error = $this->translate('Upload issue. Check file size and source.'); // @translate,
                    $filesData[] = [
                        'source' => $file['name'],
                        'filename' => basename($file['tmp_name']),
                        'file_size' => $file['size'],
                        'file_type' => $file['type'],
                        'file_isset_maps' => 'no',
                        'has_error' => $file['error'],
                    ];
                    continue;
                }
                $file['tmp_name'] = $tempFile->getTempPath();
                $file['type'] = $tempFile->getMediaType();

                // Check name for security.
                if (basename($file['name']) !== $file['name']) {
                    $error = $this->translate('All files must have a regular name. Check ended.'); // @translate
                    break;
                }

                if ($file['error'] === UPLOAD_ERR_OK) {
                    ++$totalFiles;
                    $fullFilePath = $dest . basename($file['tmp_name']);
                    move_uploaded_file($file['tmp_name'], $fullFilePath);
                }

                if ($file['type'] && isset($this->filesMapsArray[$file['type']])) {
                    $fileIssetMaps = 'yes';
                    ++$totalFilesCanRecognized;
                } else {
                    $fileIssetMaps = 'no';
                }

                $filesData[] = [
                    'source' => $file['name'],
                    'filename' => basename($file['tmp_name']),
                    'file_size' => $file['size'],
                    'file_type' => $file['type'],
                    'file_isset_maps' => $fileIssetMaps,
                    'has_error' => $file['error'],
                ];
            }

            if (!$error && count($filesData) == 0) {
                $error = $this->translate('The folder is empty or files have error.'); // @translate;
            }
        } else {
            $error = $this->translate('Can’t check empty folder. Check file size and source.'); // @translate,
        }

        // This is not a full view, only a partial html.
        $this->layout()
            ->setTemplate('bulk-import-files/index/check-files')
            ->setVariable('files_data', $filesData)
            ->setVariable('total_files', $totalFiles)
            ->setVariable('total_files_can_recognized', $totalFilesCanRecognized)
            ->setVariable('error', $error)
            ->setVariable('is_server', false);
    }

    public function checkFolderAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $this->prepareFilesMaps();

        $filesData = [];
        $totalFiles = 0;
        $totalFilesCanRecognized = 0;
        $error = '';

        $params = $this->params()->fromPost();
        if (!empty($params['folder'])) {
            $folder = $this->verifyDirectory($params['folder']);
            if ($folder) {
                $files = $this->listFilesInDir($params['folder']);
                // Skip dot files.
                $files = array_filter($files, function ($v) {
                    return strpos($v, '.') !== 0;
                });
                $filePath = $params['folder'] . '/';

                foreach ($files as $file) {
                    $fullFilePath = $filePath . $file;
                    // TODO Manage the error store.
                    $tempFile = $this->tempFileFactory->build();
                    $tempFile->setTempPath($fullFilePath);

                    ++$totalFiles;

                    $mediaType = $tempFile->getMediaType();
                    if ($mediaType && isset($this->filesMapsArray[$mediaType])) {
                        $fileIssetMaps = 'yes';
                        ++$totalFilesCanRecognized;
                    } else {
                        $fileIssetMaps = 'no';
                    }

                    $filesData[] = [
                        'source' => $file,
                        'filename' => basename($file),
                        'file_size' => filesize($fullFilePath),
                        'file_type' => $mediaType,
                        'file_isset_maps' => $fileIssetMaps,
                    ];
                }

                if (count($filesData) == 0) {
                    $error = $this->translate('The folder is empty or files have error.'); // @translate;
                }
            } else {
                $error = $this->translate('The folder is not inside the configured Bulk Import directory.'); // @translate,
            }
        } else {
            $error = $this->translate('The folder is missing.'); // @translate,
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/check-folder')
            ->setVariable('files_data', $filesData)
            ->setVariable('total_files', $totalFiles)
            ->setVariable('total_files_can_recognized', $totalFilesCanRecognized)
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

        $params = $this->params()->fromPost();
        $params['import_unmapped'] = $params['import_unmapped'] === 'true';
        $isServer = $params['is_server'] === 'true';
        $params['delete_file'] = !$isServer || $params['delete_file'] === 'true';

        $rowId = $params['row_id'];
        $notice = null;
        $warning = null;
        $error = null;

        if (isset($params['filename'])) {
            // The file is already checked.
            $tempFile = $this->tempFileFactory->build();
            if ($isServer) {
                $fullFilePath = $params['directory'] . '/' . $params['filename'];
            } else {
                // Temp dir is available in config, it's get quickly here.
                $tmpPath = pathinfo($tempFile->getTempPath(), PATHINFO_DIRNAME);
                $fullFilePath = $tmpPath . DIRECTORY_SEPARATOR . $params['filename'];
            }
            $tempFile->setTempPath($fullFilePath);
            $tempFile->setSourceName($params['source']);

            $deleteFileAction = $params['delete_file'];
            $mediaType = $tempFile->getMediaType();

            $isMapped = isset($this->filesMapsArray[$mediaType]);
            if (!$isMapped) {
                if (!$params['import_unmapped']) {
                    $this->layout()
                        ->setTemplate('bulk-import-files/index/process-import')
                        ->setVariable('row_id', $rowId)
                        ->setVariable('error', sprintf($this->translate('The media type "%s" is not managed or has no mapping.'), $mediaType)); // @translate
                    return;
                }

                $data = [];
                $notice = $this->translate('No mapping for this file.'); // @translate
            } else {
                $filesMapsArray = $this->filesMapsArray[$mediaType];
                unset($filesMapsArray['media_type']);
                unset($filesMapsArray['item_id']);

                // Use xml or array according to item mapping.
                $query = reset($filesMapsArray);
                $query = $query ? reset($query) : null;
                $isXpath = $query && strpos($query, '/') !== false;
                if ($isXpath) {
                    $data = $this->mapData()->xml($fullFilePath, $filesMapsArray);
                } else {
                    switch ($mediaType) {
                        case 'application/pdf':
                            $data = $this->mapData()->pdf($fullFilePath, $filesMapsArray);
                            break;
                        default:
                            $getId3 = new GetId3();
                            $fileSource = $getId3
                                ->analyze($fullFilePath);
                            $data = $this->mapData()->array($fileSource, $filesMapsArray);
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
                    '@value' => $isServer ? $params['filename'] : $params['source'],
                    'is_public' => '1',
                ];
            }

            // Create the item with the data and the file.
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
                    'o:ingester' => 'bulk',
                    'ingest_ingester' => $isServer ? 'sideload' : 'upload',
                    'ingest_tempfile' => $tempFile,
                    'ingest_delete_file' => $deleteFileAction,
                    'o:source' => $isServer ? $params['filename'] : $params['source'],
                ]],
                'o:is_public' => '1',
            ];

            /** @var \Omeka\Api\Response $response */
            $response = $this->api()->create('items', $data);

            // The temp file is removed in all cases.
            if (!$response) {
                $error = 'Unable to process import.'; // @translate
            }
        } else {
            $error = 'No file to process.';
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/process-import')
            ->setVariable('row_id', $rowId)
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
                $request['msg'] = sprintf($this->translate('Could not save file "%s" for writing.'), mb_substr($filepath, mb_strlen(OMEKA_PATH))); // @translate
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
                $request['msg'] = sprintf($this->translate('File "%s" is not writeable. Check rights.'), mb_substr($filepath, mb_strlen(OMEKA_PATH))); // @translate
            } elseif (($handle = fopen($filepath, 'w')) === false) {
                $request['msg'] = sprintf($this->translate('Could not save file "%s" for writing.'), mb_substr($filepath, mb_strlen(OMEKA_PATH))); // @translate
            } else {
                fclose($handle);
                $result = unlink($filepath);
                if (!$result) {
                    $request['msg'] = sprintf($this->translate('Could not delete file "%s".'), mb_substr($filepath, mb_strlen(OMEKA_PATH))); // @translate
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

        $folderPath = dirname(dirname(__DIR__)) . '/data/mapping';
        if (empty($folderPath) || !file_exists($folderPath) || !is_dir($folderPath) || !is_writeable($folderPath)) {
            $result = ['state' => false, 'msg' => $this->translate('Folder /modules/BulkImportFiles/data/mapping is not available or not writeable.')]; // @translate
            return new JsonModel($result);
        }

        $params = [];
        $params['omeka_file_id'] = $this->params()->fromPost('omeka_file_id');
        $params['media_type'] = $this->params()->fromPost('media_type');
        $params['listterms_select'] = $this->params()->fromPost('listterms_select');

        $error = '';
        $request = '';

        // Check for a new mapping.
        if (empty($params['omeka_file_id'])) {
            $params['omeka_file_id'] = 'map_' . str_replace('/', '_', $params['media_type']) . '.ini';
            $fullFilePath = $folderPath . '/' . $params['omeka_file_id'];
            if (!file_exists($fullFilePath)) {
                $result = @touch($fullFilePath);
                if (!$result) {
                    $result = ['state' => false, 'msg' => $this->translate('Unable to create a new mapping in folder data/mapping.')]; // @translate
                    return new JsonModel($result);
                }
            }
        }

        $omekaFileId = $params['omeka_file_id'];
        $mediaType = $params['media_type'];
        $listterms_select = $params['listterms_select'];

        $fileContent = "$mediaType = media_type\n";

        /** @var \BulkImport\Mvc\Controller\Plugin\Bulk $bulk */
        $bulk = $this->bulk();
        foreach ($listterms_select as $termItemName) {
            foreach ($termItemName['property'] as $term) {
                if (!$bulk->getPropertyTerm($term)) {
                    continue;
                }
                $fileContent .= $termItemName['field'] . ' = ' . $term . "\n";
            }
        }

        $fullFilePath = $folderPath . '/' . $omekaFileId;
        if (is_writeable($fullFilePath)) {
            $response = file_put_contents($fullFilePath, $fileContent);
        } else {
            $response = false;
            $error = sprintf($this->translate('Filepath "%s" is not writeable.'), mb_substr($fullFilePath, mb_strlen(OMEKA_PATH))); // @translate
        }

        if ($response) {
            $request = $this->translate('Mapping of properties successfully updated.'); // @translate
        } else {
            $request = $this->translate('Can’t update mapping.'); // @translate
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
            } elseif (!in_array($key, $ignoredKeys, true)) {
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
        $folderPath = dirname(dirname(__DIR__)) . '/data/mapping';
        if (!empty($folderPath)) {
            if (file_exists($folderPath) && is_dir($folderPath)) {
                /** @var \BulkImport\Mvc\Controller\Plugin\Bulk $bulk */
                $bulk = $this->bulk();

                $files = $this->listFilesInDir($folderPath);
                $filePath = $folderPath . '/';
                foreach ($files as $file) {
                    $fullFilePath = $filePath . $file;
                    $data = file_get_contents($fullFilePath);
                    $data = trim($data);
                    if (empty($data)) {
                        continue;
                    }

                    $data_rows = array_filter(array_map('trim', preg_split('/\n|\r\n?/', $data)));

                    $mediaType = null;
                    $currentMaps = [];
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

                        $currentMaps[$term][] = $map;
                    }

                    if ($mediaType) {
                        $currentMaps['item_id'] = $file;
                        $this->filesMapsArray[$mediaType] = $currentMaps;
                    }

                    $currentMaps['media_type'] = $mediaType;
                    $this->filesMaps[$file] = $currentMaps;
                }
            } else {
                $error = $this->translate('Folder not exist'); // @translate;
            }
        } else {
            $error = $this->translate('Can’t check empty folder'); // @translate;
        }
    }

    /**
     * List all terms of all vocabularies to build a select with option group..
     *
     * @return array
     */
    protected function listTerms()
    {
        $result = [];

        // The simplest way to get the list of all properties by vocabulary.
        // TODO Use a true form element and use chosen dynamically.
        $factory = new \Zend\Form\Factory($this->formElementManager);
        $element = $factory->createElement([
            'type' => \Omeka\Form\Element\PropertySelect::class,
        ]);
        $listTerms = $element->getValueOptions();

        // Convert the list to a list for select with option group.
        // TODO Keep the full select array, compatible with js chosen.
        foreach ($listTerms as $vocabulary) {
            foreach ($vocabulary['options'] as $property) {
                $result[$vocabulary['label']][$property['attributes']['data-term']] = $property['label'];
            }
        }

        return $result;
    }

    /**
     * Verify the passed dir.
     *
     * @param string $dirpath
     * @return string|false The real folder path or false if invalid.
     */
    protected function verifyDirectory($dirpath)
    {
        $directory = $this->settings()->get('bulkimport_local_path');
        if (empty($directory)) {
            return false;
        }
        $fileinfo = new \SplFileInfo($dirpath);
        if (!$fileinfo->isDir()) {
            return false;
        }
        $realPath = $fileinfo->getRealPath();
        if (false === $realPath) {
            return false;
        }
        if (0 !== strpos($realPath, $directory)) {
            return false;
        }
        if (!file_exists($realPath)) {
            return false;
        }
        return $realPath;
    }
}
