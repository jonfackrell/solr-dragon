<?php
namespace SolrDragon\Service\Controller;

use SolrDragon\Controller\ItemController;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ItemControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ItemController(
            $services,
            $services->get('Omeka\Paginator'),
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\ApiAdapterManager')
        );
    }
}