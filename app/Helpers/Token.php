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
            throw new TokenException('Credenciales no encontradas o inválidas.');
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
                'Error al obtener token desde API externa: HTTP ' .
                $response->status()
            );
        }

        $data = $response->json();

        if (empty($data['deviceId']) || empty($data['cdnToken'])) {
            throw new TokenException('Respuesta de API incompleta: faltan deviceId o cdnToken.');
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
