<?php
namespace BulkImportFile\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class SettingsForm extends Form
{
    protected $modules = [];

    public function init()
    {
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'bulkimportfile_maps_settings',
            'attributes' => [
                'value' => '',
                'class' => 'bulkimportfile_maps_settings',
            ]
        ]);
    }
}
