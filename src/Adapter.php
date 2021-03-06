<?php

namespace SolrDragon;

use Zend\I18n\Translator\TranslatorInterface;
use Omeka\Api\Manager as ApiManager;
use Search\Adapter\AbstractAdapter;
use Search\Api\Representation\SearchIndexRepresentation;
use Solr\Form\ConfigFieldset;

class Adapter extends AbstractAdapter
{
    protected $api;
    protected $translator;

    public function __construct(ApiManager $api, TranslatorInterface $translator)
    {
        $this->api = $api;
        $this->translator = $translator;
    }

    public function getLabel()
    {
        return 'Solr';
    }

    public function getConfigFieldset()
    {
        $solrNodes = $this->api->search('solr_nodes')->getContent();

        return new ConfigFieldset(null, ['solrNodes' => $solrNodes]);
    }

    public function getIndexerClass()
    {
        return 'Solr\Indexer';
    }

    public function getQuerierClass()
    {
        return 'Solr\Querier';
    }

    public function getAvailableFacetFields(SearchIndexRepresentation $index)
    {
        return $this->getAvailableFields($index);
    }

    public function getAvailableSortFields(SearchIndexRepresentation $index)
    {
        $settings = $index->settings();
        $solrNodeId = $settings['adapter']['solr_node_id'];
        if (!$solrNodeId) {
            return [];
        }

        $sortFields = [
            'score desc' => [
                'name' => 'score desc',
                'label' => $this->translator->translate('Relevance'),
            ],
        ];

        $directionLabel = [
            'asc' => $this->translator->translate('Asc'),
            'desc' => $this->translator->translate('Desc'),
        ];

        $solrNode = $this->api->read('solr_nodes', $solrNodeId)->getContent();
        $schema = $solrNode->schema();
        $response = $this->api->search('solr_mappings', [
            'solr_node_id' => $solrNode->id(),
        ]);
        $mappings = $response->getContent();
        foreach ($mappings as $mapping) {
            $fieldName = $mapping->fieldName();
            $schemaField = $schema->getField($fieldName);
            if ($schemaField && !$schemaField->isMultivalued()) {
                foreach (['asc', 'desc'] as $direction) {
                    $name = $fieldName . ' ' . $direction;
                    $sortFields[$name] = [
                        'name' => $name,
                    ];
                }
            }
        }

        return $sortFields;
    }

    public function getAvailableFields(SearchIndexRepresentation $index)
    {
        $settings = $index->settings();
        $solrNodeId = $settings['adapter']['solr_node_id'];
        $response = $this->api->search('solr_mappings', [
            'solr_node_id' => $solrNodeId,
        ]);
        $mappings = $response->getContent();
        $fields = [];
        foreach ($mappings as $mapping) {
            $name = $mapping->fieldName();
            $facetFields[$name] = [
                'name' => $name,
            ];
        }

        return $facetFields;
    }
}