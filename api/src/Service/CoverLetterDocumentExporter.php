<?php

declare(strict_types=1);

namespace App\Service;

use ZipArchive;

final class CoverLetterDocumentExporter
{
    public function pdf(string $letter): string
    {
        $lines = $this->pdfLines($letter);
        $pages = array_chunk($lines, 44);
        if ($pages === []) {
            $pages = [['']];
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pageReferences = [];

        foreach ($pages as $index => $pageLines) {
            $pageObject = 4 + ($index * 2);
            $streamObject = $pageObject + 1;
            $pageReferences[] = sprintf('%d 0 R', $pageObject);

            $content = "BT\n/F1 11 Tf\n16 TL\n50 790 Td\n";
            foreach ($pageLines as $lineIndex => $line) {
                if ($lineIndex > 0) {
                    $content .= "T*\n";
                }
                if ($line !== '') {
                    $content .= '('.$this->pdfEscape($line).") Tj\n";
                }
            }
            $content .= "ET";

            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                $streamObject,
            );
            $objects[$streamObject] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($content),
                $content,
            );
        }

        $objects[2] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', $pageReferences),
            count($pages),
        );
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $number, $object);
        }

        $xrefOffset = strlen($pdf);
        $objectCount = max(array_keys($objects)) + 1;
        $pdf .= sprintf("xref\n0 %d\n", $objectCount);
        $pdf .= "0000000000 65535 f \n";
        for ($number = 1; $number < $objectCount; ++$number) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number] ?? 0);
        }
        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF",
            $objectCount,
            $xrefOffset,
        );

        return $pdf;
    }

    public function docx(string $letter): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('L’extension PHP zip est requise pour exporter la lettre au format Word.');
        }

        $path = tempnam(sys_get_temp_dir(), 'jobpilot-cover-letter-');
        if ($path === false) {
            throw new \RuntimeException('Impossible de préparer le document Word.');
        }

        $zip = new ZipArchive();

        try {
            $result = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($result !== true) {
                throw new \RuntimeException('Impossible de créer le document Word.');
            }

            $zip->addFromString('[Content_Types].xml', $this->docxContentTypes());
            $zip->addFromString('_rels/.rels', $this->docxRootRelationships());
            $zip->addFromString('word/document.xml', $this->docxDocument($letter));
            $zip->addFromString('word/styles.xml', $this->docxStyles());
            $zip->addFromString('word/_rels/document.xml.rels', $this->docxDocumentRelationships());
            $zip->close();

            $content = file_get_contents($path);
            if ($content === false) {
                throw new \RuntimeException('Impossible de lire le document Word généré.');
            }

            return $content;
        } finally {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /** @return list<string> */
    private function pdfLines(string $letter): array
    {
        $sourceLines = preg_split('/\R/u', trim($letter)) ?: [];
        $result = [];

        foreach ($sourceLines as $sourceLine) {
            $line = trim($sourceLine);
            if ($line === '') {
                $result[] = '';
                continue;
            }

            $words = preg_split('/\s+/u', $line) ?: [];
            $current = '';
            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current.' '.$word;
                if ($current !== '' && mb_strlen($candidate) > 88) {
                    $result[] = $current;
                    $current = $word;
                    continue;
                }
                $current = $candidate;
            }

            if ($current !== '') {
                $result[] = $current;
            }
        }

        return $result;
    }

    private function pdfEscape(string $line): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $line);
        if ($encoded === false) {
            $encoded = $line;
        }

        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $encoded);
    }

    private function docxDocument(string $letter): string
    {
        $lines = preg_split('/\R/u', trim($letter)) ?: [];
        $paragraphs = '';

        foreach ($lines as $line) {
            if ($line === '') {
                $paragraphs .= '<w:p/>';
                continue;
            }

            $escaped = htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $paragraphs .= '<w:p><w:pPr><w:spacing w:after="120"/></w:pPr><w:r><w:t xml:space="preserve">'
                .$escaped
                .'</w:t></w:r></w:p>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$paragraphs
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    private function docxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';
    }

    private function docxRootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function docxDocumentRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function docxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            .'<w:name w:val="Normal"/><w:qFormat/><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>'
            .'</w:style></w:styles>';
    }
}
