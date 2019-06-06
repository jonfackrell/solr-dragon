<?php
namespace SolrDragon\Service\Controller;

use SolrDragon\Controller\LabelController;
use SolrDragon\Controller\SearchController;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class LabelControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new LabelController(
            $services,
            $services->get('Omeka\Paginator'),
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\ApiAdapterManager')
        );
    }
}