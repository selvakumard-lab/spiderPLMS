<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

/** Reads the plain text out of an uploaded project document: PDF, DOCX, or a text file. */
class DocumentTextExtractor
{
    public const EXTENSIONS = ['pdf', 'docx', 'txt', 'md'];

    public const MAX_CHARACTERS = 120000;

    /** @throws RuntimeException When the file cannot be read or holds no text (for example a scanned PDF). */
    public function extract(string $absolutePath, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'pdf' => $this->pdf($absolutePath),
            'docx' => $this->docx($absolutePath),
            'txt', 'md' => (string) file_get_contents($absolutePath),
            default => throw new RuntimeException('Upload a PDF, DOCX, TXT or MD file.'),
        };

        $text = trim((string) preg_replace("/[ \t]+/", ' ', str_replace(["\r\n", "\r"], "\n", $text)));
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        if (mb_strlen($text) < 40) {
            throw new RuntimeException('We could not find readable text in this file. Scanned documents are not supported, so export it as a text-based PDF or DOCX.');
        }

        return mb_substr($text, 0, self::MAX_CHARACTERS);
    }

    private function pdf(string $path): string
    {
        try {
            return (new Parser)->parseFile($path)->getText();
        } catch (Throwable) {
            throw new RuntimeException('This PDF could not be read. It may be damaged or password protected.');
        }
    }

    private function docx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('This DOCX file could not be opened. It may be damaged.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('This does not look like a Word (.docx) document.');
        }

        $xml = preg_replace(['/<w:tab\b[^>]*\/>/', '/<w:br\b[^>]*\/>/', '/<\/w:p>/', '/<\/w:tc>/'], ["\t", "\n", "\n", "\t"], $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
