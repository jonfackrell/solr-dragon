<?php

namespace Solr\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Solr\Adapter;

class AdapterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $api = $container->get('Omeka\ApiManager');
        $translator = $container->get('MvcTranslator');

        $adapter = new Adapter($api, $translator);

        return $adapter;
    }
}