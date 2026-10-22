<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OracleLowercaseTest extends TestCase
{
    public function test_tables_are_all_lowercase(): void
    {
        $tables = DB::select('SELECT table_name FROM user_tables');
        $this->assertNotEmpty($tables);

        foreach ($tables as $t) {
            $tableName = $t->table_name;
            $this->assertSame(
                strtolower($tableName),
                $tableName,
                "Table '{$tableName}' is not lowercase"
            );
        }
    }

    public function test_columns_are_all_lowercase(): void
    {
        $columns = DB::select("SELECT column_name FROM user_tab_columns WHERE table_name = 'users'");
        $this->assertNotEmpty($columns);

        foreach ($columns as $c) {
            $columnName = $c->column_name;
            $this->assertSame(
                strtolower($columnName),
                $columnName,
                "Column '{$columnName}' is not lowercase"
            );
        }
    }

    public function test_model_query_with_lowercase_columns(): void
    {
        $sql = User::where('email', 'admin@gmail.com')->toSql();

        $this->assertStringContainsString('"users"', $sql);
        $this->assertStringContainsString('"email"', $sql);

        $user = User::where('email', 'admin@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('admin@gmail.com', $user->email);
        $this->assertNotEmpty($user->roles);
    }
}
