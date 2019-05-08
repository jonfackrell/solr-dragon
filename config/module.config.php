<?php
namespace SolrDragon;

return [
    'controllers' => [
        'factories' => [
            'SolrDragon\Controller\Index' => Service\Controller\IndexControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'elasticsearch' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/elasticsearch',
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
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'SolrDragon',
                'route' => 'admin/elasticsearch',
                'resource' => 'SolrDragon\Controller\Index',
                'pages' => [
                    [
                        'label' => 'Import', // @translate
                        'route' => 'admin/elasticsearch',
                        'resource' => 'SolrDragon\Controller\Index',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
];