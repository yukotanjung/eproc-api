<?php

namespace App\Rules\Tenant;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ExistsInTable implements Rule
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
        // Check if the role ID exists in the auth.roles table
        return DB::table($this->table)
            ->where($this->column, $value)
            ->exists();
    }

    public function message()
    {
        return 'ID is invalid.';
    }
}
