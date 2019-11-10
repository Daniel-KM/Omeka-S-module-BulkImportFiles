<?php
namespace BulkImportFiles;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.0.6', '<')) {
    $this->checkDependency();

    /** @var \Omeka\Module\Manager $moduleManager */
    $moduleManager = $services->get('Omeka\ModuleManager');
    $module = $moduleManager->getModule('BulkImport');
    $version = $module->getDb('version');
    if (version_compare($version, '3.0.12', '<')) {
        throw new \Omeka\Module\Exception\ModuleCannotInstallException(
            'BulkImportFiles requires module BulkImport version 3.0.12 or higher.' // @translate
        );
    }

    $pdftk = $settings->get('bulkimportfiles_pdftk');
    $pdftkBulk = $settings->get('bulkimport_pdftk');
    if ($pdftk && !$pdftkBulk) {
        $settings->set('bulkimport_pdftk', $pdftk);
    }
    $settings->delete('bulkimportfiles_pdftk');
}
