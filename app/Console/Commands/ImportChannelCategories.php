<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChannelCategory;
use Illuminate\Support\Facades\DB;

class ImportChannelCategories extends Command
{
    /**
     * The name and signature of the Artisan command.
     */
    protected $signature = 'import:channel-categories';

    /**
     * The command description.
     */
    protected $description = 'Import group titles from an M3U file into the channel_categories table, without duplicates';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Command logic.
     */
    public function handle()
    {
        $filePath = storage_path('app/total_ott.m3u');

        // Check that the file exists and is readable
        if (! is_readable($filePath)) {
            $this->error("Unable to read file: {$filePath}");
            return 1;
        }

        // Open the file in read mode
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            $this->error("Error opening file: {$filePath}");
            return 1;
        }

        $this->info("Processing file: {$filePath}");

        $categories = [];

        while (! feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                continue;
            }

            // We only care about lines containing #EXTINF
            // (although we could apply the regex directly without this check, it's more efficient this way)
            if (stripos($line, '#EXTINF') === false) {
                continue;
            }

            // Use a regular expression to capture the value of group-title="..."
            // The syntax looks for group-title="(...)"
            if (preg_match('/group-title="([^"]+)"/i', $line, $matches)) {
                $groupTitle = trim($matches[1]);

                // Ignore if it's empty
                if (strlen($groupTitle) === 0) {
                    continue;
                }

                // If it's not already in our temporary array, add it.
                // This prevents processing internal duplicates in the same file.
                if (! in_array($groupTitle, $categories, true)) {
                    $categories[] = $groupTitle;
                }
            }
        }

        fclose($handle);

        $this->info('Categories found in file: ' . count($categories));

        // Now, for each unique category in the array, insert it into the DB
        // only if it doesn't already exist (to avoid duplicates).
        // We can use firstOrCreate to simplify.
        $insertCount = 0;
        DB::beginTransaction();
        try {
            foreach ($categories as $catName) {
                // You can adjust the 'order' value as needed. Here we leave it at 1 by default.
                $categoria = ChannelCategory::firstOrCreate(
                    ['name' => $catName],
                    ['order' => 1]
                );

                // If it was just created (didn't exist), increment the counter
                if ($categoria->wasRecentlyCreated) {
                    $insertCount++;
                    $this->info("Created category: {$catName}");
                } else {
                    $this->line("Category already exists: {$catName} (skipped)");
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // An error occurred while saving to the DB
            $this->error("Error saving to the DB: " . $e->getMessage());
            return 1;
        }

        // Process finished. New categories inserted: {$insertCount}
        $this->info("Process completed. New categories inserted: {$insertCount}");

        return 0; // Success
    }
}
