<?php
namespace SolrDragon\Service\Controller;

use SolrDragon\Controller\IndexController;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $indexController = new IndexController($config);

        return $indexController;
    }
}