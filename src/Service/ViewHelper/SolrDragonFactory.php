<?php

namespace SolrDragon\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use SolrDragon\View\Helper\SolrDragon;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the SolrDragon view helper.
 */
class SolrDragonFactory implements FactoryInterface
{
    /**
     * Create and return the SolrDragon view helper
     *
     * @return SolrDragon
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $currentTheme = $serviceLocator->get('Omeka\Site\ThemeManager')
            ->getCurrentTheme();
        return new SolrDragon($currentTheme);
    }
}