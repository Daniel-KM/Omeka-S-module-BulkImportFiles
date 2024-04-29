<?php declare(strict_types=1);

namespace BulkImportFiles\Service\ControllerPlugin;

use BulkImportFiles\Mvc\Controller\Plugin\MapData;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MapDataFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new MapData(
            $services->get('EasyMeta'),
            $plugins->get('extractStringFromFile')
        );
    }
}
