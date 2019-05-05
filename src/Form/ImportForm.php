<?php

namespace BulkImportFiles\Form;

use Zend\View\Helper\Url;
use Zend\Form\Form;

class ImportForm extends Form
{
    protected $urlHelper;

    public function init()
    {
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper)
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
