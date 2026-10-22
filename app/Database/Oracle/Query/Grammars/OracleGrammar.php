<?php

namespace App\Database\Oracle\Query\Grammars;

use Illuminate\Support\Str;
use Yajra\Oci8\Query\Grammars\OracleGrammar as BaseOracleGrammar;

class OracleGrammar extends BaseOracleGrammar
{
    /**
     * Wrap a single string in keyword identifiers with lowercase preservation.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value): string
    {
        if ($value === '*') {
            return $value;
        }

        $value = Str::lower($value);

        return '"'.str_replace('"', '""', $value).'"';
    }

    /**
     * Wrap a table in keyword identifiers with lowercase preservation.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  string|null  $prefix
     * @return string
     */
    public function wrapTable($table, $prefix = null): string
    {
        if ($this->isExpression($table)) {
            return $this->getValue($table);
        }

        $prefix ??= $this->connection->getTablePrefix();

        if (str_contains(strtolower($table), ' as ')) {
            return $this->wrapAliasedTable($table, $prefix);
        }

        $tableName = $this->wrap($prefix.$table);
        $segments = explode(' ', $table);
        if (count($segments) > 1) {
            $tableName = $this->wrap($prefix.$segments[0]).' '.$this->wrapValue($prefix.$segments[1]);
        }

        if ($this->connection->getSchemaPrefix()) {
            return $this->wrap($this->getSchemaPrefix()).'.'.$tableName;
        }

        return $tableName;
    }
}
