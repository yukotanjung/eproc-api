<?php

namespace App\Database\Oracle\Schema;

use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Schema\OracleAutoIncrementHelper as BaseHelper;
use Yajra\Oci8\Schema\Trigger;

class OracleAutoIncrementHelper extends BaseHelper
{
    public function __construct(protected Oci8Connection $connection)
    {
        $this->sequence = new Sequence($connection);
        $this->trigger = new Trigger($connection);
    }
}
