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
        $username = $account->username;
        $password = Crypt::decryptString($account->password);

        if (!$username || !$password) {
            throw new TokenException('Credenciales no encontradas o inválidas.');
        }

        // 1) Login
        $login = Http::asForm()->post(
            'https://auth.dof6.com/auth/oauth2/token?deviceClass=amazon.tv',
            [
                'grant_type'   => 'password',
                'deviceClass'  => 'amazon.tv',
                'username'     => $username,
                'password'     => $password,
            ]
        );
        if (! $login->successful()) {
            throw new TokenException('Error al hacer login: '.$login->body());
        }
        $accessToken = $login['access_token'] ?? null;
        if (! $accessToken) {
            throw new TokenException('No se obtuvo access_token.');
        }

        // 2) Get account info
        $accountInfo = Http::withToken($accessToken)
            ->get('https://auth.dof6.com/movistarplus/api/devices/amazon.tv/users/authenticate?_='. (now()->timestamp*1000));
        if (! $accountInfo->successful()) {
            throw new TokenException('Error obteniendo account info: '.$accountInfo->body());
        }
        $accountNumber = data_get($accountInfo->json(), 'ofertas.0.accountNumber');
        if (! $accountNumber) {
            throw new TokenException('No se encontró el accountNumber.');
        }

        // 3) initData or create device
        $deviceId     = $account->device_id;
        $shouldCreate = ! $deviceId;
        if (! $shouldCreate) {
            $init = self::postInitData($accessToken, $deviceId, $accountNumber);
            if (! $init->successful()) {
                $shouldCreate = true;
            }
        }
        if ($shouldCreate) {
            $deviceId = self::createAndRegisterDevice($accessToken, $accountNumber);
            $account->device_id = $deviceId;
            $account->save();
            $init = self::postInitData($accessToken, $deviceId, $accountNumber);
            if (! $init->successful()) {
                throw new TokenException('Error activando nuevo device: '.$init->body());
            }
        }
        $accessToken = $init['accessToken'] ?? null;
        if (! $accessToken) {
            throw new TokenException('No se encontró el accessToken tras initData.');
        }

        // 4) Refresh CDN token
        $cdn = Http::withToken($accessToken)
            ->post("https://idserver.dof6.com/{$accountNumber}/devices/amazon.tv/cdn/token/refresh");
        if (! $cdn->successful()) {
            throw new TokenException('Error obteniendo cdnToken: '.$cdn->body());
        }
        $cdnToken = $cdn['access_token'] ?? null;
        if (! $cdnToken) {
            throw new TokenException('No se encontró el cdnToken en la respuesta.');
        }

        // 5) Save the CDN token
        $account->token            = $cdnToken;
        $account->token_expires_at = now()->addDay();
        $account->save();

        return [
            'deviceId' => $deviceId,
            'cdnToken' => $cdnToken,
        ];
    }

    protected static function postInitData($accessToken, $deviceId, $accountNumber)
    {
        return Http::withToken($accessToken)
            ->post("https://clientservices.dof6.com/movistarplus/amazon.tv/sdp/mediaPlayers/{$deviceId}/initData?qspVersion=ssp&version=8&status=default", [
                "accountNumber"                => $accountNumber,
                "userProfile"                  => "0",
                "streamMiscellanea"            => "HTTPS",
                "deviceType"                   => "SMARTTV_OTT",
                "deviceManufacturerProduct"    => "LG",
                "streamDRM"                    => "Widevine",
                "streamFormat"                 => "DASH",
            ]);
    }

    protected static function createAndRegisterDevice($accessToken, $accountNumber): string
    {
        $resp = Http::withToken($accessToken)
            ->post("https://auth.dof6.com/movistarplus/amazon.tv/accounts/{$accountNumber}/devices/?qspVersion=ssp");
        if (! $resp->successful()) {
            throw new TokenException('Error creando deviceId: '.$resp->body());
        }
        $newId = trim($resp->body(), '"');

        $reg = Http::withToken($accessToken)
            ->post("https://auth.dof6.com/movistarplus/amazon.tv/accounts/{$accountNumber}/devices/{$newId}?qspVersion=ssp");
        if (! $reg->successful()) {
            throw new TokenException('Error registrando deviceId: '.$reg->body());
        }

        return $newId;
    }
}
