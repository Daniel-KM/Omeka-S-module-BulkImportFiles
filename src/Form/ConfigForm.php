<?php

namespace BulkImportFile\Form;

use Zend\Form\Form;

class ConfigForm extends Form
{
    protected $modules = [];

    public function init()
    {
        $this->add([
            'type' => 'hidden',
            'name' => 'bulkimportfile_maps_settings',
            'attributes' => [
                'value' => '',
                'class' => 'bulkimportfile_maps_settings',
            ]
        ]);
    }

    /**
     * @param array $modules
     */
    public function setModules(array $modules)
    {
        $this->modules = $modules;
        return $this;
    }

    /**
     * @return array
     */
    public function getModules()
    {
        return $this->modules;
    }
}
