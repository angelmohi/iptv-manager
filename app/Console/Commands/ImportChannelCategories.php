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

        // ...

		$this->info("Processing file: {$filePath}");

		$categories = []; // ['nombre' => ['name' => ..., 'type' => ...]]

		while (!feof($handle)) {
			$line = fgets($handle);
			if ($line === false) {
				continue;
			}

			// Solo líneas #EXTINF
			if (stripos($line, '#EXTINF') === false) {
				continue;
			}

			// group-title="..."
			if (!preg_match('/group-title="([^"]+)"/i', $line, $mGroup)) {
				continue;
			}

			$groupTitle = trim($mGroup[1]);
			if ($groupTitle === '') {
				continue;
			}

			// tvg-type="live|movie|series"
			$type = null;
			if (preg_match('/tvg-type="([^"]+)"/i', $line, $mType)) {
				$rawType = strtolower(trim($mType[1]));
				if (in_array($rawType, ['live', 'movie', 'series'], true)) {
					$type = $rawType;
				}
			}

			if (!isset($categories[$groupTitle])) {
				$categories[$groupTitle] = [
					'name' => $groupTitle,
					'type' => $type,
				];
			} else {
				// Si ya teníamos la categoría sin tipo y ahora sí, lo rellenamos
				if ($categories[$groupTitle]['type'] === null && $type !== null) {
					$categories[$groupTitle]['type'] = $type;
				}
			}
		}

		fclose($handle);

		$this->info('Categories found in file: ' . count($categories));

		$insertCount = 0;
		DB::beginTransaction();
		try {
			foreach ($categories as $cat) {
				$catName = $cat['name'];
				$catType = $cat['type'];

				$categoria = ChannelCategory::firstOrCreate(
					['name' => $catName],
					[
						'order' => 1,
						'type'  => $catType,   // ← aquí rellenamos el type
					]
				);

				// Si ya existía pero ahora tenemos un type nuevo, lo actualizamos
				if (!$categoria->wasRecentlyCreated && $catType !== null && $categoria->type !== $catType) {
					$categoria->type = $catType;
					$categoria->save();
				}

				if ($categoria->wasRecentlyCreated) {
					$insertCount++;
					$this->info("Created category: {$catName} (type={$catType})");
				} else {
					$this->line("Category already exists: {$catName} (type={$categoria->type})");
				}
			}

			DB::commit();
		} catch (\Exception $e) {
			DB::rollBack();
			$this->error("Error saving to the DB: " . $e->getMessage());
			return 1;
		}

		$this->info("Process completed. New categories inserted: {$insertCount}");

		return 0;

    }
}
