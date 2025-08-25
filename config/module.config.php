<?php declare(strict_types=1);

namespace BulkImportFiles;

use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],

    // FORM ELEMENTS (solo factories; se usi getForm(FQCN) non servono alias)
    'form_elements' => [
        'factories' => [
            \BulkImportFiles\Form\ImportForm::class => InvokableFactory::class,
            \BulkImportFiles\Form\SettingsForm::class => InvokableFactory::class,
        ],
        // Nessun 'aliases' per evitare cicli.
    ],

    // CONTROLLERS
    'controllers' => [
        'factories' => [
            \BulkImportFiles\Controller\Index::class => \BulkImportFiles\Service\Controller\IndexControllerFactory::class,
        ],
    ],

    // CONTROLLER PLUGINS
    'controller_plugins' => [
        'aliases' => [
            'mapData' => \BulkImportFiles\Mvc\Controller\Plugin\MapData::class,
            'extractDataFromPdf' => \BulkImportFiles\Mvc\Controller\Plugin\ExtractDataFromPdf::class,
            'extractStringFromFile' => \BulkImportFiles\Mvc\Controller\Plugin\ExtractStringFromFile::class,
            'extractStringToFile' => \BulkImportFiles\Mvc\Controller\Plugin\ExtractStringToFile::class,
        ],
        'factories' => [
            'mapData' => Service\ControllerPlugin\MapDataFactory::class,
            'extractDataFromPdf' => Service\ControllerPlugin\ExtractDataFromPdfFactory::class,
            \BulkImportFiles\Mvc\Controller\Plugin\MapData::class
                => \BulkImportFiles\Service\ControllerPlugin\MapDataFactory::class,
            \BulkImportFiles\Mvc\Controller\Plugin\ExtractDataFromPdf::class
                => \BulkImportFiles\Service\ControllerPlugin\ExtractDataFromPdfFactory::class,
            \BulkImportFiles\Mvc\Controller\Plugin\ExtractStringFromFile::class
                => InvokableFactory::class,
            \BulkImportFiles\Mvc\Controller\Plugin\ExtractStringToFile::class
                => InvokableFactory::class,
        ],

    ],

    // ROUTER
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'bulk-import-files' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/bulk-import-files',
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                'controller' => \BulkImportFiles\Controller\Index::class,
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkImportFiles\Controller',
                                        'controller' => \BulkImportFiles\Controller\Index::class,
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

    // NAVIGATION
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Bulk import files', // @translate
                'route' => 'admin/bulk-import-files',
                'resource' => \BulkImportFiles\Controller\Index::class,
                'privilege' => 'make-import',
                'class' => 'o-icon-install',
                'pages' => [
                    [
                        'label' => 'Make import', // @translate
                        'route' => 'admin/bulk-import-files',
                        'resource' => \BulkImportFiles\Controller\Index::class,
                        'privilege' => 'make-import',
                    ],
                    [
                        'label' => 'View mappings', // @translate
                        'route' => 'admin/bulk-import-files/default',
                        'action' => 'map-show',
                        'resource' => \BulkImportFiles\Controller\Index::class,
                        'privilege' => 'map-show',
                    ],
                    [
                        'label' => 'Create mappings', // @translate
                        'route' => 'admin/bulk-import-files/default',
                        'action' => 'map-edit',
                        'resource' => \BulkImportFiles\Controller\Index::class,
                        'privilege' => 'map-edit',
                    ],
                ],
            ],
        ],
    ],

    // TRANSLATOR
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
            'bulkimportfiles_local_path' => '',
        ],
    ],
];
