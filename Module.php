<?php declare(strict_types=1);

namespace BulkImportFiles;

if (!class_exists('Common\TraitModule', false)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;

/**
 * Bulk import files.
 *
 * @copyright Daniel Berthereau, 2018-2025
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    public function init(ModuleManager $moduleManager): void
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.72')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.72'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $file = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($file)) {
            throw new ModuleCannotInstallException(
                $translate('The libraries of the module should be installed first. See module’s installation documentation.') // @translate
            );
        }
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

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
