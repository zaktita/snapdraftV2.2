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

    public function test_preserves_multiline_quoted_caption(): void
    {
        $multilineCaption = "Cours du soir – Rentrée 2026/2027\n"
            ."Filières : Mode • Multimédia • Cuisine\n"
            ."Diplômes : Technicien & Technicien spécialisé\n"
            ."Inscrivez-vous dès aujourd’hui";

        $content = "title,caption,format\n";
        $content .= '"Cours du soir","'.$multilineCaption.'",square'."\n";
        $content .= "Second Post,Single line caption,square\n";

        $file = UploadedFile::fake()->createWithContent('data.csv', $content);

        $parser = new CsvRowParser();
        $rows = $parser->parse($file, [
            'title' => 'Product Title',
            'caption' => 'Image Prompt',
            'format' => 'Format',
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('Cours du soir', $rows[0]['title']);
        $this->assertSame($multilineCaption, $rows[0]['caption']);
        $this->assertStringContainsString('Filières', $rows[0]['caption']);
        $this->assertStringContainsString('Inscrivez-vous', $rows[0]['caption']);
        $this->assertSame('Second Post', $rows[1]['title']);
        $this->assertSame('Single line caption', $rows[1]['caption']);
    }
}
