<?php
namespace BulkImportFiles;

require_once dirname(__DIR__) . '/Generic/AbstractModule.php';

use Generic\AbstractModule;
use Zend\ModuleManager\ModuleManager;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        parent::install($serviceLocator);

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $label = 'Bulk import files';
        try {
            $resourceTemplate = $api
                ->read('resource_templates', ['label' => $label])
                ->getContent();
        } catch (\Exception $e) {
        }

        if (!isset($resourceTemplate)) {
            $data = [];
            $data['o:label'] = $label;
            $api->create('resource_templates', $data);
        }
    }
}
