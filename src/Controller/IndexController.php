<?php

namespace BulkImportFile\Controller;

use BulkImportFile\Form\ImportForm;
use BulkImportFile\Form\SettingsForm;
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
     * @var array
     */
    protected $filesMaps;

    /**
     * @var array
     */
    protected $filesMapsArray;

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
        'md5_data'
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
        $this->prepareFilesMaps();

        $form = $this->getForm(SettingsForm::class);

        $view = new ViewModel;
        $view->setVariable('values', $this->filesMaps);
        $view->setVariable('form', $form);
        return $view;
    }

    public function makeimportAction()
    {
    }

    public function mapimportAction()
    {
        $form = $this->getForm(ImportForm::class);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function getFilesAction()
    {
        $this->prepareFilesMaps();

        $request = $this->getRequest();

        $files = $request->getFiles()->toArray();

        $files_data_for_view = [];

        foreach ($files['files'] as $file) {
            $file_type = $file['type'];
            $recognized_data = '';
            $errors = '';
            $this->parsed_data = '';

            if (isset($this->filesMapsArray[$file_type])) {
                $file['item_id'] = $this->filesMapsArray[$file_type]['item_id'];

                switch ($file_type) {
                    case 'application/pdf':
                        $errors = 'PDF extension, which is not loaded.';
                        break;

                    default:
                        $getId3 = new GetId3();

                        $file_source = $getId3
                            ->setOptionMD5Data(true)
                            ->setOptionMD5DataSource(true)
                            ->setEncoding('UTF-8')
                            ->analyze($file['tmp_name']);

                        /**
                         * $filesMapsArray like 'dcterms:created' => string 'jpg/exif/IFD0/DateTime'
                         */

                        $mime_type = 'undefined';
                        if (isset($file_source['mime_type'])) {
                            $mime_type = $file_source['mime_type'];
                        }

                        $filesMapsArray = [];
                        if (isset($this->filesMapsArray[$mime_type])) {
                            $filesMapsArray = $this->filesMapsArray[$mime_type];
                        }

                        foreach ($filesMapsArray as $key => $val) {
                            $filesMaps = explode('/', $val);

                            $file_fields_source = $file_source;
                            foreach ($filesMaps as $fval) {
                                if (isset($file_fields_source[$fval])) {
                                    $file_fields_source = $file_fields_source[$fval];
                                }
                            }

                            if (!is_array($file_fields_source)) {
                                $recognized_data[$key] = [
                                    'field' => $val,
                                    'value' => $file_fields_source,
                                ];
                            }
                        }

                        $this->array_keys_recursive($file_source);
                        break;
                }
            }

            $files_data_for_view[] = [
                'file' => $file,
                'exif_data' => $this->parsed_data,
                'recognized_data' => $recognized_data,
                'errors' => $errors,
            ];
        }

        $this->layout()
            ->setTemplate('common/blockfileslist')
            ->setVariable('files_data_for_view', $files_data_for_view)
            // List omeka properties.
            ->setVariable('listTerms', $this->listTerms())
            // All saved file types with saved properties.
            // key = item id; values = mapping
            // (ex.: public 'dcterms:title' => string 'image/jpeg')
            ->setVariable('filesMaps', $this->filesMaps);
    }

    protected function array_keys_recursive($data_array, $keys = null)
    {
        foreach ($data_array as $key => $val) {
            if (is_array($val)) {
                $this->array_keys_recursive($val, $keys . '/' . $key);
            } else {
                if (!in_array($key, $this->ignoredKeys)) {
                    $this->parsed_data[] = [
                        'key' => $keys . '/' . $key,
                        'value' => $val,
                    ];
                }
            }
        }
    }

    /**
     * Return the list of properties by names and labels from Dublin Core.
     *
     * @return array Associative array of term names and term labels as key
     * (ex: "dcterms:title" and "Dublin Core : Title") in two subarrays ("names"
     * "labels", and properties as value.
     */
    protected function listTerms()
    {
        $result = [];
        $vocabulary = $this->api()->search('vocabularies', ['vocabulary_id' => 1])->getContent();
        $properties = $vocabulary[0]->properties();
        foreach ($properties as $property) {
            $result[] = $property->term();
        }
        return $result;
    }

    public function saveOptionAction()
    {
        if ((isset($_REQUEST['omeka_item_id'])) && $_REQUEST['omeka_item_id'] != '') {
            $omeka_item_id = $_REQUEST['omeka_item_id'];
            $file_field_property = $_REQUEST['file_field_property'];
            $listterms_select = $_REQUEST['listterms_select'];

            $form = $this->getForm(ResourceForm::class)
                ->setAttribute('action', $this->url()->fromRoute(null, [], true))
                ->setAttribute('enctype', 'multipart/form-data')
                ->setAttribute('id', 'edit-item');

            $items = $this->api()->read('items', ['id' => $omeka_item_id])->getContent();
            $values = $items->valueRepresentation();

            $resourceTemplate = $this->api()
                ->read('resource_templates', ['label' => 'BulkImportFile Resource'])
                ->getContent();

            $data = [
                'o:resource_template' => [
                    'o:id' => $resourceTemplate->id()
                ],
                'o:resource_class' => [
                    'o:id' => ''
                ],
                'dcterms:title' => [
                    '0' => [
                        'property_id' => '1',
                        'type' => 'literal',
                        '@language' => '',
                        '@value' => $values['display_title'],
                        'is_public' => '1'
                    ]
                ],
                'o:thumbnail' => [
                    'o:id' => ''
                ],
                'o:is_public' => '1'
            ];

            foreach ($listterms_select as $term_item_name) {
                if (isset($term_item_name['property'])) {
                    foreach ($term_item_name['property'] as $val) {
                        $term = explode(':', $val);

                        $term_item = $this->api()->search('properties', ['vocabulary_id' => 1, 'local_name' => $term[1]])->getContent();

                        $data [$val] = [
                            '0' => [
                                'property_id' => $term_item[0]->id(),
                                'type' => 'literal',
                                '@language' => '',
                                '@value' => $term_item_name['field'],
                                'is_public' => '1'
                            ]
                        ];
                    }
                }
            }

            $form->setData($data);

            $response = $this->api($form)->update('items', $omeka_item_id, $data);

            if ($response) {
                $request = $this->translate('Item property successfully updated'); // @translate
            } else {
                $request = $this->translate('Can’t update item property'); // @translate
            }
        } else {
            $request = $this->translate('Can’t update item property'); // @translate
        }

        $this->layout()
            ->setTemplate('bulk-import-file/index/save-option')
            ->setVariable('request', $request);
    }

    public function checkFolderAction()
    {
        $this->prepareFilesMaps();
        $error = '';
        $files_data = [];

        if ((isset($_REQUEST['folder'])) && ($_REQUEST['folder'] != '')) {
            if (file_exists($_REQUEST['folder'])) {
                $files = array_diff(scandir($_REQUEST['folder']), array('.', '..'));

                $file_path = $_REQUEST['folder'] . '/';

                $total_files = 0;
                $total_files_can_recognized = 0;

                foreach ($files as $file) {
                    $getId3 = new GetId3();

                    $file_source = $getId3
                        ->setOptionMD5Data(true)
                        ->setOptionMD5DataSource(true)
                        ->setEncoding('UTF-8')
                        ->analyze($file_path . $file);

                    ++$total_files;

                    $mime_type = 'undefined';
                    $file_isset_maps = 'no';

                    if (isset($file_source['mime_type'])) {
                        $mime_type = $file_source['mime_type'];

                        if (isset($this->filesMapsArray[$mime_type])) {
                            $file_isset_maps = 'yes';
                            ++$total_files_can_recognized;
                        }
                    }

                    $files_data[] = [
                        'filename' => $file_source['filename'],
                        'file_size' => $file_source['filesize'],
                        'file_type' => $mime_type,
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
            ->setTemplate('bulk-import-file/index/check-folder')
            ->setVariable('files_data', $files_data)
            ->setVariable('total_files', $total_files)
            ->setVariable('total_files_can_recognized', $total_files_can_recognized)
            ->setVariable('error', $error);
    }

    public function actionMakeImportAction()
    {
        $this->prepareFilesMaps();

        $api = $this->api();

        $data_for_recognize_row_id = '';
        $error = '';
        $recognized_data = [];
        $url = $this->getRequest()->getUri();
        $url_path = $url->getScheme() . '://' . $url->getHost();

        if (isset($_REQUEST['data_for_recognize_single'])) {
            $full_file_path = $_REQUEST['directory'] . '/' . $_REQUEST['data_for_recognize_single'];

            $delete_file_action = $_REQUEST['delete-file'];

            // Create new media.

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
            $url = $url_path . '/files/original/' . $tempFile->getStorageId() . '.' . $tempFile->getExtension();

            $getId3 = new GetId3();

            $file_source = $getId3
                ->setOptionMD5Data(true)
                ->setOptionMD5DataSource(true)
                ->setEncoding('UTF-8')
                ->analyze($full_file_path);

            $mime_type = 'undefined';
            if (isset($file_source['mime_type'])) {
                $mime_type = $file_source['mime_type'];
            }

            $filesMapsArray = $this->filesMapsArray[$mime_type];
            foreach ($filesMapsArray as $key => $val) {
                $filesMaps = explode('/', $val);

                $file_fields_source = $file_source;

                foreach ($filesMaps as $fval) {
                    if (isset($file_fields_source[$fval])) {
                        $file_fields_source = $file_fields_source[$fval];
                    }
                }

                if (!is_array($file_fields_source)) {
                    $recognized_data[$key] = [
                        'field' => $val,
                        'value' => $file_fields_source,
                    ];
                }
            }

            // Check for item title in "dcterms:alternative". If not, set item
            // title as file name.
            if (isset($recognized_data['dcterms:alternative'])) {
                $item_title = $recognized_data['dcterms:alternative']['value'];
            } else {
                $item_title = $_REQUEST['data_for_recognize_single'];
            }

            /** @var \Omeka\Form\ResourceForm $form */
            $form = $this->getForm(ResourceForm::class);
            $form
                ->setAttribute('action', $this->url()->fromRoute(null, [], true))
                ->setAttribute('enctype', 'multipart/form-data')
                ->setAttribute('id', 'add-item');

            $property_id = 1;
            $data = [
                'o:resource_template' => [
                    'o:id' => 1,
                ],
                'o:resource_class' => [
                    'o:id' => '',
                ],
                'dcterms:title' => [
                    '0' => [
                        'property_id' => $property_id,
                        'type' => 'literal',
                        '@language' => '',
                        '@value' => $item_title,
                        'is_public' => '1',
                    ],
                ],
                'o:thumbnail' => [
                    'o:id' => '',
                ],
                'o:media' => [
                    '0' => [
                        'o:is_public' => '1',
                        'dcterms:title' => [
                            '0' => [
                                '@value' => 'test2_media',
                                'property_id' => '1',
                                'type' => 'literal',
                            ],
                        ],
                        'ingest_url' => $url,
                        'o:ingester' => 'url',
                    ],
                ],
                'o:is_public' => '1',
            ];

            if (count($recognized_data) > 0) {
                foreach ($recognized_data as $key => $val) {
                    ++$property_id;
                    if ($key != 'dcterms:alternative') {
                        $term = explode(':', $key);

                        $term_item = $api
                            ->search('properties', ['vocabulary_id' => 1, 'local_name' => $term[1]])->getContent();

                        $data[$key] = [
                            '0' => [
                                'property_id' => $term_item[0]->id(),
                                'type' => 'literal',
                                '@language' => '',
                                '@value' => (string) $val['value'],
                                'is_public' => '1'
                            ]
                        ];
                    }
                }
            } else {
                $error = $this->translate('Field can’t map'); // @translate;
            }

            $form->setData($data);

            $new_item = $this->api($form)->create('items', $data);
            if ($new_item) {
                $data_for_recognize_row_id = $_REQUEST['data_for_recognize_row_id'];
                echo $data_for_recognize_row_id;
            }

            // $new_item_id = $new_item->getContent()->id();

            if ($delete_file_action ===  'yes') {
                $tempFile->delete();
            }
        }

        $this->layout()
            ->setTemplate('bulk-import-file/index/action-make-import')
            ->setVariable('data_for_recognize_row_id', $data_for_recognize_row_id)
            ->setVariable('error', $error);
    }

    /**
     * Set filesMaps as object (stdClass) for all Items with BulkImportFile Resource
     * (ex: public 'dcterms:created' => string '/x:xmpmeta/rdf:RDF/rdf:Description/@xmp:CreateDate')
     *
     * Set filesMapsArray as array with key "Item title" it's type of files
     * (ex: 'image/jpeg' => 'dcterms:created' => string '/x:xmpmeta/rdf:RDF/rdf:Description/@xmp:CreateDate')
     */
    protected function prepareFilesMaps()
    {
        $this->filesMaps = [];

        try {
            $resourceTemplate = $this->api()
                ->read('resource_templates', ['label' => 'BulkImportFile Resource'])
                ->getContent();
        } catch (\Exception $e) {
            $this->messenger()->addError('The required resource template "BulkImportFile Resource" has been removed or renamed.'); // @translate
            return;
        }

        $items = $this->api()
            ->search('items', ['resource_template_id' => $resourceTemplate->id()])
            ->getContent();

        $options = [];
        $options['viewName'] = 'common/item-resource-values';

        foreach ($items as $item) {
            $this->filesMaps[$item->id()] = json_decode($item->displayValues($options));

            $current_maps = (array) json_decode($item->displayValues($options));
            $current_maps['item_id'] = $item->id();
            if (isset($current_maps['dcterms:title'])) {
                $this->filesMapsArray[$current_maps['dcterms:title']] = $current_maps;
            }
        }
    }
}
