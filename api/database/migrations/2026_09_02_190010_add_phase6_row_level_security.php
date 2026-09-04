<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected array $tables = [
        'announcements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                USING (
                    current_setting('app.current_tenant_id', true) IS NULL
                    OR current_setting('app.current_tenant_id', true) = ''
                    OR tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint
                )
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
