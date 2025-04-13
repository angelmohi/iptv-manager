<?php

use Illuminate\Support\Facades\Session;

/**
 * Flash message
 */
function flashMessage(string $type, string $text) : void
{
    Session::flash('message', (object)[
        'type' => $type,
        'text' => $text,
    ]);
}

/**
 * Flash message of type 'info'
 */
function flashInfoMessage(string $text) : void
{
    flashMessage('info', $text);
}

/**
 * Flash message of type 'success'
 */
function flashSuccessMessage(string $text) : void
{
    flashMessage('success', $text);
}

/**
 * Flash message of type 'warning'
 */
function flashWarningMessage(string $text) : void
{
    flashMessage('warning', $text);
}

/**
 * Flash message of type 'danger'
 */
function flashDangerMessage(string $text) : void
{
    flashMessage('danger', $text);
}
