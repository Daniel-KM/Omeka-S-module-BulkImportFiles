<?php declare(strict_types=1);

namespace BulkImportFiles;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\Exception\ModuleCannotInstallException;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    protected $dependencies = [
        'BulkImport',
    ];

    public function init(ModuleManager $moduleManager): void
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->addAclRules();
    }

    protected function preInstall(): void
    {
        $this->checkDependencies();

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $this->getServiceLocator()->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('BulkImport');
        $version = (string) $module->getDb('version');
        if (version_compare($version, '3.3.28', '<')) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                'BulkImportFiles requires module BulkImport version 3.3.28 or higher.' // @translate
            );
        }

        $file = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($file)) {
            $services = $this->getServiceLocator();
            $t = $services->get('MvcTranslator');
            throw new ModuleCannotInstallException(
                $t->translate('The libraries of the module should be installed first.') // @translate
                    . ' ' . $t->translate('See module’s installation documentation.') // @translate
            );
        }
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules(): void
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        // Only global admin can edit mapping, other people can only import.
        // The rules include some ajax actions.
        $roles = [
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
        ];
        $acl
            ->deny(
                null,
                ['BulkImportFiles\Controller\Index']
            )
            ->allow(
                $roles,
                ['BulkImportFiles\Controller\Index'],
                [
                    'index',
                    'make-import',
                    'get-files',
                    'get-folder',
                    'check-files',
                    'check-folder',
                    'process-import',
                    'map-show',
                ]
            )
            ->deny(
                [\Omeka\Permissions\Acl::ROLE_SITE_ADMIN],
                ['BulkImportFiles\Controller\Index'],
                [
                    'map-edit',
                    'add-file-type',
                    'delete-file-type',
                    'save-options',
                ]
            );
    }
}
