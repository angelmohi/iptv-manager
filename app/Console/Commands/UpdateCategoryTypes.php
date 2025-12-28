<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Channel;
use App\Models\ChannelCategory;
use Illuminate\Support\Facades\DB;

class UpdateCategoryTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:update-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the type field in channel_categories based on tvg_type from related channels';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting category type update process...');

        // Get all categories
        $categories = ChannelCategory::all();

        if ($categories->isEmpty()) {
            $this->warn('No categories found in the database.');
            return 0;
        }

        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($categories as $category) {
            $this->info("Processing category: {$category->name} (ID: {$category->id})");

            // Get the first channel with a non-null tvg_type for this category
            $channel = Channel::where('category_id', $category->id)
                ->whereNotNull('tvg_type')
                ->first();

            if ($channel) {
                // Update the category type with the channel's tvg_type
                $oldType = $category->type;
                $category->type = $channel->tvg_type;
                $category->save();

                $updatedCount++;

                if ($oldType !== $channel->tvg_type) {
                    $this->line("  ✓ Updated type from '{$oldType}' to '{$channel->tvg_type}'");
                } else {
                    $this->line("  ✓ Type already set to '{$channel->tvg_type}'");
                }
            } else {
                $skippedCount++;
                $this->warn("  ✗ No channels with tvg_type found for this category");
            }
        }

        $this->newLine();
        $this->info("Category type update completed!");
        $this->info("Total categories processed: {$categories->count()}");
        $this->info("Categories updated: {$updatedCount}");
        $this->info("Categories skipped (no channels with tvg_type): {$skippedCount}");

        return 0;
    }
}
