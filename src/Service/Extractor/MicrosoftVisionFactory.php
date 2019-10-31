<?php
namespace SolrDragon\Service\Extractor;

use SolrDragon\Extractor\MicrosoftVision;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class MicrosoftVisionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MicrosoftVision($services->get('Omeka\Cli'));
    }
}