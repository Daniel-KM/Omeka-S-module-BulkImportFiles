<?php declare(strict_types=1);

namespace BulkImportFiles\Service\Controller;

use BulkImportFiles\Controller\IndexController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new IndexController(
            $services->get('Omeka\File\TempFileFactory'),
            $services->get('Omeka\File\Uploader'),
            $services->get('FormElementManager')
        );
    }
}
