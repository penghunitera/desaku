<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_tax_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_object_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->decimal('assessed_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status')->default('estimated')->index();
            $table->date('due_date')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['tax_object_id', 'tax_year']);
            $table->index(['tax_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_tax_assessments');
    }
};
