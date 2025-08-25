<?php declare(strict_types=1);

namespace BulkImportFiles\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class SettingsForm extends Form
{
    public function init(): void
    {
        $this->setAttribute('id', 'bulkimportfiles-settings');

        $this->add([
            'name' => 'dummy',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Mapping settings (placeholder)', // @translate
            ],
            'attributes' => [
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'csrf',
            'type' => Element\Csrf::class,
        ]);

        $this->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'attributes' => [
                'value' => 'Save', // @translate
                'class' => 'button',
            ],
        ]);
    }
}
