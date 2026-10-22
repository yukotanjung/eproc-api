<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class SumUniqueInTable implements Rule
{
    protected $table;
    protected $column;

    public function __construct($table, $column = 'id')
    {
        $this->table = $table;
        $this->column = $column;
    }

    public function passes($attribute, $value)
    {
        $originChecksum = md5_file($value->getRealPath());
        return !DB::table($this->table)
            ->where($this->column, $originChecksum)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function message()
    {
        return 'File already exists.';
    }
}
