<?php

namespace App\Database\Oracle\Schema;

use Illuminate\Support\Str;
use Yajra\Oci8\Schema\Comment as BaseComment;

class Comment extends BaseComment
{
    /**
     * Wrap identifier in double quotes with lowercase preservation.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value): string
    {
        $value = Str::lower($value);

        return '"'.str_replace('"', '""', $value).'"';
    }
}
