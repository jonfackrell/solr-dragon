<?php
namespace SolrDragon;

use Omeka\Stdlib\Cli;
use SolrDragon\Controller\SearchController;
use SolrDragon\Form\ConfigForm;
use Omeka\Module\AbstractModule;
use Spatie\PdfToImage\Pdf;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Math\Rand;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractController;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
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
            function (Event $event) {

                // Get module settings
                $settings = $this->getServiceLocator()->get('Omeka\Settings');
                $directory = $settings->get('file_sideload_directory');

                // Access the uploaded file passed through with the event
                $tempFile = $event->getParam('tempFile');

                switch ('pdftotext') {
                    case 'pdftotext':

                        if($tempFile->getExtension() == 'pdf'){
                            // Create a new item subdirectory in the sideload directory
                            if (!is_dir($directory.DIRECTORY_SEPARATOR.$event->getTarget()->getItem()->getId())) {
                                mkdir($directory.DIRECTORY_SEPARATOR.$event->getTarget()->getItem()->getId(), 0700);
                            }
                            // Convert the pdf into a png image and store it in the item subdirectory
                            $pdf = new Pdf($tempFile->getTempPath());
                            $pdf->setOutputFormat('png')
                                ->saveImage($directory.DIRECTORY_SEPARATOR.$event->getTarget()->getItem()->getId().DIRECTORY_SEPARATOR.str_replace('.pdf', '.png',$tempFile->getSourceName()));

                            // Extract OCR text coordinates
                            $coordinates = $this->extractText($tempFile->getTempPath(), $tempFile->getMediaType());
                            $logger = $this->getServiceLocator()->get('Omeka\Logger');
                            $logger->info($coordinates);
                        }
                        // Process XML
                        break;
                    case 'google':
                        // Process JSON
                        break;
                }

                /*$this->storeLocally($coordinates);
                if($solrIsEnabled)
                    $this->storeSolr();*/

            }
        );

        $sharedEventManager->attach(
            '*',
            'entity.update.post',
            function (Event $event) {
                die();
                $settings = $this->getServiceLocator()->get('Omeka\Settings');
                $directory = $settings->get('file_sideload_directory').DIRECTORY_SEPARATOR.$event->getTarget()->getId();
                $api = $this->getServiceLocator()->get('Omeka\ApiManager');
                // After the Item has been saved, we can now attach the the new png image as a new media object
                if (is_dir($directory)) {
                    $dir = new \SplFileInfo(realpath($directory));
                    $iterator = new \DirectoryIterator($dir);
                    foreach ($iterator as $file) {
                        if ($this->verifyFile($directory, $file)) {
                            // Create new Media object and store it
                            $response = $api->create('media', [
                                "o:ingester" => "sideload",
                                "o:source" => $file->getFilename(),
                                "ingest_filename" => $event->getTarget()->getId().DIRECTORY_SEPARATOR.$file->getFilename(),
                                "o:item" => [
                                    "o:id" => $event->getTarget()->getId()
                                ]
                            ], []
                            );

                            $media = $response->getContent();
                        }
                    }

                    // Delete the temporary item subdirectory from the sideload directory
                    rmdir($directory);

                }

            }
        );

    }

    /**
     * Extract text from a file.
     *
     * @param string $filePath
     * @param string $mediaType
     * @param array $options
     * @return string|false
     */
    public function extractText($filePath, $mediaType = null, array $options = [])
    {
        if (!@is_file($filePath)) {
            // The file doesn't exist.
            return false;
        }
        if (null === $mediaType) {
            // Fall back on PHP's magic.mime file.
            $mediaType = mime_content_type($filePath);
        }
        $extractors = $this->getServiceLocator()->get('SolrDragon\ExtractorManager');
        try {
            $extractor = $extractors->get($mediaType);
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