<?php

namespace BulkImportFile;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            //Form\ConfigForm::class => Form\ConfigForm::class,
        ],
        'factories' => [
            //Form\ConfigFormSettings::class => Service\Form\ConfigFormFactory::class,
            'BulkImportFile\Form\SettingsForm' => Service\Form\SettingsFormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'BulkImportFile\Controller\Index' => Service\Controller\IndexControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'bulkimportfile' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/bulkimportfile',
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkImportFile\Controller',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'mapimport' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/mapimport',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFile\Controller',
                                        'controller' => 'Index',
                                        'action' => 'mapimport',
                                    ],
                                ],
                            ],
                            'getFiles' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/getfiles',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFile\Controller',
                                        'controller' => 'Index',
                                        'action' => 'getFiles',
                                    ],
                                ],
                            ],
                            'saveOption' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/saveoption',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFile\Controller',
                                        'controller' => 'Index',
                                        'action' => 'saveOption',
                                    ],
                                ],
                            ],
                            'makeimport' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/makeimport',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFile\Controller',
                                        'controller' => 'Index',
                                        'action' => 'makeimport',
                                    ],
                                ],
                            ],
                            'checkfolder' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/checkfolder',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFile\Controller',
                                        'controller' => 'Index',
                                        'action' => 'checkFolder',
                                    ],
                                ],
                            ],
                            'actionmakeimport' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/actionmakeimport',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFile\Controller',
                                        'controller' => 'Index',
                                        'action' => 'actionMakeImport',
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
                'label' => 'Bulk Import Files',
                'route' => 'admin/bulkimportfile',
                'resource' => 'BulkImportFile\Controller\Index',
                'pages' => [
                    [
                        'label' => 'View maps settings', // @translate
                        'route' => 'admin/bulkimportfile',
                        'resource' => 'BulkImportFile\Controller\Index',
                    ],
                    [
                        'label' => 'Create file Maps', // @translate
                        'route' => 'admin/bulkimportfile/mapimport',
                        'resource' => 'BulkImportFile\Controller\Index',
                    ],
                    [
                        'label' => 'Make Import', // @translate
                        'route' => 'admin/bulkimportfile/makeimport',
                        'resource' => 'BulkImportFile\Controller\Index',
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
    'bulkimportfile' => [
        'config' => [
            'bulkimportfile_maps_settings' => [
                'image/jpeg' => 'data/mapping/map_jpeg.csv',
                'pdf' => 'data/mapping/map_pdf.csv',
                'mp3' => 'data/mapping/map_mp3.csv'
            ],
        ],
    ]
];
