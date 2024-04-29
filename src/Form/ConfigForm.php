<?php declare(strict_types=1);

namespace BulkImportFiles\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    protected $modules = [];

    public function init(): void
    {
        $this
            ->add([
                'name' => 'bulkimportfiles_local_path',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Folder for files to import', // @translate
                    'info' => 'For security reasons, local files to import should be inside this folder.', // @translate
                ],
                'attributes' => [
                    'id' => 'bulkimportfiles_local_path',
                ],
            ])
            ->add([
                'name' => 'bulkimportfiles_pdftk',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'PdfTk path', // @translate
                    'info' => 'Set the path if it is not automatically detected. PdfTk is the library used to extract metadata from pdf files.', // @translate
                ],
                'attributes' => [
                    'id' => 'bulkimportfiles_pdftk',
                ],
            ])
        ;
    }
}
