<?php

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'solrDragon' => SolrDragon\Service\ViewHelper\SolrDragonFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'SolrDragon\ExtractorManager' => SolrDragon\Service\Extractor\ManagerFactory::class,
        ],
    ],
    'extract_text_extractors' => [
        'factories' => [
            'pdftotext' => SolrDragon\Service\Extractor\PdftotextFactory::class,
        ],
        'aliases' => [
            'application/pdf' => 'pdftotext',
        ],
    ],
    'controllers' => [
        'invokables' => [
            'SolrDragon\Controller\Viewer' => SolrDragon\Controller\ViewerController::class,
        ],
        'factories' => [
            'SolrDragon\Controller\Index' => SolrDragon\Service\Controller\IndexControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'solrdragon' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/solrdragon',
                            'defaults' => [
                                '__NAMESPACE__' => 'SolrDragon\Controller',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                    ],
                ],
            ],
            'solrdragon_viewer' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/:resourcename/:id/solrdragon',
                    'constraints' => [
                        'resourcename' => 'item|item\-set',
                        'id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'SolrDragon\Controller',
                        'controller' => 'Viewer',
                        'action' => 'show',
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'SolrDragon',
                'route' => 'admin/solrdragon',
                'resource' => 'SolrDragon\Controller\Index',
                'pages' => [
                    [
                        'label' => 'Import', // @translate
                        'route' => 'admin/solrdragon',
                        'resource' => 'SolrDragon\Controller\Index',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
];