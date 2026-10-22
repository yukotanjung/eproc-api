<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IsUniqueOtherRule implements Rule
{
    var $table;
    var $column, $exept_id, $message;
    var $value;

    public function __construct($table, $column, $exept_id, $message = "")
    {
        $this->column = $column;
        $this->exept_id = $exept_id;
        $this->table = $table;
        $this->message = $message;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->value = $value;
        if (!empty($value)) {
            $result = DB::table($this->table)
                ->where($this->column, $value)
                ->where('id', '<>', $this->exept_id)
                ->when(Schema::hasColumn($this->table, 'deleted_at'), function ($q){
                    $q->whereNull('deleted_at');
                })
                ->first();
            if ($result && $result->id != $this->exept_id) return false;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        if (!empty($this->message)) {
            return $this->message;
        } else {
            return "Error, {$this->value} is already exist, please select or input others.";
        }
    }
}
