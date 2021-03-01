<?php declare(strict_types=1);

namespace BulkImportFiles\Form;

use Laminas\Form\Form;
use Laminas\View\Helper\Url;

class ImportForm extends Form
{
    protected $urlHelper;

    public function init(): void
    {
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper): void
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}
