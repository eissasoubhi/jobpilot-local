<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\CoverLetterDocumentExporter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class CoverLetterDocumentExporterTest extends TestCase
{
    private const DOCX_ENTRY_MTIME = 315532800;

    public function testDocxContainsNoGenerationTimestampOrApplicationProperties(): void
    {
        $exporter = new CoverLetterDocumentExporter();
        $first = $exporter->docx("Madame, Monsieur,\n\nUne lettre déterministe.");
        $second = $exporter->docx("Madame, Monsieur,\n\nUne lettre déterministe.");

        self::assertSame($first, $second);

        $path = tempnam(sys_get_temp_dir(), 'jobpilot-docx-test-');
        self::assertNotFalse($path);

        try {
            self::assertNotFalse(file_put_contents($path, $first));

            $zip = new ZipArchive();
            self::assertTrue($zip->open($path));

            $names = [];
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $stat = $zip->statIndex($index);
                self::assertIsArray($stat);
                self::assertArrayHasKey('name', $stat);
                self::assertArrayHasKey('mtime', $stat);
                self::assertSame(self::DOCX_ENTRY_MTIME, $stat['mtime']);
                $names[] = $stat['name'];
            }

            self::assertSame([
                '[Content_Types].xml',
                '_rels/.rels',
                'word/document.xml',
                'word/styles.xml',
                'word/_rels/document.xml.rels',
            ], $names);
            self::assertSame([], array_values(array_filter(
                $names,
                static fn (string $name): bool => str_starts_with($name, 'docProps/'),
            )));

            $zip->close();
        } finally {
            @unlink($path);
        }
    }
}
