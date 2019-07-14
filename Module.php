<?php
namespace BulkImportFiles;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\ModuleManager;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    protected $dependency = 'BulkImport';

    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addAclRules();
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules()
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        // Only admins can edit mapping, else can only import, that uses some
        // ajax actions.
        $roles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
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
                    'check-files',
                    'check-folder',
                    'process-import',
                    // 'map-show',
                    // 'map-edit',
                    // 'add-file-type',
                    // 'delete-file-type',
                    // 'save-options',
                ]
            );
    }
}
