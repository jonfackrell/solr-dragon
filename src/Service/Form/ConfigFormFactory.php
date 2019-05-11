<?php
namespace SolrDragon\Service\Form;

use SolrDragon\Form\ConfigForm;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $translator = $services->get('MvcTranslator');
        $settings = $services->get('Omeka\Settings');

        $form = new ConfigForm(null, $options);
        $form->setTranslator($translator);

        return $form;
    }
}
