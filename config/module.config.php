<?php
namespace BulkImportFiles;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ConfigForm::class => \Omeka\Form\Factory\InvokableFactory::class,
            Form\SettingsForm::class => \Omeka\Form\Factory\InvokableFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'BulkImportFiles\Controller\Index' => Service\Controller\IndexControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'extractStringFromFile' => Mvc\Controller\Plugin\ExtractStringFromFile::class,
        ],
        'factories' => [
            'extractDataFromPdf' => Service\ControllerPlugin\ExtractDataFromPdfFactory::class,
            'mapData' => Service\ControllerPlugin\MapDataFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'bulk-import-files' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/bulk-import-files',
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                        'controller' => 'Index',
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Bulk import files', // @translate
                'route' => 'admin/bulk-import-files',
                'resource' => 'BulkImportFiles\Controller\Index',
                'pages' => [
                    [
                        'label' => 'View mappings', // @translate
                        'route' => 'admin/bulk-import-files',
                        'resource' => 'BulkImportFiles\Controller\Index',
                    ],
                    [
                        'label' => 'Create mappings', // @translate
                        'route' => 'admin/bulk-import-files/default',
                        'action' => 'map-import',
                        'resource' => 'BulkImportFiles\Controller\Index',
                    ],
                    [
                        'label' => 'Make import', // @translate
                        'route' => 'admin/bulk-import-files/default',
                        'action' => 'make-import',
                        'resource' => 'BulkImportFiles\Controller\Index',
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'bulkimportfiles' => [
        'config' => [
            'bulkimportfiles_pdftk' => '',
            'bulkimportfiles_mappings' => [
                'image/jpeg' => 'data/mapping/map_jpeg.csv',
                'pdf' => 'data/mapping/map_pdf.csv',
                'mp3' => 'data/mapping/map_mp3.csv',
            ],
        ],
    ],
];
