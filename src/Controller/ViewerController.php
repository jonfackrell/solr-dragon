<?php
namespace SolrDragon\Controller;

use Omeka\Mvc\Exception\NotFoundException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ViewerController extends AbstractActionController
{
    /**
     * Forward to the 'view' action
     *
     * @see self::playAction()
     */
    public function indexAction()
    {
        $this->forward('show');
    }

    public function showAction()
    {
        $id = $this->params('id');
        if (empty($id)) {
            throw new NotFoundException;
        }

        // Map iiif resources with Omeka Classic and Omeka S records.
        $matchingResources = [
            'item' => 'items',
            'items' => 'items',
            'item-set' => 'item_sets',
            'item-sets' => 'item_sets',
            'item_set' => 'item_sets',
            'item_sets' => 'item_sets',
            'collection' => 'item_sets',
            'collections' => 'item_sets',
        ];
        $resourceName = $this->params('resourcename');
        if (!isset($matchingResources[$resourceName])) {
            throw new NotFoundException;
        }
        $resourceName = $matchingResources[$resourceName];

        $response = $this->api()->read($resourceName, $id);
        $resource = $response->getContent();
        if (empty($resource)) {
            throw new NotFoundException;
        }

        $view = new ViewModel;
        $view->setVariable('resource', $resource);

        return $view;
    }
}