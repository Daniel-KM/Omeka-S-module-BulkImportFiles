<?php
namespace BulkImportFile\Service\Controller;

use BulkImportFile\Controller\IndexController;
use Interop\Container\ContainerInterface;
use Omeka\Settings\SettingsInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $mediaIngesterManager = $serviceLocator->get('Omeka\Media\Ingester\Manager');
        $config = $serviceLocator->get('Config');

        $settings = $serviceLocator->get('Omeka\Settings');


        $indexController = new IndexController($config, $mediaIngesterManager, $serviceLocator);
        return $indexController;
    }
}
