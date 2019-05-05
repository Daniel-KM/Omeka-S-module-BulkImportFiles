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
     * Mapping by media type like 'dcterms:created' => ['jpg/exif/IFD0/DateTime'].
     *
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
        $view->setVariable('filesMaps', $this->filesMaps);
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
            $media_type = $file['type'];
            $recognized_data = '';
            $errors = '';
            $this->parsed_data = '';

            if (isset($this->filesMapsArray[$media_type])) {
                $file['item_id'] = $this->filesMapsArray[$media_type]['item_id'];

                switch ($media_type) {
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

                        $filesMapsArray = $this->filesMapsArray[$media_type];

                        foreach ($filesMapsArray as $key => $val) {
                            if (is_array($val)) {
                                foreach ($val as $v) {
                                    $filesMaps = explode('/', $v);

                                    $file_fields_source = $file_source;
                                    foreach ($filesMaps as $fval) {
                                        if (isset($file_fields_source[$fval])) {
                                            $file_fields_source = $file_fields_source[$fval];
                                        }
                                    }

                                    if (!is_array($file_fields_source)) {
                                        $recognized_data[$key][] = [
                                            'field' => $v,
                                            'value' => $file_fields_source,
                                        ];
                                    }
                                }
                            } else {
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

    public function saveOptionsAction()
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
                ->read('resource_templates', ['label' => 'Bulk import files'])
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
            ->setTemplate('bulk-import-files/index/save-options')
            ->setVariable('request', $request);
    }

    public function checkFolderAction()
    {
        $this->prepareFilesMaps();

        $files_data = [];
        $total_files = 0;
        $total_files_can_recognized = 0;
        $error = '';

        if ((isset($_REQUEST['folder'])) && ($_REQUEST['folder'] != '')) {
            if (file_exists($_REQUEST['folder'])) {
                $files = array_diff(scandir($_REQUEST['folder']), ['.', '..']);

                $file_path = $_REQUEST['folder'] . '/';

                foreach ($files as $file) {
                    $getId3 = new GetId3();

                    $file_source = $getId3
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

        $data_for_recognize_row_id = '';
        $error = '';

        $config = $this->services->get('Config');
        $baseUri = $config['file_store']['local']['base_uri'];
        if (!$baseUri) {
            $helpers = $this->services->get('ViewHelperManager');
            $serverUrlHelper = $helpers->get('ServerUrl');
            $basePathHelper = $helpers->get('BasePath');
            $baseUri = $serverUrlHelper($basePathHelper('files'));
        }

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
            $url = $baseUri . '/original/' . $tempFile->getStorageId() . '.' . $tempFile->getExtension();

            $getId3 = new GetId3();

            $file_source = $getId3
                ->setOptionMD5Data(true)
                ->setOptionMD5DataSource(true)
                ->setEncoding('UTF-8')
                ->analyze($full_file_path);

            $media_type = 'undefined';
            if (isset($file_source['mime_type'])) {
                $media_type = $file_source['mime_type'];
            }

            if (!isset($this->filesMapsArray[$media_type])) {
                $this->layout()
                    ->setTemplate('bulk-import-files/index/process-import')
                    ->setVariable('data_for_recognize_row_id', $data_for_recognize_row_id)
                    ->setVariable('error', $this->translate('Mime type not managed.'));
                return;
            }

            $filesMapsArray = $this->filesMapsArray[$media_type];
            unset($filesMapsArray['media_type']);

            $metadata = $this->extractStringFromFile($full_file_path, '<x:xmpmeta', '</x:xmpmeta>');

            if ($metadata) {
                unset($filesMapsArray['item_id']);
                $data = $this->mapData($metadata, $filesMapsArray);
                $data += [
                    'o:resource_template' => [
                        'o:id' => '',
                    ],
                    'o:resource_class' => [
                        'o:id' => '',
                    ],
                    'o:thumbnail' => [
                        'o:id' => '',
                    ],
                    'o:media' => [
                        '0' => [
                            'o:is_public' => '1',
                            'ingest_url' => $url,
                            'o:ingester' => 'url',
                        ],
                    ],
                    'o:is_public' => '1',
                ];

                if (!isset($data['dcterms:title'][0])) {
                    $item_title = $_REQUEST['data_for_recognize_single'];
                    $data['dcterms:title'] = [
                        [
                            'property_id' => 1,
                            'type' => 'literal',
                            '@language' => '',
                            '@value' => $item_title,
                            'is_public' => '1',
                        ],
                    ];
                }
            } else {
                // Try via getid3.
                $result = $this->mapDataFromGetId3($getId3, $file_source, $url, $filesMapsArray);
                $data = $result['data'];
                $error = $result['error'];
            }

            /** @var \Omeka\Form\ResourceForm $form */
            $form = $this->getForm(ResourceForm::class);
            $form
                ->setAttribute('action', $this->url()->fromRoute(null, [], true))
                ->setAttribute('enctype', 'multipart/form-data')
                ->setAttribute('id', 'add-item');

            $form->setData($data);
            $new_item = $this->api($form)->create('items', $data);

            if ($new_item) {
                $data_for_recognize_row_id = $_REQUEST['data_for_recognize_row_id'];
                // echo $data_for_recognize_row_id;
            }

            // $new_item_id = $new_item->getContent()->id();

            if ($delete_file_action ===  'yes') {
                $tempFile->delete();
            }
        }

        $this->layout()
            ->setTemplate('bulk-import-files/index/process-import')
            ->setVariable('data_for_recognize_row_id', $data_for_recognize_row_id)
            ->setVariable('error', $error);
    }

    protected function mapDataFromGetId3(GetID3 $getid3, $file_source, $url, array $filesMapsArray)
    {
        $api = $this->api();
        $recognized_data = [];
        $error = '';

        foreach ($filesMapsArray as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $v) {
                    $filesMaps = explode('/', $v);

                    $file_fields_source = $file_source;
                    foreach ($filesMaps as $fval) {
                        if (isset($file_fields_source[$fval])) {
                            $file_fields_source = $file_fields_source[$fval];
                        }
                    }

                    if (!is_array($file_fields_source)) {
                        $recognized_data[$key][] = [
                            'field' => $v,
                            'value' => $file_fields_source,
                        ];
                    }
                }
            } else {
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
        }

        // Check for item title in "dcterms:title". If not, set item
        // title as file name.
        if (isset($recognized_data['dcterms:title'][0])) {
            $item_title = $recognized_data['dcterms:title'][0]['value'];
        } else {
            $item_title = $_REQUEST['data_for_recognize_single'];
        }

        $data = [
            'o:resource_template' => [
                'o:id' => 1,
            ],
            'o:resource_class' => [
                'o:id' => '',
            ],
            'dcterms:title' => [
                [
                    'property_id' => 1,
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
                    'ingest_url' => $url,
                    'o:ingester' => 'url',
                ],
            ],
            'o:is_public' => '1',
        ];

        if (count($recognized_data) > 0) {
            foreach ($recognized_data as $term => $val) {
                if (!is_array($val)) {
                    continue;
                }
                $property = $api->searchOne('properties', ['term' => $term])->getContent();
                foreach ($val as $v) {
                    $data[$key][] = [
                        'property_id' => $property->id(),
                        'type' => 'literal',
                        '@language' => '',
                        '@value' => (string) $v['value'],
                        'is_public' => '1'
                    ];
                }
            }
        } else {
            $error = $this->translate('Field can’t map'); // @translate;
        }

        return [
            'data' => $data,
            'error' => $error,
        ];
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
        $vocabularies = $this->api()->search('vocabularies')->getContent();
        foreach ($vocabularies as $vocabulary) {
            foreach ($vocabulary->properties() as $property) {
                $result[] = $property->term();
            }
        }
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

        try {
            $resourceTemplate = $this->api()
                ->read('resource_templates', ['label' => 'Bulk import files'])
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
                unset($current_maps['dcterms:title'][0]);
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
