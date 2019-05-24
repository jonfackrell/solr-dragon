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
use Solarium\Client;
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
        $config = array(
            'endpoint' => array(
                'localhost' => array(
                    'host' => 'http://elasticsearch.lib-host.com',
                    'port' => 8983,
                    'path' => '/',
                    'core' => 'words',
                    // For Solr Cloud you need to provide a collection instead of core:
                    // 'collection' => 'techproducts',
                )
            )
        );
        // create a client instance
        $client = new Client($config);

        //$conn = $this->services->get('Omeka\Connection');
        $qb = $this->services->get('Omeka\EntityManager')->createQueryBuilder();

        $qb->select(array('Omeka\Entity\Media'))
            ->from('Omeka\Entity\Media', 'Omeka\Entity\Media')
            ->add('where', $qb->expr()->in('Omeka\Entity\Media.id', [4,5]));

        // Before adding the ORDER BY clause, set a paginator responsible for
        // getting the total count. This optimization excludes the ORDER BY
        // clause from the count query, greatly speeding up response time.
        $countPaginator = new DoctrinePaginator($qb, false);
        $paginator = new DoctrinePaginator($qb, false);

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
                $entities[] = $entity;
            }
        }

        $request = new Request(Request::SEARCH, 'media');
        $response = new Response($entities);
        $response->setTotalResults($countPaginator->count());

        $response->setRequest($request);
        $adapter = $this->adapterManager->get($request->getResource());
        $this->api->finalize($adapter, $request, $response);


        //var_dump($medias);
        //die();

        /*$this->setBrowseDefaults('id', 'asc');
        $resource = $this->params()->fromRoute('resource');
        $query = $this->params()->fromQuery();
        $response = $this->api->search($resource, $query);

        $this->paginator->setCurrentPage($query['page']);
        $this->paginator->setTotalCount($response->getTotalResults());

        // Add Link header for pagination.
        $links = [];
        $pages = [
            'first' => 1,
            'prev' => $this->paginator->getPreviousPage(),
            'next' => $this->paginator->getNextPage(),
            'last' => $this->paginator->getPageCount(),
        ];
        foreach ($pages as $rel => $page) {
            if ($page) {
                $query['page'] = $page;
                $url = $this->url()->fromRoute(null, [],
                    ['query' => $query, 'force_canonical' => true], true);
                $links[] = sprintf('<%s>; rel="%s"', $url, $rel);
            }
        }

        $this->getResponse()->getHeaders()
            ->addHeaderLine('Link', implode(', ', $links));*/
        /*var_dump($response);
        die();*/
        return new ApiJsonModel($response, $this->getViewOptions());
    }
}