<?php

namespace App\Database\Oracle\Schema;

use Yajra\Oci8\Schema\Sequence as BaseSequence;

class Sequence extends BaseSequence
{
    /**
     * Check if a sequence exists case-insensitively.
     *
     * @param  string  $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        $owner = $this->connection->getSchema();

        if (str_contains($name, '.')) {
            [$owner, $name] = explode('.', $name);
        }

        $name = $this->unquote($name);
        $owner = $this->unquote($owner);

        $sql = "select count(*) from all_sequences where upper(sequence_name) = upper('{$name}') and upper(sequence_owner) = upper('{$owner}')";

        return (bool) $this->connection->scalar($sql);
    }
}
