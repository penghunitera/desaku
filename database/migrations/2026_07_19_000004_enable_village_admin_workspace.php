<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('village_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('operator')->after('password')->index();
            $table->boolean('active')->default(true)->after('role');
        });

        Schema::create('village_potentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('category');
            $table->string('name');
            $table->decimal('value', 18, 2)->default(0);
            $table->string('unit', 40);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'year', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('village_potentials');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('village_id');
            $table->dropColumn(['role', 'active']);
        });
    }
};
