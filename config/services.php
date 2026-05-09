<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'api_token_url' => env('API_TOKEN_URL'),
    'api_pssh_url' => env('API_PSSH_URL'),
    'api_search_url' => env('API_SEARCH_URL'),

    'difusion_url' => env('DIFUSION_URL'),

    'iptv' => [
        'token_account' => env('IPTV_TOKEN_ACCOUNT'),
    ],

    'ipinfo' => [
        'token' => env('IPINFO_TOKEN'),
    ],

    'tmdb' => [
        'key'       => env('TMDB_API_KEY'),
        'base_url'  => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
        'image_url' => env('TMDB_IMAGE_URL', 'https://image.tmdb.org/t/p'),
        'language'  => env('TMDB_LANGUAGE', 'es-ES'),
        'delay_ms'  => (int) env('TMDB_DELAY_MS', 250),
    ],

    'omdb' => [
        'key'      => env('OMDB_API_KEY'),
        'base_url' => env('OMDB_BASE_URL', 'https://www.omdbapi.com/'),
    ],

    'tvmaze' => [
        'base_url' => env('TVMAZE_BASE_URL', 'https://api.tvmaze.com'),
    ],

];
