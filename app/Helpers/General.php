<?php

namespace App\Helpers;

use App\Models\Account;

class General
{
    /**
     * Genera un código alfanumérico de 5 caracteres a partir de una cadena.
     * Siempre que la cadena sea la misma, el código será el mismo.
     *
     * @param  string  $input
     * @return string
     */
    public static function codeFromString(string $input, Account $account): string
    {
        $hashInt = crc32($input);
        $base36 = strtoupper(base_convert($hashInt, 10, 36));
        $padded = str_pad($base36, 5, '0', STR_PAD_LEFT);
        
        return substr($padded, 0, 5) . $account->id;
    }
}
