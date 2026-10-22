<?php

namespace App\Database\Oracle;

use App\Database\Oracle\Query\Grammars\OracleGrammar as QueryGrammar;
use App\Database\Oracle\Schema\Grammars\OracleGrammar as SchemaGrammar;
use App\Database\Oracle\Schema\OracleBuilder;
use App\Database\Oracle\Schema\Sequence;
use Yajra\Oci8\Oci8Connection as BaseOci8Connection;

class OracleConnection extends BaseOci8Connection
{
    /**
     * Get the default query grammar instance.
     */
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        return new QueryGrammar($this);
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return new SchemaGrammar($this);
    }

    /**
     * Get a schema builder instance for the connection.
     */
    public function getSchemaBuilder(): OracleBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new OracleBuilder($this);
    }

    /**
     * Get the oracle sequence class.
     */
    public function getSequence(): Sequence
    {
        return new Sequence($this);
    }
}
