<?php

namespace App\Services\Resumes;

use RuntimeException;
use Smalot\PdfParser\Parser;
use ZipArchive;

/**
 * Reads plain text out of PDF and DOCX resumes.
 */
class ResumeTextExtractor
{
    public const PDF = 'application/pdf';

    public const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function extract(string $path, string $mimeType): string
    {
        $text = match ($mimeType) {
            self::PDF => (new Parser)->parseFile($path)->getText(),
            self::DOCX => $this->docxText($path),
            default => throw new RuntimeException("Unsupported resume type [{$mimeType}]."),
        };

        return $this->normalize($text);
    }

    private function docxText(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The DOCX file could not be opened.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('The DOCX file has no document body.');
        }

        // Keep paragraph, line-break and tab boundaries before dropping markup.
        $xml = preg_replace(['/<w:tab\/>/', '/<w:br\/>/', '/<\/w:p>/'], ["\t", "\n", "\n"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = array_map(
            fn (string $line) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line)),
            explode("\n", $text),
        );

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }
}
