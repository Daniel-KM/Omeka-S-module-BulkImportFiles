<?php

namespace BulkImportFile;

use BulkImportFile\Form\ConfigFormSettings;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\ResourceTemplate;
use Omeka\Form\ResourceTemplateForm;
use Omeka\Module\AbstractModule;
use Omeka\Settings\SettingsInterface;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Fieldset;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{

    /**
     * @var ApiManager
     */
    private $api;

    protected $listenersByEventViewShowAfter = [];
    protected $resourceTemplate;

    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
    }


    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $this->manageAnySettings($serviceLocator->get('Omeka\Settings'), 'config', 'install', '');

        $services = $this->getServiceLocator();

        $this->api = $services->get('Omeka\ApiManager');

        try {
            $resourceTemplate = $this->api
                ->read('resource_templates', ['label' => 'BulkImportFile Resource'])
                ->getContent();

        } catch (\Exception $e) {

        }

        if (!isset($resourceTemplate))
        {
            $form = new ResourceTemplateForm;

            $label = 'BulkImportFile Resource';
            $this->resourceTemplate = new ResourceTemplate;
            $this->resourceTemplate->setLabel($label);

            $data['o:label'] = $label;

            $form->setData($data);
            $this->api->create('resource_templates', $data);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $this->manageAnySettings($serviceLocator->get('Omeka\Settings'), 'config', 'uninstall', '');
    }

    /**
     * Set or delete all settings of a specific type.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @param string $process
     * @param array $setValue
     */
    protected function manageAnySettings(SettingsInterface $settings, $settingsType, $process, $setValue)
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$settingsType];

        foreach ($defaultSettings as $name => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            if (is_array($setValue)) {
                $setValue = json_encode($setValue);
            }
            if (is_object($setValue)) {
                $setValue = json_encode($setValue);
            }

            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
                case 'update':
                    $settings->set($name, $setValue);
                    break;
            }
        }
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        require_once 'data/scripts/upgrade.php';
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {

    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();

        $settings = $services->get('Omeka\Settings');
        $data = $this->prepareDataToPopulate($settings, 'config');


        $view = $renderer;
        $html = '<p>';
        $html .= '</p>';
        $html .= '<p>'
            . $view->translate('Configure modules settings for display.') // @translate
            . '</p>';

        $form = $services->get('FormElementManager')->get(ConfigFormSettings::class);
        $form->init();

        $form->setData($data);

        $html .= $renderer->formCollection($form);

        return $html;
    }


    public function handleConfigForm(AbstractController $controller)
    {
        $config = include __DIR__ . '/config/module.config.php';
        $space = strtolower(__NAMESPACE__);

        $services = $this->getServiceLocator();

        $params = $controller->getRequest()->getPost();

        $form = $services->get('FormElementManager')->get(ConfigFormSettings::class);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();

        $settings = $services->get('Omeka\Settings');
        $defaultSettings = $config[$space]['config'];
        $params = array_intersect_key($params, $defaultSettings);

        $process = 'update';

        $setValue = json_decode($params['bulkimportfile_maps_settings']);

        $this->manageAnySettings($settings, 'config', $process, $setValue);


        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }

        return true;
    }

    /**
     * Prepare data for a form or a fieldset.
     *
     * To be overridden by module for specific keys.
     *
     * @todo Use form methods to populate.
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @return array
     */
    protected function prepareDataToPopulate(SettingsInterface $settings, $settingsType)
    {
        $config = include __DIR__ . '/config/module.config.php';
        $space = strtolower(__NAMESPACE__);
        if (empty($config[$space][$settingsType])) {
            return;
        }

        $defaultSettings = $config[$space][$settingsType];

        $data = [];
        foreach ($defaultSettings as $name => $value) {
            $val = $settings->get($name, $value);
            $data[$name] = $val;
        }

        return $data;
    }
}
