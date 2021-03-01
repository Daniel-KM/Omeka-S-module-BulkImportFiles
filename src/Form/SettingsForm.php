<?php declare(strict_types=1);
namespace BulkImportFiles\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class SettingsForm extends Form
{
    protected $modules = [];

    public function init(): void
    {
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'bulkimportfiles_mappings',
            'attributes' => [
                'value' => '',
                'class' => 'bulkimportfiles_mappings',
            ],
        ]);
    }
}
