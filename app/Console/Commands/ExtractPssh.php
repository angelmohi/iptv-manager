<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Channel;
use App\Models\Account;
use Illuminate\Support\Facades\Log;
use App\Helpers\Pssh;
use App\Helpers\SearchKeys;
use App\Helpers\Lists;
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
            $catchup_url = $channel->catchup_source;

            $psshUpdated = false;
            $keysUpdated = false;
            $psshVal = $channel->pssh;
            $keysVal = $channel->api_key;

            $catchupPsshUpdated = false;
            $catchupKeysUpdated = false;
            $catchupPsshVal = $channel->catchup_pssh;
            $catchupKeysVal = $channel->catchup_api_key;

            try {
                $pssh = Pssh::getFromUrl($url, (bool)$channel->apply_token ? $token : null);

                if ($pssh && $pssh != $channel->pssh) {
                    $psshVal = $pssh;
                    $psshUpdated = true;
                    Log::info("PSSH Extraction: Updated channel '{$channel->name}' (ID: {$channel->id}).");

                    $keys = SearchKeys::getKeys($pssh);
                    if ($keys) {
                        $keysVal = $keys;
                        $keysUpdated = true;
                        Log::info("PSSH Extraction: Updated keys for channel '{$channel->name}' (ID: {$channel->id}).");
                    }
                    else {
                        Log::info("PSSH Extraction: No keys found for channel '{$channel->name}' (ID: {$channel->id}).");
                    }
                }

                if ($psshUpdated || $keysUpdated) {
                    if ($psshUpdated)
                        $channel->pssh = $psshVal;
                    if ($keysUpdated)
                        $channel->api_key = $keysVal;
                    $channel->save();
                }
            }
            catch (Exception $e) {
                Log::error("PSSH Extraction: Error in channel '{$channel->name}' (ID: {$channel->id}): " . $e->getMessage());
            }

            sleep(1); // 1 second delay between channels

            if ($catchup_url) {
                try {
                    $catchup_pssh = Pssh::getFromUrl($catchup_url, (bool)$channel->apply_token ? $token : null, true);

                    if ($catchup_pssh && $catchup_pssh != $channel->catchup_pssh) {
                        $catchupPsshVal = $catchup_pssh;
                        $catchupPsshUpdated = true;
                        Log::info("PSSH Extraction: Updated catch-up PSSH for channel '{$channel->name}' (ID: {$channel->id}).");

                        $catchup_keys = SearchKeys::getKeys($catchup_pssh);
                        if ($catchup_keys) {
                            $catchupKeysVal = $catchup_keys;
                            $catchupKeysUpdated = true;
                            Log::info("PSSH Extraction: Updated catch-up keys for channel '{$channel->name}' (ID: {$channel->id}).");
                        }
                        else {
                            Log::info("PSSH Extraction: No catch-up keys found for channel '{$channel->name}' (ID: {$channel->id}).");
                        }
                    }

                    if ($catchupPsshUpdated || $catchupKeysUpdated) {
                        if ($catchupPsshUpdated)
                            $channel->catchup_pssh = $catchupPsshVal;
                        if ($catchupKeysUpdated)
                            $channel->catchup_api_key = $catchupKeysVal;
                        $channel->save();
                    }
                }
                catch (Exception $e) {
                    Log::error("PSSH Extraction: Error in catch-up for channel '{$channel->name}' (ID: {$channel->id}): " . $e->getMessage());
                }

                sleep(1); // 1 second delay between channels
            }
        }

        $accounts = Account::all();
        foreach ($accounts as $account) {
            Lists::generateTivimateList($account);
            Lists::generateOttList($account);
            Lists::generateKodiList($account);
        }

        Log::info("PSSH Extraction: Process completed.");

        return 0;
    }
}
