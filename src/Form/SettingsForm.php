<?php
namespace BulkImportFiles\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class SettingsForm extends Form
{
    protected $modules = [];

    public function init()
    {
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'bulkimportfiles_maps_settings',
            'attributes' => [
                'value' => '',
                'class' => 'bulkimportfiles_maps_settings',
            ]
        ]);
    }
}
