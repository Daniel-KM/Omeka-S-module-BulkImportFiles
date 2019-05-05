<?php
namespace BulkImportFile\Service\Controller;

use BulkImportFile\Controller\IndexController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        return new IndexController($serviceLocator);
    }
}
