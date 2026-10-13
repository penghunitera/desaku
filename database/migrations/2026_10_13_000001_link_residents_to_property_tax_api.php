<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_tax_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nik_hash', 64);
            $table->decimal('total_outstanding', 15, 2)->nullable();
            $table->string('sync_status')->default('queued');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->string('error_code')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['village_id', 'sync_status']);
        });

        Schema::table('tax_objects', function (Blueprint $table) {
            $table->text('nop_encrypted')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tax_objects', fn (Blueprint $table) => $table->dropColumn('nop_encrypted'));
        Schema::dropIfExists('property_tax_snapshots');
    }
};
