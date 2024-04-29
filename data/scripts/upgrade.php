<?php declare(strict_types=1);

namespace BulkImportFiles;

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
$config = $services->get('Config');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.57')) {
    $message = new \Omeka\Stdlib\Message(
        'The module %1$s should be upgraded to version %2$s or later.', // @translate
        'Common', '3.4.57'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (version_compare($oldVersion, '3.4.8', '<')) {
    $pdftk = $settings->get('bulkimportfiles_pdftk') ?: null;
    $pdftkBulk = $settings->get('bulkimport_pdftk') ?: null;
    $settings->set('bulkimportfiles_pdftk', $pdftk ?? $pdftkBulk ?? '/usr/bin/pdftk');

    $config = $services->get('Config');
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

    $pdftk = $settings->get('bulkimportfiles_local_path') ?: null;
    $pdftkBulk = $settings->get('bulkimport_local_path') ?: null;
    $settings->set('bulkimportfiles_local_path', $pdftk ?? $pdftkBulk ?? ($basePath . '/_import'));
}
