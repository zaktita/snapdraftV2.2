<?php

namespace Tests\Unit\Services\Wizards;

use App\Services\FormatPresetMapper;
use App\Services\Wizards\CsvRowParser;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvRowParserTest extends TestCase
{
    public function test_parses_csv_with_column_mappings(): void
    {
        $content = "title,caption,format\n";
        $content .= "Product Launch,Buy now,square\n";
        $file = UploadedFile::fake()->createWithContent('data.csv', $content);

        $parser = new CsvRowParser();
        $rows = $parser->parse($file, [
            'title' => 'Product Title',
            'caption' => 'Image Prompt',
            'format' => 'Format',
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('Product Launch', $rows[0]['title']);
        $this->assertSame('Buy now', $rows[0]['caption']);
        $this->assertSame(FormatPresetMapper::normalize('square'), $rows[0]['format']);
    }

    public function test_returns_empty_for_header_only_csv(): void
    {
        $file = UploadedFile::fake()->createWithContent('data.csv', "title\n");

        $parser = new CsvRowParser();
        $rows = $parser->parse($file, ['title' => 'Product Title']);

        $this->assertSame([], $rows);
    }
}
