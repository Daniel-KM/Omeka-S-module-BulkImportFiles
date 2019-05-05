<?php
namespace BulkImportFile\Service\Form;

use Interop\Container\ContainerInterface;
use BulkImportFile\Form\SettingsForm;
use Zend\ServiceManager\Factory\FactoryInterface;

class SettingsFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new SettingsForm(null, $options);
    }
}
