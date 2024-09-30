<?php

namespace App\Utils;

use Illuminate\Support\Str;

class StringUtils
{
    static function generateUniqueCode($name, $ip)
    {
        return strtoupper(Str::random(10)) . '-' . hash('sha256', $name . $ip);
    }
}
