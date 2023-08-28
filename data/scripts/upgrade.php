<?php declare(strict_types=1);

namespace BulkImportFiles;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.0.6', '<')) {
    $this->checkDependencies();

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

if (version_compare($oldVersion, '3.3.6.2', '<')) {
    $this->checkDependencies();

    /** @var \Omeka\Module\Manager $moduleManager */
    $moduleManager = $services->get('Omeka\ModuleManager');
    $module = $moduleManager->getModule('BulkImport');
    $version = $module->getDb('version');
    if (version_compare($version, '3.3.21', '<')) {
        throw new \Omeka\Module\Exception\ModuleCannotInstallException(
            'BulkImportFiles requires module BulkImport version 3.3.21 or higher.' // @translate
        );
    }
}
