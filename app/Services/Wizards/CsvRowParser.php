<?php

namespace App\Services\Wizards;

use App\Services\FormatPresetMapper;
use Illuminate\Http\UploadedFile;

class CsvRowParser
{
    /**
     * Parse CSV file into normalised rows using column mappings.
     *
     * @param  array<string, string>  $columnMappings  csvColumnName => semantic field
     * @return list<array{title: string, caption: string, format: string}>
     */
    public function parse(UploadedFile $file, array $columnMappings): array
    {
        $content = file_get_contents($file->getRealPath());
        $content = ltrim($content, "\xEF\xBB\xBF");

        // Parse via a stream so quoted fields containing newlines (multi-line
        // captions/briefs) are preserved as a single field per RFC 4180.
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headers = fgetcsv($handle);
        if ($headers === false || $headers === null) {
            fclose($handle);

            return [];
        }

        $headers = array_map(fn ($h) => trim((string) $h), $headers);

        $titleCol = $this->findColumn($columnMappings, 'Product Title') ?? ($headers[0] ?? null);
        $captionCol = $this->findColumn($columnMappings, 'Image Prompt');
        $formatCol = $this->findColumn($columnMappings, 'Format');

        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            // Skip blank lines (fgetcsv yields [null] for an empty row).
            if ($values === null || $values === [null] || implode('', array_map('strval', $values)) === '') {
                continue;
            }

            $row = array_combine($headers, array_pad($values, count($headers), ''));

            if (! $row) {
                continue;
            }

            $title = trim((string) ($row[$titleCol] ?? ''));
            $caption = $captionCol ? trim((string) ($row[$captionCol] ?? '')) : $title;
            $format = $formatCol ? strtolower(trim((string) ($row[$formatCol] ?? 'square'))) : 'square';

            if ($title === '') {
                continue;
            }

            $rows[] = [
                'title' => $title,
                'caption' => $caption !== '' ? $caption : $title,
                'format' => FormatPresetMapper::normalize($format),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $columnMappings
     */
    public function findColumn(array $columnMappings, string $semanticName): ?string
    {
        foreach ($columnMappings as $csvColumn => $mapped) {
            if ($mapped === $semanticName) {
                return $csvColumn;
            }
        }

        return null;
    }
}
