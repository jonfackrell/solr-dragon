<?php
namespace SolrDragon\Service\Extractor;

use SolrDragon\Extractor\GoogleVision;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class GoogleVisionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GoogleVision($services->get('Omeka\Settings'), $services->get('Omeka\Cli'));
    }
}