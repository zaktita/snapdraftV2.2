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

        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $lines = array_values($lines);

        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));

        $titleCol = $this->findColumn($columnMappings, 'Product Title') ?? ($headers[0] ?? null);
        $captionCol = $this->findColumn($columnMappings, 'Image Prompt');
        $formatCol = $this->findColumn($columnMappings, 'Format');

        $rows = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $values = str_getcsv($line);
            $row = array_combine($headers, array_pad($values, count($headers), ''));

            if (! $row) {
                continue;
            }

            $title = trim($row[$titleCol] ?? '');
            $caption = $captionCol ? trim($row[$captionCol] ?? '') : $title;
            $format = $formatCol ? strtolower(trim($row[$formatCol] ?? 'square')) : 'square';

            if ($title === '') {
                continue;
            }

            $rows[] = [
                'title' => $title,
                'caption' => $caption ?: $title,
                'format' => FormatPresetMapper::normalize($format),
            ];
        }

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
