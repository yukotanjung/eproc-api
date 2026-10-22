<?php

namespace App\Database\Oracle\Schema;

use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Schema\OracleBuilder as BaseOracleBuilder;

class OracleBuilder extends BaseOracleBuilder
{
    public function __construct(Oci8Connection $connection)
    {
        parent::__construct($connection);
        $this->helper = new OracleAutoIncrementHelper($connection);
        $this->comment = new Comment($connection);
    }
}
