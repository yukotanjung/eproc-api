<?php

namespace App\Database\Oracle\Schema\Grammars;

use Illuminate\Support\Str;
use Yajra\Oci8\Schema\Grammars\OracleGrammar as BaseSchemaGrammar;

class OracleGrammar extends BaseSchemaGrammar
{
    /**
     * The keyword identifier wrapper format.
     */
    protected string $wrapper = '"%s"';

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
     * Compile the SQL needed to drop all tables and sequences safely.
     *
     * @return string
     */
    public function compileDropAllTables(): string
    {
        return 'BEGIN
            FOR c IN (SELECT table_name FROM user_tables WHERE secondary = \'N\') LOOP
                EXECUTE IMMEDIATE (\'DROP TABLE "\' || c.table_name || \'" CASCADE CONSTRAINTS\');
            END LOOP;

            FOR s IN (SELECT sequence_name FROM user_sequences) LOOP
                EXECUTE IMMEDIATE (\'DROP SEQUENCE "\' || s.sequence_name || \'"\');
            END LOOP;
        END;';
    }
}
