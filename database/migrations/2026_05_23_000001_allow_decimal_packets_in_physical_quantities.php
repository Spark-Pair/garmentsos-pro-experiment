<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE physical_quantities MODIFY packets DECIMAL(10, 2) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE physical_quantities ALTER COLUMN packets TYPE NUMERIC(10, 2)');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE physical_quantities MODIFY packets INT NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE physical_quantities ALTER COLUMN packets TYPE INTEGER USING ROUND(packets)::INTEGER');
        }
    }
};
