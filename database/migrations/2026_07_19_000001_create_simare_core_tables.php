<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('district');
            $table->string('regency');
            $table->string('province');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->text('nik_encrypted')->nullable();
            $table->string('nik_hash', 64)->nullable()->index();
            $table->string('family_card_hash', 64)->nullable()->index();
            $table->string('full_name');
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('hamlet')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->string('service_type');
            $table->string('status')->default('draft')->index();
            $table->string('destination_agency')->nullable();
            $table->json('form_data')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();
        });

        Schema::create('health_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('indicator');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('health_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('risk_level')->default('unknown')->index();
            $table->date('observed_at');
            $table->json('measurements')->nullable();
            $table->text('notes_encrypted')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nop_hash', 64)->index();
            $table->decimal('land_area', 12, 2)->default(0);
            $table->decimal('building_area', 12, 2)->default(0);
            $table->decimal('pbb_due', 15, 2)->default(0);
            $table->decimal('estimated_bphtb', 15, 2)->default(0);
            $table->string('payment_status')->default('unknown')->index();
            $table->timestamps();
        });

        Schema::create('bumdes_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category')->default('livestock');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->json('livestock_profile')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total', 15, 2);
            $table->string('status')->default('pending')->index();
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('bumdes_products');
        Schema::dropIfExists('tax_objects');
        Schema::dropIfExists('health_observations');
        Schema::dropIfExists('health_programs');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('residents');
        Schema::dropIfExists('villages');
    }
};
