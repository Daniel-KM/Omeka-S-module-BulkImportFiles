<?php declare(strict_types=1);

namespace BulkImportFiles\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Smalot\PdfParser\Parser;

class ExtractDataFromPdf extends AbstractPlugin
{
    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function __invoke(string $filepath): array
    {
        if (!is_readable($filepath)) {
            return [];
        }
        $pdf = $this->parser->parseFile($filepath);

        $details = $pdf->getDetails();
        if (is_array($details) && $details) {
            return $details;
        }

        $text = trim((string) $pdf->getText());
        return $text !== '' ? ['content' => $text] : [];
    }
}
