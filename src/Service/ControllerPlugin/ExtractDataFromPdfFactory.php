<?php
namespace BulkImportFiles\Service\ControllerPlugin;

use BulkImportFiles\Mvc\Controller\Plugin\ExtractDataFromPdf;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ExtractDataFromPdfFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('ControllerPluginManager')->get('settings');
        $config = $services->get('Config');
        $executeStrategy = isset($config['cli']['execute_strategy']) ? $config['cli']['execute_strategy'] : 'exec';
        return new ExtractDataFromPdf(
            $settings()->get('bulkimportfiles_pdftk'),
            $executeStrategy,
            $services->get('Omeka\Logger')
        );
    }
}
