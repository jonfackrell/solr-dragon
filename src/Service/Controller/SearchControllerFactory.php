<?php
namespace SolrDragon\Service\Controller;

use SolrDragon\Controller\SearchController;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class SearchControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchController(
            $services,
            $services->get('Omeka\Paginator'),
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\ApiAdapterManager')
        );
    }
}