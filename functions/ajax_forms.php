<?php

use Illuminate\Http\JsonResponse;

/**
 * Generic JSON response
 */
function jsonResponse(mixed $data, int $code = 200) : JsonResponse
{
    return new JsonResponse($data, $code, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Validation errors
 */
function jsonValidation(array $errors) : JsonResponse
{
    return jsonResponse(['validation' => $errors], 422);
}

/**
 * Alert ($type: success, error, warning, info, question)
 */
function jsonAlert(string $type, string $message, string $redirection = null) : JsonResponse
{
    return jsonResponse(['alert' => [
        'type' => $type,
        'message' => $message,
        'redirection' => $redirection,
    ]]);
}

/**
 * Redirection
 */
function jsonRedirection(string $url) : JsonResponse
{
    return jsonResponse(['redirection' => ['url' => $url]]);
}

/**
 * Iframe redirection
 */
function jsonIframeRedirection(string $url) : JsonResponse
{
    return jsonResponse(['iframeRedirection' => ['url' => $url]]);
}

/**
 * Custom data
 */
function jsonCustom(mixed $data) : JsonResponse
{
    return jsonResponse(['custom' => $data]);
}
