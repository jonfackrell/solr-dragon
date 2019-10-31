<?php

namespace SolrDragon\Controller;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Interop\Container\ContainerInterface;
use Intervention\Image\ImageManagerStatic as Image;
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

class SearchController extends ApiController
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

        $client = $this->services->get('SolrDragon\Service\Solarium')->newClient('media');
        // get a select query instance
        $query = $client->createQuery($client::QUERY_SELECT);
        $id = $this->params()->fromRoute('id');
        $q = $this->params()->fromQuery();

        $query->createFilterQuery('item')->setQuery("item_id:$id");
        if(isset($q['search']) && strlen($q['search']) > 0){
            $term = $q['search'];
            $query->setQuery("attr_text:$term");
        }
        // this executes the query and returns the result
        $resultset = $client->execute($query);
        // get media ids
        $media = [];
        foreach ($resultset as $document) {
            $fields = $document->getFields();
            $media[] = $fields['id'];
        }

        if(empty($media)){
            $request = new Request(Request::SEARCH, 'media');
            $response = new Response([]);
            $response->setRequest($request);

            return new ApiJsonModel($response, $this->getViewOptions());
        }

        //$conn = $this->services->get('Omeka\Connection');
        $qb = $this->services->get('Omeka\EntityManager')->createQueryBuilder();

        $qb->select('m')
            ->from('Omeka\Entity\Media', 'm')
            ->add('where', $qb->expr()->in('m.id', $media));

        /*$qb->select(array('Omeka\Entity\Media'))
            ->from('media', 'media')
            ->add('where', $qb->expr()->in('media.id', $media));*/

        //dd($qb);

        // Before adding the ORDER BY clause, set a paginator responsible for
        // getting the total count. This optimization excludes the ORDER BY
        // clause from the count query, greatly speeding up response time.
        $countPaginator = new DoctrinePaginator($qb, false);
        $paginator = new DoctrinePaginator($qb, false);

        $adapter = $this->adapterManager->get('media');
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
                $temp = $adapter->getRepresentation($entity);
                $image = Image::make(OMEKA_PATH . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'original' . DIRECTORY_SEPARATOR . $temp->filename());
                $entity->setData(['width' => $image->width(), 'height' => $image->height()]);
                $entities[] = $entity;
            }
        }

        $request = new Request(Request::SEARCH, 'media');
        $response = new Response($entities);
        $response->setTotalResults($countPaginator->count());

        $response->setRequest($request);
        $adapter = $this->adapterManager->get($request->getResource());
        $this->api->finalize($adapter, $request, $response);


        return new ApiJsonModel($response, $this->getViewOptions());
    }
}