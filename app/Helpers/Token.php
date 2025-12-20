<?php

namespace App\Helpers;

use App\Exceptions\TokenException;
use App\Models\Account;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

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

        $response = Http::get($apiTokenUrl, $query);

        if ($response->failed()) {
            throw new TokenException(
                'Error obtaining token from external API: HTTP ' .
                $response->status()
            );
        }

        $data = $response->json();

        if (empty($data['deviceId']) || empty($data['cdnToken'])) {
            throw new TokenException('Incomplete API response: missing deviceId or cdnToken.');
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
