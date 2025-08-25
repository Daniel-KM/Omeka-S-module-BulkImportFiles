<?php declare(strict_types=1);

namespace BulkImportFiles\Service\ControllerPlugin;

use BulkImportFiles\Mvc\Controller\Plugin\ExtractDataFromPdf;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Smalot\PdfParser\Parser;

class ExtractDataFromPdfFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ExtractDataFromPdf(new Parser());
    }
}
