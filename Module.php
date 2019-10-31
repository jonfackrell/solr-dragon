<?php
namespace SolrDragon;

use Doctrine\Common\Collections\Criteria;
use Omeka\Entity\Item;
use Omeka\Entity\Media;
use Omeka\Entity\Property;
use Omeka\Entity\Resource;
use Omeka\Entity\Value;
use Omeka\Stdlib\Cli;
use SolrDragon\Controller\SearchController;
use SolrDragon\Form\ConfigForm;
use Omeka\Module\AbstractModule;
use Spatie\PdfToImage\Pdf;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractController;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{

    /**
     * Text property cache
     *
     * @var Omeka\Entity\Property|false
     */
    protected $textProperty;


    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services)
    {
        // Import the SolrDragon vocabulary if it doesn't already exist.
        $api = $services->get('Omeka\ApiManager');
        $response = $api->search('vocabularies', [
            'namespace_uri' => 'http://omeka.org/s/vocabs/o-module-solrdragon#',
            'limit' => 0,
        ]);
        if (0 === $response->getTotalResults()) {
            $importer = $services->get('Omeka\RdfImporter');
            $importer->import(
                'file',
                [
                    'o:namespace_uri' => 'http://omeka.org/s/vocabs/o-module-solrdragon#',
                    'o:prefix' => 'solrdragon',
                    'o:label' => 'SolrDragon Text',
                    'o:comment' =>  null,
                ],
                [
                    'file' => __DIR__ . '/vocabs/solrdragon.n3',
                    'format' => 'turtle',
                ]
            );
        }
    }

    /**
     * Get this module's configuration form.
     *
     * @param ViewModel $view
     * @return string
     */
    public function getConfigForm(PhpRenderer $view)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $extractors = $this->getServiceLocator()->get('SolrDragon\ExtractorManager');

        $data = [];
        $defaultSettings = $this->getDefaultSettings();

        foreach ($defaultSettings as $name => $value) {
            $data[$name] = $settings->get($name, $value);
        }

        $html = '
        <table class="tablesaw tablesaw-stack">
            <thead>
            <tr>
                <th>' . $view->translate('Extractor') . '</th>
                <th>' . $view->translate('Available') . '</th>
            </tr>
            </thead>
            <tbody>';
        $extractor = $extractors->get('pdftotext');
        $isAvailable = $extractor->isAvailable()
            ? sprintf('<span style="color: green;">%s</span>', $view->translate('Yes'))
            : sprintf('<span style="color: red;">%s</span>', $view->translate('No'));
        $html .= sprintf('
        <tr>
            <td>%s</td>
            <td>%s</td>
        </tr>', 'pdftotext', $isAvailable);


        $html .= '
            </tbody>
        </table>';

        $form->init();
        $form->setData($data);
        $html .= $view->formCollection($form);

        return $html;
    }

    /**
     * Handle this module's configuration form.
     *
     * @param AbstractController $controller
     * @return bool False if there was an error during handling
     */
    public function handleConfigForm(AbstractController $controller)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        $form = $serviceLocator->get('FormElementManager')->get(ConfigForm::class);

        $params = $controller->getRequest()->getPost();
        $form->init();
        $form->setData($params);

        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();
        $logger = $this->getServiceLocator()->get('Omeka\Logger');
        $logger->debug($params);
        $defaultSettings = $this->getDefaultSettings();
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $defaultSettings = $this->getDefaultSettings();
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    private function getDefaultSettings($key = 'config')
    {
        $serviceLocator = $this->getServiceLocator();
        // TODO Fix so that configs are actually grabbed and the module can be deleted if desired
        $config = $serviceLocator->get('Config');
        return $config[strtolower(__NAMESPACE__)][$key];
    }

    /**
     * @todo Use form methods to populate.
     * @param \Omeka\Settings\SettingsInterface $settings
     * @param array$defaultSettings
     * @return array
     */
    protected function prepareDataToPopulate(\Omeka\Settings\SettingsInterface $settings, array $defaultSettings)
    {
        $data = [];
        foreach ($defaultSettings as $name => $value) {
            $val = $settings->get($name, $value);
            if (is_array($value)) {
                $val = is_array($val) ? implode(PHP_EOL, $val) : $val;
            }
            $data[$name] = $val;
        }
        return $data;
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(
            null,
            [
                'SolrDragon\Controller\Search',
                'SolrDragon\Controller\Label',
            ]
        );

        require __DIR__.'/vendor/autoload.php'; // Add autoloader for module-specific requirements
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        /**
         * Before ingesting a media file, extract its text and set it to the
         * media. This will only happen when creating the media.
         */
        $sharedEventManager->attach(
            '*',
            'media.ingest_file.pre',
            [$this, 'processIngestedFile']
        );

        $sharedEventManager->attach(
            'Omeka\Entity\Media',
            'entity.persist.post',
            [$this, 'processNewMedia']
        );

        $sharedEventManager->attach(
            'Omeka\Entity\Item',
            'entity.persist.post',
            [$this, 'processUpdatedItem']
        );

        $sharedEventManager->attach(
            'Omeka\Entity\Item',
            'entity.update.post',
            [$this, 'processUpdatedItem']
        );

        $sharedEventManager->attach(
            'Omeka\Entity\Item',
            'entity.remove.pre',
            [$this, 'processDeletedItem']
        );

        $sharedEventManager->attach(
            'Omeka\Entity\Media',
            'entity.remove.pre',
            [$this, 'processDeletedMedia']
        );

        // TODO: This is not triggering
       /* $sharedEventManager->attach(
            'Omeka\Entity\Media',
            'entity.update.post',
            function (Event $event) {
                $this->addMediaToSolrIndex($event->getTarget()->getItem(), $event->getTarget(), 'Test');
            }
        );*/

    }

    public function processIngestedFile(Event $event) {

        // Get module settings
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $directory = $settings->get('file_sideload_directory');
        $extractorName = $settings->get('solrdragon_text_extractor');

        // Access the uploaded file passed through with the event
        $tempFile = $event->getParam('tempFile');

        if($tempFile->getExtension() == 'pdf'){
            // Create a new item subdirectory in the sideload directory
            $itemDirectory = $directory.DIRECTORY_SEPARATOR.$event->getTarget()->getItem()->getId();
            $pdfDirectory = DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR;
            $pngDirectory = DIRECTORY_SEPARATOR.'png'.DIRECTORY_SEPARATOR;
            $jsonDirectory = DIRECTORY_SEPARATOR.'json'.DIRECTORY_SEPARATOR;
            $fileDirectory = $tempFile->getTempPath();

            if (!is_dir($itemDirectory)) {
                mkdir($itemDirectory, 0700);
            }

            // Create new Pdf object
            $pdf = new Pdf($tempFile->getTempPath());
            //$pdfUtil = new PdfUtil($tempFile->getTempPath());
            if (!is_dir($itemDirectory.$pdfDirectory)) {
                mkdir($itemDirectory.$pdfDirectory, 0700);
            }
            if (!is_dir($itemDirectory.$pngDirectory)) {
                mkdir($itemDirectory.$pngDirectory, 0700);
            }
            if (!is_dir($itemDirectory.$jsonDirectory)) {
                mkdir($itemDirectory.$jsonDirectory, 0700);
            }

            $pdf->setOutputFormat('png')->saveAllPagesAsImages($itemDirectory.$pngDirectory, str_replace('.pdf', '', $tempFile->getSourceName()));

            // TODO: Determine which extractor to use
            //        - If pdftotext then pass the pdf path in
            //        - If Google/Microsoft, pass multiple png files
            //        - Returned array is an array of pages -> words that will be used to store json files

            if($extractorName == 'googlevision' || $extractorName == 'microsoftvision'){
                $fileDirectory = $itemDirectory.$pngDirectory;
            }

            $array = $this->extractText($fileDirectory, $extractorName);

            foreach($array as $key => $page){
                file_put_contents($itemDirectory.$jsonDirectory.str_replace('.pdf', '', $tempFile->getSourceName()).($key+1).'.json', json_encode($page));
            }

            // TODO: This needs to be removed or turned off
            $logger = $this->getServiceLocator()->get('Omeka\Logger');
            $logger->info($array);

        }

    }

    public function processDeletedItem(Event $event) {
        $this->removeItemFromSolrIndex($event->getTarget());
    }

    public function processDeletedMedia(Event $event) {
        $this->removeWordsFromSolrIndex($event->getTarget());
        $this->removeMediaFromSolrIndex($event->getTarget());
    }

    public function processUpdatedItem(Event $event) {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $itemDirectory = $settings->get('file_sideload_directory').DIRECTORY_SEPARATOR.$event->getTarget()->getId();
        $pdfDirectory = DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR;
        $pngDirectory = DIRECTORY_SEPARATOR.'png'.DIRECTORY_SEPARATOR;
        $jsonDirectory = DIRECTORY_SEPARATOR.'json'.DIRECTORY_SEPARATOR;

        // After the Item has been saved, we can now attach the the new png image as a new media object
        if (is_dir($itemDirectory.$pngDirectory)) {
            $jsonFiles = [];
            $dir = new \SplFileInfo(realpath($itemDirectory.$pngDirectory));
            $iterator = new \DirectoryIterator($dir);
            foreach ($iterator as $file) {
                if ($this->verifyFile($itemDirectory.$pngDirectory, $file)) {
                    // Create new Media object and store it
                    $response = $api->create('media', [
                        "o:ingester" => "sideload",
                        "o:source" => $file->getFilename(),
                        "ingest_filename" => $itemDirectory.$pngDirectory.$file->getFilename(),
                        "o:item" => [
                            "o:id" => $event->getTarget()->getId()
                        ]
                    ], []
                    );

                    $media = $response->getContent();
                    $jsonFiles[$media->id()] = str_replace('.png', '.json', $file->getFilename());
                }
            }

            $itemMediaText = [];
            foreach($jsonFiles as $key => $file){
                $json = json_decode(file_get_contents($itemDirectory.$jsonDirectory.$file), true);
                $words = array_column($json['words'], 'text');
                $mediaText = implode(' ', $words);

                $this->addMediaToSolrIndex($event->getTarget()->getId(), $key, $mediaText);
                $this->addWordsToSolrIndex($event->getTarget()->getId(), $key, $json['words']);
                $itemMediaText[] = $mediaText;
            }


            // Delete the temporary item subdirectory from the sideload directory
            rmdir($itemDirectory);
        }

        $this->addItemToSolrIndex($event->getTarget(), $itemMediaText);
    }

    public function processNewMedia(Event $event){
        $text = [];
        if($event->getTarget()->getRenderer() == 'file'){

        }
        return $text;
    }

    /**
     * Extract text from a file.
     *
     * @param string $filePath
     * @param string $mediaType
     * @param array $options
     * @return string|false
     */
    public function extractText($filePath, $extractorName, array $options = [])
    {
        /*if (!@is_file($filePath)) {
            // The file doesn't exist.
            return false;
        }*/

        // TODO: Modify this piece of code to choose text extractor
        $extractors = $this->getServiceLocator()->get('SolrDragon\ExtractorManager');

        try {
            $extractor = $extractors->get($extractorName);
        } catch (ServiceNotFoundException $e) {
            // No extractor assigned to the media type.
            return false;
        }
        if (!$extractor->isAvailable()) {
            // The extractor is unavailable.
            return false;
        }
        // extract() should return false if it cannot extract text.
        return $extractor->extract($filePath, $options);
    }

    /**
     * Set extracted text to a media.
     *
     * @param string $filePath
     * @param Media $media
     * @param Property $textProperty
     * @param string $mediaType
     * @return null|false
     */
    public function setTextToMedia($filePath, Media $media, Property $textProperty, $mediaType = null)
    {
        if (null === $mediaType) {
            // Fall back on the media type set to the media.
            $mediaType = $media->getMediaType();
        }
        $text = $this->extractText($filePath, $mediaType);
        if (false === $text) {
            // Could not extract text from the file.
            return false;
        }
        $text = trim($text);
        $this->setTextToTextProperty($media, $textProperty, ('' === $text) ? null : $text);
    }
    /**
     * Set extracted text to an item.
     *
     * There are three actions this method can perform:
     *
     * - default: aggregates text from child media and set it to the item.
     * - refresh: same as default but (re)extracts text from files first.
     * - clear: clears all extracted text from item and child media.
     *
     * @param Item $item
     * @param Property $textProperty
     * @param string $action default|refresh|clear
     */
    public function setTextToItem(Item $item, Property $textProperty, $action = 'default')
    {
        $store = $this->getServiceLocator()->get('Omeka\File\Store');
        $itemTexts = [];
        $itemMedia = $item->getMedia();
        // Order by position in case the position was changed on this request.
        $criteria = Criteria::create()->orderBy(['position' => Criteria::ASC]);
        foreach ($itemMedia->matching($criteria) as $media) {
            // Files must be stored locally to refresh extracted text.
            if (('refresh' === $action) && ($store instanceof Local)) {
                $filePath = $store->getLocalPath(sprintf('original/%s', $media->getFilename()));
                $this->setTextToMedia($filePath, $media, $textProperty);
            }
            $mediaValues = $media->getValues();
            $criteria = Criteria::create()
                ->where(Criteria::expr()->eq('property', $textProperty))
                ->andWhere(Criteria::expr()->eq('type', 'literal'));
            foreach($mediaValues->matching($criteria) as $mediaValueTextProperty) {
                if ('clear' === $action) {
                    $mediaValues->removeElement($mediaValueTextProperty);
                } else {
                    $itemTexts[] = $mediaValueTextProperty->getValue();
                }
            }
        }
        $itemText = trim(implode(PHP_EOL, $itemTexts));
        $this->setTextToTextProperty($item, $textProperty, ('' === $itemText) ? null : $itemText);
    }

    /**
     * Set text as a text property value of a resource.
     *
     * Clears all existing text property values from the resource before setting
     * the value. Pass anything but a string to $text to just clear the values.
     *
     * @param Resource $resource
     * @param Property $textProperty
     * @param string $text
     */
    public function setTextToTextProperty(Resource $resource, Property $textProperty, $text)
    {
        // Clear values.
        $criteria = Criteria::create()->where(Criteria::expr()->eq('property', $textProperty));
        $resourceValues = $resource->getValues();
        foreach ($resourceValues->matching($criteria) as $resourceValueTextProperty) {
            $resourceValues->removeElement($resourceValueTextProperty);
        }
        // Create and add the value.
        if (is_string($text)) {
            $value = new Value;
            $value->setResource($resource);
            $value->setType('literal');
            $value->setProperty($textProperty);
            $value->setValue($text);
            $resourceValues->add($value);
        }
    }

    /**
     * Send the item to solr for indexing.
     *
     * Either creates a new document or updates an existing one.
     *
     * @param Item $item
     * @param string $text
     */
    public function addItemToSolrIndex($item, $text)
    {
        $client = $this->getServiceLocator()->get('SolrDragon\Service\Solarium')->newClient('items');

        if(!is_array($text)){
            $text = [$text];
        }

        // get an update query instance
        $update = $client->createUpdate();

        // create a new document for the data
        $doc = $update->createDocument();
        $doc->id = $item->getId();

        $metadata = [];
        foreach($item->getValues() as $key => $value){
            $metadata[] = $value->getValue();
        }

        $doc->attr_metadata = $metadata;

        foreach ($text as $value){
            $doc->addField('attr_text', $value);
        }
        //$doc->attr_text = implode(' ', $text);
        // add the documents and a commit command to the update query
        $update->addDocuments(array($doc));
        $update->addCommit();

        // this executes the query and returns the result
        $result = $client->update($update);

        return $result;
    }

    /**
     * Remove Item from Solr index.
     *
     * Either creates a new document or updates an existing one.
     *
     * @param Item $item
     */
    public function removeItemFromSolrIndex($item)
    {
        $client = $this->getServiceLocator()->get('SolrDragon\Service\Solarium')->newClient('items');

        // get an update query instance
        $update = $client->createUpdate();

        // create a new document for the data
        $update->addDeleteQuery('id:'.$item->getId());
        $update->addCommit();

        // this executes the query and returns the result
        $result = $client->update($update);

        return $result;
    }

    /**
     * Send the media to solr for indexing.
     *
     * Either creates a new document or updates an existing one.
     *
     * @param Item $item
     * @param Media $media
     * @param string $text
     */
    public function addMediaToSolrIndex($item, $media, $text)
    {
        $client = $this->getServiceLocator()->get('SolrDragon\Service\Solarium')->newClient('media');

        // get an update query instance
        $update = $client->createUpdate();

        // create a new document for the data
        $doc = $update->createDocument();
        $doc->id = $media;
        $doc->item_id = $item;
        $doc->attr_text = $text;

        // add the documents and a commit command to the update query
        $update->addDocuments(array($doc));
        $update->addCommit();

        // this executes the query and returns the result
        $result = $client->update($update);

        return $result;
    }

    /**
     * Remove media from Solr index.
     *
     *
     * @param Media $media
     */
    public function removeMediaFromSolrIndex($media)
    {
        $client = $this->getServiceLocator()->get('SolrDragon\Service\Solarium')->newClient('media');

        // get an update query instance
        $update = $client->createUpdate();

        // create a new document for the data
        $update->addDeleteQuery('id:'.$media->getId());
        $update->addCommit();

        // this executes the query and returns the result
        $result = $client->update($update);

        return $result;
    }

    /**
     * Send the word to solr for indexing.
     *
     * Either creates a new document or updates an existing one.
     *
     * @param Item $item
     * @param Media $media
     * @param string $words
     */
    public function addWordsToSolrIndex($item, $media, $words)
    {
        $client = $this->getServiceLocator()->get('SolrDragon\Service\Solarium')->newClient('words');

        if(!is_array($words)){
            $words = [$words];
        }

        // get an update query instance
        $update = $client->createUpdate();

        $documents = [];
        foreach($words as $word){
            // create a new document for the data
            $doc = $update->createDocument();

            $doc->media_id = $media;
            $doc->attr_text = $word['text'];
            $doc->x = $word['x'];
            $doc->y = $word['y'];
            $doc->width = $word['width'];
            $doc->height = $word['height'];

            $documents[] = $doc;
        }
        // add the documents and a commit command to the update query
        $update->addDocuments($documents);
        $update->addCommit();

        // this executes the query and returns the result
        $result = $client->update($update);

        return $result;
    }

    /**
     * Remove media words from Solr index.
     *
     * @param Media $media
     */
    public function removeWordsFromSolrIndex($media)
    {
        $client = $this->getServiceLocator()->get('SolrDragon\Service\Solarium')->newClient('words');

        // get an update query instance
        $update = $client->createUpdate();

        // create a new document for the data
        $update->addDeleteQuery('media_id:'.$media->getId());
        $update->addCommit();

        // this executes the query and returns the result
        $result = $client->update($update);

        return $result;
    }


    /**
     * Get the text property, caching on first pass.
     *
     * @return Omeka\Entity\Property|false
     */
    public function getTextProperty()
    {
        if (isset($this->textProperty)) {
            return $this->textProperty;
        }
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $textProperty = $entityManager->createQuery('
            SELECT p FROM Omeka\Entity\Property p
            JOIN p.vocabulary v
            WHERE p.localName = :localName
            AND v.namespaceUri = :namespaceUri
        ')->setParameters([
            'localName' => 'solrdragon_text_text',
            'namespaceUri' => 'http://omeka.org/s/vocabs/o-module-solrdragon#',
        ])->getOneOrNullResult();
        $this->textProperty = (null === $textProperty) ? false : $textProperty;
        return $this->textProperty;
    }

    /**
     * Verify the passed file.
     *
     * Working off the "real" base directory and "real" filepath: both must
     * exist and have sufficient permissions; the filepath must begin with the
     * base directory path to avoid problems with symlinks; the base directory
     * must be server-writable to delete the file; and the file must be a
     * readable regular file.
     *
     * @param \SplFileInfo $fileinfo
     * @return string|false The real file path or false if the file is invalid
     */
    public function verifyFile($directory, \SplFileInfo $fileinfo)
    {
        if (false === $directory) {
            return false;
        }
        $realPath = $fileinfo->getRealPath();
        if (false === $realPath) {
            return false;
        }
        if (0 !== strpos($realPath, $directory)) {
            return false;
        }
        if (!$fileinfo->isFile() || !$fileinfo->isReadable()) {
            return false;
        }
        return $realPath;
    }


}