<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->string('case_code')->unique();
            $table->string('status')->default('suspected')->index();
            $table->date('screened_at');
            $table->date('treatment_started_at')->nullable();
            $table->date('treatment_due_at')->nullable();
            $table->date('next_visit_at')->nullable()->index();
            $table->string('referral_facility')->default('Puskesmas Borbor');
            $table->text('clinical_data_encrypted')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['village_id', 'resident_id']);
        });

        Schema::create('tb_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tb_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->string('relationship')->nullable();
            $table->string('screening_status')->default('pending')->index();
            $table->boolean('priority')->default(false);
            $table->date('screened_at')->nullable();
            $table->string('tpt_status')->default('not_assessed');
            $table->text('notes_encrypted')->nullable();
            $table->timestamps();
            $table->unique(['tb_case_id', 'resident_id']);
        });

        Schema::create('tb_treatment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tb_case_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_at');
            $table->string('adherence')->default('taken')->index();
            $table->string('visit_type')->default('medicine');
            $table->text('notes_encrypted')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_treatment_logs');
        Schema::dropIfExists('tb_contacts');
        Schema::dropIfExists('tb_cases');
    }
};
