<?php
namespace BulkImportFiles;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'form_elements' => [
        'factories' => [
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
            'extractStringToFile' => Mvc\Controller\Plugin\ExtractStringToFile::class,
        ],
        'factories' => [
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
                'privilege' => 'make-import',
                'class' => 'o-icon-install',
                'pages' => [
                    [
                        'label' => 'Make import', // @translate
                        'route' => 'admin/bulk-import-files',
                        'resource' => 'BulkImportFiles\Controller\Index',
                        'privilege' => 'make-import',
                    ],
                    [
                        'label' => 'View mappings', // @translate
                        'route' => 'admin/bulk-import-files/default',
                        'action' => 'map-show',
                        'resource' => 'BulkImportFiles\Controller\Index',
                        'privilege' => 'map-show',
                    ],
                    [
                        'label' => 'Create mappings', // @translate
                        'route' => 'admin/bulk-import-files/default',
                        'action' => 'map-edit',
                        'resource' => 'BulkImportFiles\Controller\Index',
                        'privilege' => 'map-edit',
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
    ],
];
