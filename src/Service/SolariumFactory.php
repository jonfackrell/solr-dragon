<?php
namespace SolrDragon\Service;

use Solarium\Client;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class SolariumFactory implements FactoryInterface
{

    private $settings;

    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $this->settings = $services->get('Omeka\Settings');
        return $this;
    }

    public function newClient($core){
        $solr_ip = $this->settings->get('solrdragon_solr_server_ip');
        $config = array(
            'endpoint' => array(
                'localhost' => array(
                    'host' => $solr_ip,
                    'port' => 8983,
                    'path' => '/',
                    'core' => $core,
                    // For Solr Cloud you need to provide a collection instead of core:
                    // 'collection' => 'techproducts',
                )
            )
        );
        // create a client instance
        return new Client($config);
    }

}