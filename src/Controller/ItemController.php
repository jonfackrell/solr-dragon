<?php
namespace SolrDragon\Controller;

use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Interop\Container\ContainerInterface;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Omeka\Stdlib\Paginator;
use Omeka\View\Model\ApiJsonModel;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ItemController extends \Omeka\Controller\Site\ItemController
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

    public function searchAction()
    {
    }

    public function browseAction()
    {

        /*$site = $this->currentSite();

        $this->setBrowseDefaults('created');

        $view = new ViewModel;

        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();
        if ($this->siteSettings()->get('browse_attached_items', false)) {
            $query['site_attachments_only'] = true;
        }
        if ($itemSetId = $this->params('item-set-id')) {
            $itemSetResponse = $this->api()->read('item_sets', $itemSetId);
            $itemSet = $itemSetResponse->getContent();
            $view->setVariable('itemSet', $itemSet);
            $query['item_set_id'] = $itemSetId;
        }

        $response = $this->api()->search('items', $query);
        $this->paginator($response->getTotalResults());
        $items = $response->getContent();

        $view->setVariable('site', $site);
        $view->setVariable('items', $items);
        $view->setVariable('resources', $items);*/

        /*********************************************/

        $site = $this->currentSite();

        $view = new ViewModel;

        $client = $this->services->get('SolrDragon\Service\Solarium')->newClient('items');
        // get a select query instance
        $query = $client->createQuery($client::QUERY_SELECT);

        $q = $this->params()->fromQuery();

        if(isset($q['search']) && strlen($q['search']) > 0){
            $term = $q['search'];
            $query->setQuery("attr_text:$term");
        }else{
            $site = $this->currentSite();

            $this->setBrowseDefaults('created');

            $view = new ViewModel;

            $query = $this->params()->fromQuery();
            $query['site_id'] = $site->id();
            if ($this->siteSettings()->get('browse_attached_items', false)) {
                $query['site_attachments_only'] = true;
            }
            if ($itemSetId = $this->params('item-set-id')) {
                $itemSetResponse = $this->api()->read('item_sets', $itemSetId);
                $itemSet = $itemSetResponse->getContent();
                $view->setVariable('itemSet', $itemSet);
                $query['item_set_id'] = $itemSetId;
            }

            $response = $this->api()->search('items', $query);
            $this->paginator($response->getTotalResults());
            $items = $response->getContent();

            /*var_dump($items);
            die();*/

            $view->setVariable('site', $site);
            $view->setVariable('items', $items);
            $view->setVariable('resources', $items);
            return $view;
        }
        // this executes the query and returns the result
        $resultset = $client->execute($query);
        // get media ids
        $items = [];
        foreach ($resultset as $document) {
            $fields = $document->getFields();
            $items[] = $fields['id'];
        }

        if(empty($items)){
            var_dump('Nothing found');
            die();
            $request = new Request(Request::SEARCH, 'item');
            $response = new Response([]);
            $response->setRequest($request);

            $view->setVariable('site', $site);
            $view->setVariable('items', []);
            $view->setVariable('resources', []);
            return $view;
        }

        //$conn = $this->services->get('Omeka\Connection');
        $qb = $this->services->get('Omeka\EntityManager')->createQueryBuilder();

        $qb->select(array('Omeka\Entity\Item'))
            ->from('Omeka\Entity\Item', 'Omeka\Entity\Item')
            ->add('where', $qb->expr()->in('Omeka\Entity\Item.id', $items));

        // Before adding the ORDER BY clause, set a paginator responsible for
        // getting the total count. This optimization excludes the ORDER BY
        // clause from the count query, greatly speeding up response time.
        $countPaginator = new DoctrinePaginator($qb, false);
        $paginator = new DoctrinePaginator($qb, false);

        $adapter = $this->adapterManager->get('items');
        $entities = [];
        // Don't make the request if the LIMIT is set to zero. Useful if the
        // only information needed is total results.
        if ($qb->getMaxResults() || null === $qb->getMaxResults()) {
            foreach ($paginator as $entity) {
                if (is_array($entity)) {
                    // Remove non-entity columns added to the SELECT. You can use
                    // "AS HIDDEN {alias}" to avoid this condition.
                    $entity = $entity[0];
                }
                $entities[] = $adapter->getRepresentation($entity);
            }
        }

        $request = new Request(Request::SEARCH, 'item');
        $response = new Response($entities);
        $response->setTotalResults($countPaginator->count());

        $response->setRequest($request);

        $this->paginator($response->getTotalResults());
        $items = $response->getContent();
        /*var_dump($items);
        die();*/

        $view->setVariable('site', $site);
        $view->setVariable('items', $items);
        $view->setVariable('resources', $items);

        return $view;
    }
}
