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
            'BulkImportFiles\Form\SettingsForm' => Service\Form\SettingsFormFactory::class,
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
            'mapData' => Service\ControllerPlugin\MapDataFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'bulkimportfiles' => [
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
                            'mapimport' => [
                                'type' => \Zend\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/map-import',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                        'controller' => 'Index',
                                        'action' => 'map-import',
                                    ],
                                ],
                            ],
                            'get_files' => [
                                'type' => \Zend\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/get-files',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                        'controller' => 'Index',
                                        'action' => 'get-files',
                                    ],
                                ],
                            ],
                            'save_options' => [
                                'type' => \Zend\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/save-options',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                        'controller' => 'Index',
                                        'action' => 'save-options',
                                    ],
                                ],
                            ],
                            'makeimport' => [
                                'type' => \Zend\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/make-import',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                        'controller' => 'Index',
                                        'action' => 'make-import',
                                    ],
                                ],
                            ],
                            'checkfolder' => [
                                'type' => \Zend\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/check-folder',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                        'controller' => 'Index',
                                        'action' => 'check-folder',
                                    ],
                                ],
                            ],
                            'processimport' => [
                                'type' => \Zend\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/process-import',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                        'controller' => 'Index',
                                        'action' => 'process-import',
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
                'route' => 'admin/bulkimportfiles',
                'resource' => 'BulkImportFiles\Controller\Index',
                'pages' => [
                    [
                        'label' => 'View mappings', // @translate
                        'route' => 'admin/bulkimportfiles',
                        'resource' => 'BulkImportFiles\Controller\Index',
                    ],
                    [
                        'label' => 'Create mappings', // @translate
                        'route' => 'admin/bulkimportfiles/mapimport',
                        'resource' => 'BulkImportFiles\Controller\Index',
                    ],
                    [
                        'label' => 'Process import', // @translate
                        'route' => 'admin/bulkimportfiles/makeimport',
                        'resource' => 'BulkImportFiles\Controller\Index',
                    ]
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
            'bulkimportfiles_mappings' => [
                'image/jpeg' => 'data/mapping/map_jpeg.csv',
                'pdf' => 'data/mapping/map_pdf.csv',
                'mp3' => 'data/mapping/map_mp3.csv'
            ],
        ],
    ]
];
