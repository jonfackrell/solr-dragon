<?php

namespace SolrDragon\Controller;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Interop\Container\ContainerInterface;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Omeka\Controller\ApiController;
use Omeka\Stdlib\Paginator;
use Omeka\View\Model\ApiJsonModel;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;

class LabelController extends ApiController
{

    /**
     * @param Paginator $paginator
     */
    public function __construct(ContainerInterface $services, Paginator $paginator, ApiManager $api, AdapterManager $adapterManager)
    {
        $this->services = $services;
        $this->paginator = $paginator;
        $this->api = $api;
        $this->adapterManager = $adapterManager;
    }
    /**
     * Forward to the 'view' action
     *
     * @see self::showAction()
     */
    public function indexAction()
    {
        $this->forward('show');
    }

    public function showAction()
    {

        $client = $this->services->get('SolrDragon\Service\Solarium')->newClient('words');
        // get a select query instance
        $query = $client->createQuery($client::QUERY_SELECT);
        $id = $this->params()->fromRoute('id');
        $q = $this->params()->fromQuery();
        $term = $q['search'];
        $query->createFilterQuery('media')->setQuery("media_id:$id");
        $query->setQuery("attr_text:$term");
        // this executes the query and returns the result
        $resultset = $client->execute($query);
        // get word coordinates
        $media = [];
        foreach ($resultset as $document) {
            $fields = $document->getFields();
            $media[] = [
                'media_id' => $fields['media_id'],
                /*'x' => $fields['x'],
                'y' => $fields['y'],
                'height' => $fields['height'],
                'width' => $fields['width'],*/
            ];
        }

        $request = new Request(Request::SEARCH, 'media');
        $response = new Response($media);
        $response->setRequest($request);

        return new ApiJsonModel($response, $this->getViewOptions());
    }
}