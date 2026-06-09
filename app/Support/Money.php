<?php

namespace App\Support;

class Money
{
    public static function format(?int $cents): string
    {
        return '¥'.number_format(($cents ?? 0) / 100, 2);
    }
}
