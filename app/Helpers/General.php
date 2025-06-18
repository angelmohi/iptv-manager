<?php

namespace App\Helpers;

use App\Models\Account;

class General
{
    /**
     * Generate a unique code based on a string input and an account ID.
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
