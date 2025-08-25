<?php declare(strict_types=1);

namespace BulkImportFiles\Controller;

// The autoload doesn’t work with GetId3.
if (!class_exists('JamesHeinrich\GetID3\GetId3', false)) {
    require dirname(__DIR__, 2) . '/vendor/james-heinrich/getid3/src/GetID3.php';
}

use BulkImportFiles\Form\ImportForm;
use BulkImportFiles\Form\SettingsForm;
use JamesHeinrich\GetID3\GetId3;
use Laminas\Form\FormElementManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Omeka\File\TempFile;
use Omeka\File\TempFileFactory;
use Omeka\File\Uploader;
use Omeka\Mvc\Exception\NotFoundException;

class IndexController extends AbstractActionController
{
    /** @var array */
    protected $filesMaps;

    /** @var string */
    protected $resourceTemplateLabel = 'Bulk import files';

    /**
     * Mapping by media type like 'dcterms:created' => ['jpg/exif/IFD0/DateTime'].
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

    protected TempFileFactory $tempFileFactory;
    protected Uploader $uploader;
    protected FormElementManager $formElementManager;

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

    public function makeImportAction(): void
    {
        // Only template; managed via AJAX.
    }

    public function getFilesAction(): void
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $this->prepareFilesMaps();

        $files = $this->getRequest()->getFiles()->toArray();
        // Skip dot files.
        $files['files'] = array_filter($files['files'], fn ($v) => strpos($v['name'], '.') !== 0);

        $filesDataForView = [];
        $error = '';

        if (!empty($files['files'])) {
            foreach ($files['files'] as $file) {
                $errorStore = null;
                $tempFile = $this->uploader->upload($file, $errorStore);
                if (!$tempFile) {
                    $error = $this->translate('Upload issue. Check file size and source.');
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
            $error = $this->translate('Can’t check empty folder. Check file size and source.');
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/get-files')
            ->setVariable('files_data_for_view', $filesDataForView)
            ->setVariable('listTerms', $this->listTerms())
            ->setVariable('filesMaps', $this->filesMaps)
            ->setVariable('error', $error);
    }

    public function getFolderAction(): void
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
                $files = $this->listFilesInDir($folder);
                // Skip dot files.
                $files = array_filter($files, fn ($v) => strpos($v, '.') !== 0);
                foreach ($files as $file) {
                    $fullFilePath = $folder . '/' . $file;
                    $tempFile = $this->tempFileFactory->build();
                    $tempFile->setTempPath($fullFilePath);

                    $mediaType = $tempFile->getMediaType();

                    $file = [
                        'name' => basename($fullFilePath),
                        'type' => $mediaType,
                        'tmp_name' => $fullFilePath,
                        'error' => 0,
                        'size' => filesize($fullFilePath),
                    ];

                    $filesDataForView[] = $this->getDataForFile($file, $tempFile);
                }
            } else {
                $error = $this->translate('The folder is not inside the configured Bulk Import directory.');
            }
        } else {
            $error = $this->translate('The folder is missing.');
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
            unset($filesMapsArray['media_type'], $filesMapsArray['item_id']);
        } else {
            $filesMapsArray = null;
            $file['item_id'] = null;
        }

        switch ($mediaType) {
            case 'application/pdf':
                // >>> usa il controller plugin, NON una proprietà
                $pdfData = $this->plugin('extractDataFromPdf')->__invoke($tempFile->getTempPath());
                $parsedData = $this->flatArray($pdfData);
                $data = $filesMapsArray
                    ? $this->mapData()->array($pdfData, $filesMapsArray, true)
                    : [];
                break;

            default:
                $getId3 = new GetId3();
                $fileSource = $getId3->analyze($tempFile->getTempPath());
                $parsedData = $this->flatArray($fileSource, $this->ignoredKeys);
                $data = $filesMapsArray
                    ? $this->mapData()->array($fileSource, $filesMapsArray, true)
                    : [];
                break;
        }

        return [
            'file' => $file,
            'source_data' => $parsedData,
            'recognized_data' => $data,
            'errors' => $errors,
        ];
    }

    public function checkFilesAction(): void
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $this->prepareFilesMaps();

        $files = $this->getRequest()->getFiles()->toArray();
        $files['files'] = array_filter($files['files'], fn ($v) => strpos($v['name'], '.') !== 0);

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
                $errorStore = null;
                $tempFile = $this->uploader->upload($file, $errorStore);
                if (!$tempFile) {
                    $error = $this->translate('Upload issue. Check file size and source.');
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
                    $error = $this->translate('All files must have a regular name. Check ended.');
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
                $error = $this->translate('The folder is empty or files have error.');
            }
        } else {
            $error = $this->translate('Can’t check empty folder. Check file size and source.');
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/check-files')
            ->setVariable('files_data', $filesData)
            ->setVariable('total_files', $totalFiles)
            ->setVariable('total_files_can_recognized', $totalFilesCanRecognized)
            ->setVariable('error', $error)
            ->setVariable('is_server', false);
    }

    public function checkFolderAction(): void
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
                $files = $this->listFilesInDir($folder);
                // Skip dot files.
                $files = array_filter($files, fn ($v) => strpos($v, '.') !== 0);

                foreach ($files as $file) {
                    $fullFilePath = $folder . '/' . $file;
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
                    $error = $this->translate('The folder is empty or files have error.');
                }
            } else {
                $error = $this->translate('The folder is not inside the configured Bulk Import directory.');
            }
        } else {
            $error = $this->translate('The folder is missing.');
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/check-folder')
            ->setVariable('files_data', $filesData)
            ->setVariable('total_files', $totalFiles)
            ->setVariable('total_files_can_recognized', $totalFilesCanRecognized)
            ->setVariable('error', $error)
            ->setVariable('is_server', true);
    }

    public function processImportAction(): void
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $this->prepareFilesMaps();

        $params = $this->params()->fromPost();
        $params['import_unmapped'] = $params['import_unmapped'] === 'true';
        $isServer = $params['is_server'] === 'true';
        $params['delete_file'] = !$isServer || $params['delete_file'] === 'true';

        $rowId = $params['row_id'];
        $notice = null;
        $warning = null;
        $error = null;

        if (isset($params['filename'])) {
            $tempFile = $this->tempFileFactory->build();
            if ($isServer) {
                $fullFilePath = $params['directory'] . '/' . $params['filename'];
            } else {
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
                        ->setVariable('error', sprintf($this->translate('The media type "%s" is not managed or has no mapping.'), $mediaType));
                    return;
                }

                $data = [];
                $notice = $this->translate('No mapping for this file.');
            } else {
                $filesMapsArray = $this->filesMapsArray[$mediaType];
                unset($filesMapsArray['media_type'], $filesMapsArray['item_id']);

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
                            $fileSource = $getId3->analyze($fullFilePath);
                            $data = $this->mapData()->array($fileSource, $filesMapsArray);
                            break;
                    }
                }

                if (count($data) <= 0) {
                    if ($query) {
                        $warning = $this->translate('No metadata to import. You may see log for more info.');
                    } else {
                        $notice = $this->translate('No metadata: mapping is empty.');
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

            $response = $this->api()->create('items', $data);

            if (!$response) {
                $error = 'Unable to process import.';
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

        $request = ['state' => false, 'reloadURL' => $this->url()->fromRoute(null, ['action' => 'map-edit'], true)];

        $mediaType = $this->params()->fromPost('media_type');
        if (empty($mediaType)) {
            $request['msg'] = $this->translate('Request empty.');
        } else {
            $filename = 'map_' . explode('/', $mediaType)[0] . '_' . explode('/', $mediaType)[1] . '.ini';
            $filepath = dirname(__DIR__, 2) . '/data/mapping/' . $filename;
            if (($handle = fopen($filepath, 'w')) === false) {
                $request['msg'] = sprintf($this->translate('Could not save file "%s" for writing.'), mb_substr($filepath, mb_strlen(OMEKA_PATH)));
            } else {
                fwrite($handle, "$mediaType = media_type\n");
                fclose($handle);
                $request['state'] = true;
                $request['msg'] = $this->translate('File successfully added!');
            }
        }

        return new JsonModel($request);
    }

    public function deleteFileTypeAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $request = ['state' => false, 'reloadURL' => $this->url()->fromRoute(null, ['action' => 'map-edit'], true)];

        $mediaType = $this->params()->fromPost('media_type');
        if (empty($mediaType)) {
            $request['msg'] = $this->translate('Request empty.');
        } else {
            $filename = 'map_' . explode('/', $mediaType)[0] . '_' . explode('/', $mediaType)[1] . '.ini';
            $filepath = dirname(__DIR__, 2) . '/data/mapping/' . $filename;
            if (!strlen($filepath)) {
                $request['msg'] = $this->translate('Filepath string should be longer that zero character.');
            } elseif (!is_writeable($filepath)) {
                $request['msg'] = sprintf($this->translate('File "%s" is not writeable. Check rights.'), mb_substr($filepath, mb_strlen(OMEKA_PATH)));
            } elseif (($handle = fopen($filepath, 'w')) === false) {
                $request['msg'] = sprintf($this->translate('Could not save file "%s" for writing.'), mb_substr($filepath, mb_strlen(OMEKA_PATH)));
            } else {
                fclose($handle);
                $result = unlink($filepath);
                if (!$result) {
                    $request['msg'] = sprintf($this->translate('Could not delete file "%s".'), mb_substr($filepath, mb_strlen(OMEKA_PATH)));
                } else {
                    $request['state'] = true;
                    $request['msg'] = $this->translate('File successfully deleted!');
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

        $folderPath = dirname(__DIR__, 2) . '/data/mapping';
        if (empty($folderPath) || !file_exists($folderPath) || !is_dir($folderPath) || !is_writeable($folderPath)) {
            return new JsonModel(['state' => false, 'msg' => $this->translate('Folder /modules/BulkImportFiles/data/mapping is not available or not writeable.')]);
        }

        $params = [];
        $params['omeka_file_id'] = $this->params()->fromPost('omeka_file_id');
        $params['media_type'] = $this->params()->fromPost('media_type');
        $params['listterms_select'] = $this->params()->fromPost('listterms_select');

        $error = '';
        $request = '';

        if (empty($params['omeka_file_id'])) {
            $params['omeka_file_id'] = 'map_' . str_replace('/', '_', $params['media_type']) . '.ini';
            $fullFilePath = $folderPath . '/' . $params['omeka_file_id'];
            if (!file_exists($fullFilePath)) {
                $created = @touch($fullFilePath);
                if (!$created) {
                    return new JsonModel(['state' => false, 'msg' => $this->translate('Unable to create a new mapping in folder data/mapping.')]);
                }
            }
        }

        $omekaFileId = $params['omeka_file_id'];
        $mediaType = $params['media_type'];
        $listterms_select = $params['listterms_select'];

        $fileContent = "$mediaType = media_type\n";

        /** @var \Common\Mvc\Controller\Plugin\EasyMeta $easyMeta */
        $easyMeta = $this->easyMeta();
        foreach ($listterms_select as $termItemName) {
            foreach ($termItemName['property'] as $term) {
                if (!$easyMeta->propertyTerm($term)) {
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
            $error = sprintf($this->translate('Filepath "%s" is not writeable.'), mb_substr($fullFilePath, mb_strlen(OMEKA_PATH)));
        }

        $result = $response
            ? ['state' => true, 'msg' => $this->translate('Mapping of properties successfully updated.')]
            : ['state' => false, 'msg' => ($error ?: $this->translate('Can’t update mapping.'))];

        return new JsonModel($result);
    }

    protected function flatArray(array $data, array $ignoredKeys = [])
    {
        $this->flatArray = [];
        $this->_flatArray($data, $ignoredKeys);
        $result = $this->flatArray;
        $this->flatArray = [];
        return $result;
    }

    private function _flatArray(array $data, array $ignoredKeys = [], $keys = null): void
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
     * List files in a directory, not recursively, and without subdirs,
     * and sort them alphabetically (case insensitive and natural order).
     */
    protected function listFilesInDir($dir)
    {
        if (empty($dir) || !file_exists($dir) || !is_dir($dir) || !is_readable($dir)) {
            return [];
        }
        $result = array_values(array_filter(scandir($dir), fn ($file) => is_file($dir . DIRECTORY_SEPARATOR . $file)));
        natcasesort($result);
        return $result;
    }

    protected function prepareFilesMaps(): void
    {
        $this->filesMaps = [];
        $folderPath = dirname(__DIR__, 2) . '/data/mapping';
        if (!empty($folderPath)) {
            if (file_exists($folderPath) && is_dir($folderPath)) {
                /** @var \Common\Mvc\Controller\Plugin\EasyMeta $easyMeta */
                $easyMeta = $this->easyMeta();

                $files = $this->listFilesInDir($folderPath);
                $filePath = $folderPath . '/';
                foreach ($files as $file) {
                    $fullFilePath = $filePath . $file;
                    $data = trim((string) file_get_contents($fullFilePath));
                    if ($data === '') {
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

                        if (in_array('media_type', $value, true)) {
                            $mediaType = $value[0] === 'media_type' ? $value[1] : $value[0];
                            continue;
                        }

                        // Reorder as mapping = term.
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
                        $term = $easyMeta->propertyTerm($term);
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
                // noop: cartella mapping non esiste
            }
        }
    }

    /**
     * List all terms of all vocabularies to build a select with option group.
     */
    protected function listTerms()
    {
        $result = [];
        $factory = new \Laminas\Form\Factory($this->formElementManager);
        $element = $factory->createElement([
            'type' => \Omeka\Form\Element\PropertySelect::class,
        ]);
        $listTerms = $element->getValueOptions();

        foreach ($listTerms as $vocabulary) {
            foreach ($vocabulary['options'] as $property) {
                $result[$vocabulary['label']][$property['attributes']['data-term']] = $property['label'];
            }
        }

        return $result;
    }

    /**
     * Verify the passed dir.
     * @return string|false The real folder path or false if invalid.
     */
    protected function verifyDirectory($dirpath)
    {
        // ATTENZIONE: la chiave DEVE combaciare con module.config.php
        $directory = $this->settings()->get('bulkimportfiles_local_path');
        if (empty($directory)) {
            return false;
        }
        $fileinfo = new \SplFileInfo($dirpath);
        if (!$fileinfo->isDir()) {
            return false;
        }
        $realPath = $fileinfo->getRealPath();
        if ($realPath === false) {
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
