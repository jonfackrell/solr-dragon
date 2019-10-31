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
    'form_elements' => [
        'factories' => [
            SolrDragon\Form\ConfigForm::class => SolrDragon\Service\Form\ConfigFormFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'SolrDragon\ExtractorManager' => SolrDragon\Service\Extractor\ManagerFactory::class,
            'SolrDragon\Service\Solarium' => SolrDragon\Service\SolariumFactory::class,
        ],
    ],
    'extract_text_extractors' => [
        'factories' => [
            'pdftotext' => SolrDragon\Service\Extractor\PdftotextFactory::class,
            'googlevision' => SolrDragon\Service\Extractor\GoogleVisionFactory::class,
            'microsoftvision' => SolrDragon\Service\Extractor\MicrosoftVisionFactory::class,
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
            'SolrDragon\Controller\Item' => SolrDragon\Service\Controller\ItemControllerFactory::class,
            'SolrDragon\Controller\Search' => SolrDragon\Service\Controller\SearchControllerFactory::class,
            'SolrDragon\Controller\Label' => SolrDragon\Service\Controller\LabelControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'resource' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:controller[/:action]',
                            'defaults' => [
                                '__NAMESPACE__' => 'SolrDragon\Controller',
                                'controller' => 'Item',
                                'action' => 'browse',
                            ],
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                        ],
                    ],
                    'resource-id' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:controller/:id[/:action]/solrdragon',
                            'defaults' => [
                                'action' => 'show',
                            ],
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id' => '\d+',
                            ],
                        ],
                    ],
                ],
            ],
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
                    'route' => '/s/:site-slug/:resourcename/:id/solrdragon',
                    'constraints' => [
                        'resourcename' => 'item|item\-set',
                        'id' => '\d+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'SolrDragon\Controller',
                        '__SITE__' => true,
                        'controller' => 'Viewer',
                        'action' => 'show',
                    ],
                ],
            ],
            'solrdragon_media_search' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/solrdragon/search/:resource/:id',
                    'constraints' => [
                        'resource' => '[a-zA-Z0-9_-]+',
                        'id' => '\d+'
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'SolrDragon\Controller',
                        '__API__' => true,
                        'controller' => 'Search',
                        'action' => 'show',
                    ],
                ],
            ],
            'solrdragon_label_search' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/solrdragon/labels/:id',
                    'constraints' => [
                        'id' => '\d+'
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'SolrDragon\Controller',
                        '__API__' => true,
                        'controller' => 'Label',
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
    'search_adapters' => [
        'factories' => [
            'solrdragon' => 'SolrDragon\Service\AdapterFactory',
        ],
    ],
    'solrdragon' => [
        'config' => [
            'solrdragon_solr_server_ip' => '',
            'solrdragon_solr_server_username' => '',
            'solrdragon_solr_server_password' => '',
            'solrdragon_text_extractor' => '',
            'solrdragon_google_cloud_key' => '',
        ],
    ],
];