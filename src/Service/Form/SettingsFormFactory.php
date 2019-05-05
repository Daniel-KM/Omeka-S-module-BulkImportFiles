<?php
namespace BulkImportFile\Service\Form;

use Interop\Container\ContainerInterface;
use BulkImportFile\Form\SettingsForm;
use Omeka\Module\Manager as ModuleManager;
use Zend\ServiceManager\Factory\FactoryInterface;

class SettingsFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $activeModules = $container->get('Omeka\ModuleManager')
            ->getModulesByState(ModuleManager::STATE_ACTIVE);

        $activeModulesArray = array();

        foreach ($activeModules as $key => $val) {
            if ($key != 'BulkImportFile') {
                $activeModulesArray[$key] = $key;
            }
        }

        $form = new SettingsForm(null, $options);
        return $form
            ->setModules($activeModulesArray);
    }
}
