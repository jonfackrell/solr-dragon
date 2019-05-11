<?php
namespace SolrDragon;

use SolrDragon\Form\ConfigForm;
use Omeka\Module\AbstractModule;
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


}