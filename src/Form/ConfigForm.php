<?php
namespace BulkImportFiles\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'bulkimportfiles_pdftk',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'PdfTk path', // @translate
                'info' => 'Set the path if it is not automatically detected. PdfTk is the library used to extract metadata from pdf files.', // @translate
            ],
            'attributes' => [
                'id' => 'bulkimportfiles_pdftk',
            ],
        ]);
    }
}
