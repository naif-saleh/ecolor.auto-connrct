<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportEvaluationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Path to your CSV file
        $csvFile = database_path('seeders/data/mobile_agent_data.csv');

        if (file_exists($csvFile)) {
            $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $addedCount = 0;
            $skippedCount = 0;

            // Skip header line if it exists
            if (strpos($lines[0], 'Mobile') !== false) {
                array_shift($lines);
            }

            foreach ($lines as $line) {
                $parts = explode(',', $line);

                // Check if we have enough parts
                if (count($parts) >= 5) {
                    $mobile = $parts[0];
                    $extension = !empty($parts[1]) ? $parts[1] : null;
                    $isSatisfied = (trim($parts[2]) === 'Yes') ? 'YES' : 'NO';

                    // The date-time is in the format: ,04/06/2025 - 11:28
                    $dateTimePart = trim($parts[count($parts) - 1]);
                    if (preg_match('/(\d{2}\/\d{2}\/\d{4}) - (\d{2}:\d{2})/', $dateTimePart, $matches)) {
                        $date = $matches[1];
                        $time = $matches[2];

                        // Convert date format
                        $dateObj = Carbon::createFromFormat('d/m/Y H:i', $date . ' ' . $time);
                        $formattedDateTime = $dateObj->format('Y-m-d H:i:s');

                        // Prepare data for insertion
                        $data = [
                            'mobile' => $mobile,
                            'is_satisfied' => $isSatisfied,
                            'lang' => 'ar', // Default language
                            'created_at' => $formattedDateTime,
                            'updated_at' => $formattedDateTime
                        ];

                        // Add extension if not empty
                        if (!empty($extension)) {
                            $data['extension'] = $extension;
                        }

                        // Insert into database
                        DB::table('evaluations')->insert($data);
                        $addedCount++;
                    } else {
                        $this->command->warn("Could not parse date/time from line: $line");
                        $skippedCount++;
                    }
                } else {
                    $this->command->warn("Line doesn't have enough parts: $line");
                    $skippedCount++;
                }
            }

            $this->command->info("Evaluations seeding completed:");
            $this->command->info("- $addedCount records added successfully");
            $this->command->info("- $skippedCount records skipped due to format issues");
        } else {
            $this->command->error('CSV file not found at: ' . $csvFile);
        }
    }
}
