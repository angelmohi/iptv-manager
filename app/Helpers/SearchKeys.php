<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Exception;

class SearchKeys
{
    /**
     * Get DRM keys from external API based on PSSH.
     *
     * @param string $pssh
     * @return string|null
     */
    public static function getKeys(string $pssh): ?string
    {
        $apiUrl = config('services.api_search_url');

        if (!$apiUrl) {
            return null;
        }

        try {
            $response = Http::timeout(120)->get($apiUrl, [
                'input' => $pssh
            ]);

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();

            if (!is_array($data) || empty($data)) {
                return null;
            }

            $keys = [];
            foreach ($data as $item) {
                if (!empty($item['KID']) && !empty($item['Key'])) {
                    $keys[] = "{$item['KID']}:{$item['Key']}";
                }
            }

            if (empty($keys)) {
                return null;
            }

            return '{' . implode(',', $keys) . '}';

        } catch (Exception $e) {
            return null;
        }
    }
}
