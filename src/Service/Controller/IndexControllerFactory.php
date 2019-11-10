<?php
namespace BulkImportFiles\Service\Controller;

use BulkImportFiles\Controller\IndexController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        return new IndexController(
            $serviceLocator->get('Omeka\File\TempFileFactory'),
            $serviceLocator->get('Omeka\File\Uploader'),
            $serviceLocator->get('FormElementManager')
        );
    }
}
