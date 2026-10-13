<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local SQLite imports create these tables and views in import_dpt.py.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable('dpt')) {
            Schema::create('dpt', function (Blueprint $table) {
                $table->collation = 'utf8mb4_bin';
                $table->bigInteger('iddpt')->primary();
                foreach (['kabupaten', 'kecamatan', 'desa_kelurahan'] as $column) {
                    $table->string($column, 191)->nullable();
                }
                foreach (['tps', 'nama', 'jenis_kelamin', 'usia', 'desakelurahan', 'rt', 'rw', 'created_at', 'created_by', 'updated_at', 'updated_by', 'nowa'] as $column) {
                    $table->text($column)->nullable();
                }
                $table->index(['kabupaten', 'kecamatan', 'desa_kelurahan'], 'dpt_wilayah_index');
            });
        }

        if (! Schema::hasTable('dpt_imports')) {
            Schema::create('dpt_imports', function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->primary();
                $table->text('source_filename');
                $table->string('source_sha256', 64)->unique();
                $table->text('source_database')->nullable();
                $table->text('source_generated_at')->nullable();
                $table->text('imported_at');
                $table->unsignedBigInteger('row_count');
                $table->unsignedBigInteger('insert_statement_count');
                $table->string('rows_sha256', 64);
                $table->text('backup_path');
            });
            DB::statement('ALTER TABLE dpt_imports ADD CONSTRAINT dpt_imports_singleton CHECK (id = 1)');
        }

        $sql = file_get_contents(database_path('queries/dpt_potensi.sql'));
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = str_replace([
            'CREATE VIEW ', "TRIM(usia) NOT GLOB '*[^0-9]*'", 'CAST(TRIM(usia) AS INTEGER)',
        ], [
            'CREATE OR REPLACE VIEW ', "TRIM(usia) REGEXP '^[0-9]+$'", 'CAST(TRIM(usia) AS UNSIGNED)',
        ], $sql);
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                DB::statement($statement);
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS dpt_potensi_desa');
        DB::statement('DROP VIEW IF EXISTS dpt_demografi');
        Schema::dropIfExists('dpt_imports');
        Schema::dropIfExists('dpt');
    }
};
