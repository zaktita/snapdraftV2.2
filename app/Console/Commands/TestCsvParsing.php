<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestCsvParsing extends Command
{
    protected $signature = 'test:csv-parsing {file}';
    protected $description = 'Test CSV parsing to verify empty row filtering';

    public function handle()
    {
        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $data = [];
        $header = null;

        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    // Combine header with row data
                    $rowData = array_combine($header, $row);
                    
                    // Filter out empty rows (rows where all values are empty)
                    $hasData = false;
                    foreach ($rowData as $value) {
                        if (!empty(trim($value))) {
                            $hasData = true;
                            break;
                        }
                    }
                    
                    // Only add rows that have at least one non-empty value
                    if ($hasData) {
                        $data[] = $rowData;
                    }
                }
            }
            fclose($handle);
        }

        $this->info("Parsed " . count($data) . " rows:");
        $this->line(json_encode($data, JSON_PRETTY_PRINT));

        return 0;
    }
}
