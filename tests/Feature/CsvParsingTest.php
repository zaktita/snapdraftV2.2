<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvParsingTest extends TestCase
{
    public function test_csv_parsing_filters_empty_rows()
    {
        Storage::fake('local');
        
        $csvContent = "title,description,format\n";
        $csvContent .= "Summer,Vibes,square\n";
        $csvContent .= ",,\n"; // Empty row
        $csvContent .= "Winter,Cold,portrait\n";
        
        $filePath = 'test.csv';
        Storage::put($filePath, $csvContent);
        $fullPath = Storage::path($filePath);

        $data = [];
        $header = null;

        if (($handle = fopen($fullPath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    $rowData = array_combine($header, $row);
                    
                    // Filter logic
                    $hasData = false;
                    foreach ($rowData as $value) {
                        if (!empty(trim($value))) {
                            $hasData = true;
                            break;
                        }
                    }
                    
                    if ($hasData) {
                        $data[] = $rowData;
                    }
                }
            }
            fclose($handle);
        }

        $this->assertCount(2, $data);
        $this->assertEquals('Summer', $data[0]['title']);
        $this->assertEquals('Winter', $data[1]['title']);
    }
}
