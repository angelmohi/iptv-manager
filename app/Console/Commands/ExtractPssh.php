<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Channel;
use App\Models\Account;
use SimpleXMLElement;
use Illuminate\Support\Facades\Log;
use App\Helpers\Pssh;
use App\Helpers\SearchKeys;
use Exception;

class ExtractPssh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channels:update-pssh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Iterate through all live channels and extract PSSH from their MPD.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $username = config('services.iptv.token_account');
        $account = Account::where('username', $username)->first();

        if (!$account) {
            Log::error("PSSH Extraction: Account '{$username}' not found.");
            return 1;
        }

        $token = $account->token;
        if (!$token) {
            Log::error("PSSH Extraction: Account '{$username}' does not have a valid token.");
            return 1;
        }

        $channels = Channel::where('tvg_type', 'live')->where('is_active', true)->whereNotNull('url_channel')->get();
        Log::info("PSSH Extraction: Starting processing of " . $channels->count() . " channels.");

        foreach ($channels as $channel) {
            $url = $channel->url_channel;
            
            try {
                $pssh = Pssh::getFromUrl($url, (bool) $channel->apply_token ? $token : null);
                
                if ($pssh && $pssh != $channel->pssh) {
                    $channel->pssh = $pssh;
                    $channel->save();
                    Log::info("PSSH Extraction: Updated channel '{$channel->name}' (ID: {$channel->id}).");

                    $keys = SearchKeys::getKeys($pssh);
                    if ($keys) {
                        $channel->api_key = $keys;
                        $channel->save();
                        Log::info("PSSH Extraction: Updated keys for channel '{$channel->name}' (ID: {$channel->id}).");
                    } else {
                        Log::info("PSSH Extraction: No keys found for channel '{$channel->name}' (ID: {$channel->id}).");
                    }
                }
            } catch (Exception $e) {
                Log::error("PSSH Extraction: Error in channel '{$channel->name}' (ID: {$channel->id}): " . $e->getMessage());
            }

            sleep(1); // 1 second delay between channels
        }

        $accounts = Account::all();
        foreach ($accounts as $account) {
            Lists::generateTivimateList($account);
            Lists::generateOttList($account);
			Lists::generateCineList($account);
			Lists::generateSeriesList($account);
			Lists::generateCineOttList($account);
			Lists::generateSeriesOttList($account);
            Lists::generateKodiList($account);
        }

        Log::info("PSSH Extraction: Process completed.");

        return 0;
    }

    /**
     * Extract PSSH from an MPD URL.
     */
    private function fetchPssh($url, $token, $applyToken = false)
    {
        return Pssh::getFromUrl($url, $applyToken ? $token : null);
    }
}
