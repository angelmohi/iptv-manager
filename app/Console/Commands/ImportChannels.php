<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Channel;
use App\Models\ChannelCategory;

class ImportChannels extends Command
{
    /**
     * The name and signature of the Artisan command.
     * Always uses storage/app/total_ott.m3u without parameters.
     */
    protected $signature = 'import:channels';

    /**
     * The command description.
     */
    protected $description = 'Import all channels from storage/app/total_ott.m3u, skipping commented (##...) and invalid lines.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // 1. Fixed file path inside storage/app
        $filePath = storage_path('app/total_ott.m3u');

        // 2. Check that the file exists and is readable
        if (! is_readable($filePath)) {
            $this->error("Unable to read file: {$filePath}");
            return 1;
        }

        $this->info("Starting channel import from: {$filePath}");

        // 3. Open the file for line-by-line processing
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            $this->error("Error opening file: {$filePath}");
            return 1;
        }

        // 4. Temporary variables to build each channel
        $current = [
            'tvg_id'        => null,
            'name'          => null,
            'group_title'   => null,
            'logo'          => null,
            'catchup'       => null,
            'catchup_days'  => null,
            'catchup_source'=> null,
            'user_agent'    => null,
            'manifest_type' => null,
            'license_type'  => null,
            'api_key'       => null,
            'url_channel'   => null,
        ];

        // 5. Counter for successfully processed channels
        $channelCount = 0;

        // 6. Incremental order counter if needed
        $orderCounter = 1;

        // 7. Iterate through each line of the file
        while (! feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                continue;
            }

            // Trim whitespace
            $trimmed = trim($line);

            // 7.1. Skip empty lines
            if ($trimmed === '') {
                continue;
            }

            // 7.2. Skip lines starting with "##" (commented)
            if (substr($trimmed, 0, 2) === '##') {
                continue;
            }

            // 7.3. Skip lines starting with "<" (e.g., <###### Movistar Futbol######>)
            if (substr($trimmed, 0, 1) === '<') {
                continue;
            }

            // 7.4. Process an #EXTINF: block (single #) for channel metadata
            if (stripos($trimmed, '#EXTINF:') === 0) {
                // 7.4.1. Reset $current for a new channel
                $current = array_fill_keys(array_keys($current), null);

                // 7.4.2. Extract key="value" attributes from the EXTINF line
                //     Use ([\w-]+) to allow hyphens in keys.
                // Example EXTINF line:
                // #EXTINF:-1 tvg-id="TV3" tvg-name="TV3" group-title="Movistar Autonomicas"
                //           catchup="default" catchup-days="30" catchup-source="https://...mpd" tvg-logo="...png", TV3
                preg_match_all('/([\w-]+)="([^"]*)"/i', $trimmed, $matches, PREG_SET_ORDER);

                foreach ($matches as $m) {
                    // $m[1] is the key, $m[2] is the value
                    $key = strtolower($m[1]);
                    $val = trim($m[2]);

                    switch ($key) {
                        case 'tvg-id':
                            $current['tvg_id'] = $val;
                            break;
                        case 'tvg-name':
                            $current['name'] = $val;
                            break;
                        case 'group-title':
                            $current['group_title'] = $val;
                            break;
                        case 'tvg-logo':
                            $current['logo'] = $val;
                            break;
                        case 'catchup':
                            $current['catchup'] = $val;
                            break;
                        case 'catchup-days':
                            $current['catchup_days'] = $val;
                            break;
                        case 'catchup-source':
                            $current['catchup_source'] = $val;
                            break;
                        // Add more EXTINF fields here if needed
                    }
                }

                // 7.4.3. If tvg-name was missing, take the human-readable name after the comma
                if (stripos($trimmed, ',') !== false) {
                    $afterComma = substr($trimmed, stripos($trimmed, ',') + 1);
                    if (empty($current['name'])) {
                        $current['name'] = trim($afterComma);
                    }
                }

                // Move to next line; URL will close this channel record
                continue;
            }

            // 7.5. Parse #EXTVLCOPT: for user_agent
            if (stripos($trimmed, '#EXTVLCOPT:') === 0) {
                // Example: #EXTVLCOPT:http-user-agent=Mozilla/5.0 (...)
                if (preg_match('/http-user-agent=(.*)/i', $trimmed, $m2)) {
                    $ua = trim($m2[1]);
                    $current['user_agent'] = trim($ua, '"');
                }
                continue;
            }

            // 7.6. Parse #KODIPROP: for manifest_type, license_type, and license_key
            if (stripos($trimmed, '#KODIPROP:') === 0) {
                $kodiprop = substr($trimmed, strlen('#KODIPROP:'));

                // 7.6.1. Capture manifest_type
                if (preg_match('/inputstream\.adaptive\.manifest_type=([^\s]+)/i', $kodiprop, $m3)) {
                    $current['manifest_type'] = trim($m3[1]);
                }

                // 7.6.2. Capture license_type
                if (preg_match('/inputstream\.adaptive\.license_type=([^\s]+)/i', $kodiprop, $m4)) {
                    $current['license_type'] = trim($m4[1]);
                }

                // 7.6.3. Capture license_key (JSON or token format)
                if (preg_match('/inputstream\.adaptive\.license_key=({.*})/i', $kodiprop, $m5_json)) {
                    // JSON format including braces
                    $current['api_key'] = trim($m5_json[1]);
                } elseif (preg_match('/inputstream\.adaptive\.license_key=([^\s]+)/i', $kodiprop, $m5_plain)) {
                    // Plain token (hex:hex)
                    $current['api_key'] = trim($m5_plain[1]);
                }

                // 7.6.4. Deliberately ignore stream headers (X-TCDN-token)
                continue;
            }

            // 7.7. If line does not start with '#' or '<' and is not empty => it's the channel URL
            if (substr($trimmed, 0, 1) !== '#' && substr($trimmed, 0, 1) !== '<') {
                // 7.7.1. Assign the URL
                $current['url_channel'] = $trimmed;

                // 7.7.2. We have all channel data, proceed to save

                // 7.7.2.1. Find category by group_title
                $catId = null;
                if (! empty($current['group_title'])) {
                    $categoria = ChannelCategory::where('name', $current['group_title'])->first();
                    if ($categoria) {
                        $catId = $categoria->id;
                    } else {
                        // Category not found, leave as null or create on the fly if desired
                        $catId = null;
                    }
                }

                // 7.7.2.2. Clean catchup_source from potential tokens
                if (! empty($current['catchup_source'])) {
                    $current['catchup_source'] = preg_replace(
                        '/([?&])X-TCDN-token=[^&]+(&?)/i',
                        '$1',
                        $current['catchup_source']
                    );
                    $current['catchup_source'] = rtrim($current['catchup_source'], '&');
                }

                // 7.7.2.3. Prepare data array for insertion
                $channelData = [
                    'category_id'   => $catId,
                    'name'          => $current['name']           ?? null,
                    'tvg_id'        => $current['tvg_id']         ?? null,
                    'logo'          => $current['logo']           ?? null,
                    'user_agent'    => $current['user_agent']     ?? null,
                    'manifest_type' => $current['manifest_type']  ?? null,
                    'license_type'  => $current['license_type']   ?? null,
                    'api_key'       => $current['api_key']        ?? null,
                    'url_channel'   => $current['url_channel']    ?? null,
                    'catchup'       => $current['catchup']        ?? null,
                    'catchup_days'  => $current['catchup_days']   ?? null,
                    'catchup_source'=> $current['catchup_source'] ?? null,
                    'order'         => $orderCounter,
                ];

                // 7.7.2.4. Insert into channels table
                try {
                    Channel::create($channelData);
                    $channelCount++;
                    $this->info("Imported channel: {$current['name']} (tvg-id: {$current['tvg_id']})");
                } catch (\Exception $e) {
                    $this->error("Error inserting channel {$current['name']}: " . $e->getMessage());
                }

                // 7.7.2.5. Prepare for next channel: increment order and reset $current
                $orderCounter++;
                $current = array_fill_keys(array_keys($current), null);
            }

            // Lines that don't match any case are ignored
        } // end while

        fclose($handle);

        $this->info("Import finished. Total channels inserted: {$channelCount}");

        return 0;
    }
}
