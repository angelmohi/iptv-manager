<?php

namespace App\Helpers;

use App\Exceptions\TokenException;
use App\Models\Account;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Token
{
    /**
     * Refresh the CDN token for a given account.
     *
     * @throws TokenException
     * @return array{deviceId: string, cdnToken: string}
     */
    public static function refreshCdnToken(Account $account): array
    {
        $apiTokenUrl = config('services.api_token_url');
        $username = $account->username;
        $password = Crypt::decryptString($account->password);

        if (!$username || !$password) {
            throw new TokenException('Credentials not found or invalid.');
        }

        $query = [
            'username' => $username,
            'password' => $password,
        ];
        if ($account->device_id) {
            $query['deviceId'] = $account->device_id;
        }

        $maxAttempts = 3;
        $data = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Http::get($apiTokenUrl, $query);

            if ($response->failed()) {
                if ($attempt < $maxAttempts) {
                    sleep(5);
                    continue;
                }
                throw new TokenException(
                    'Error obtaining token from external API: HTTP ' .
                    $response->status()
                );
            }

            $data = $response->json();

            if (!empty($data['deviceId']) && !empty($data['cdnToken'])) {
                break;
            }

            if ($attempt < $maxAttempts) {
                Log::warning("CDN token attempt {$attempt}/{$maxAttempts}: incomplete response, retrying in 5s...");
                sleep(5);
            }
        }

        if (empty($data['deviceId']) || empty($data['cdnToken'])) {
            throw new TokenException('Incomplete API response after ' . $maxAttempts . ' attempts: missing deviceId or cdnToken.');
        }

        $deviceId = $data['deviceId'];
        $cdnToken = $data['cdnToken'];

        // Save the device ID and CDN token to the account
        $account->device_id         = $deviceId;
        $account->token            = $cdnToken;
        $account->token_expires_at = now()->addDay();
        $account->save();

        return [
            'deviceId' => $deviceId,
            'cdnToken' => $cdnToken,
        ];
    }
}
