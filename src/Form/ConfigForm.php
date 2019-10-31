<?php
namespace SolrDragon\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    public function init()
    {

        $this->add([
            'name' => 'solrdragon_solr_server',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Solr Server Configuration', // @translate
                'info' => 'A Solr server is required to use this module', // @translate
            ],
        ]);
        $imageFieldset = $this->get('solrdragon_solr_server');

        $this->add([
            'name' => 'solrdragon_solr_server_ip',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Solr Server URL', // @translate
                'info' => $this->translate('This should be the full URL to your Solr Server instance.'), // @translate
            ],
            'attributes' => [
                'id' => 'solrdragon_solr_server_ip',
            ],
        ]);

        $this->add([
            'name' => 'solrdragon_solr_server_username',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Solr Server Username', // @translate
                'info' => $this->translate('If provided, SolrDragon will use Basic Authentication when interacting with your Solr server.'), // @translate
            ],
            'attributes' => [
                'id' => 'solrdragon_solr_server_username',
            ],
        ]);

        $this->add([
            'name' => 'solrdragon_solr_server_password',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Solr Server Password', // @translate
                'info' => $this->translate('If provided, SolrDragon will use Basic Authentication when interacting with your Solr server.'), // @translate
            ],
            'attributes' => [
                'id' => 'solrdragon_solr_server_password',
            ],
        ]);

        $this->add([
            'name'    => 'solrdragon_extractor',
            'type'    => Fieldset::class,
            'options' => [
                'label' => 'SolrDragon Text Extractor',
            ],
            'attributes' => [
                'id' => 'solrdragon-extractor-fieldset',
            ],
        ]);

        $adapterFieldset = $this->get('solrdragon_extractor');

        $this->add([
            'name'    => 'solrdragon_text_extractor',
            'type'    => Element\Select::class,
            'options' => [
                'label'         => 'Text Extractor: ',
                'value_options' => [
                    'none'              => 'Do not extract text',
                    'pdftotext'         => 'PDF to Text',
                    'googlevision'      => 'Google Vision API',
                    'microsoftvision'   => 'Microsoft Computer Vision API',
                ],
            ],
            'attributes' => [
                'id' => 'solrdragon-text-extractor',
            ],
        ]);

        $this->add([
            'name' => 'solrdragon_google_vision',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Google Vision API Configuration',
            ],
        ]);
        $googleVisionFieldset = $this->get('solrdragon_google_vision');

        $this->add([
            'name' => 'solrdragon_google_cloud_key',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Google Cloud Key', // @translate
                'info' => $this->translate('This is your Google API key with access to the Google Vision Cloud API'), // @translate
            ],
            'attributes' => [
                'id' => 'solrdragon_google_cloud_key',
            ],
        ]);

    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }

}
