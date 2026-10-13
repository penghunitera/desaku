<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('tracking_code')->nullable()->unique()->after('public_id');
            $table->timestamp('submitted_at')->nullable()->after('consented_at');
        });

        Schema::create('service_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('service_request_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_histories');
        Schema::dropIfExists('service_request_documents');
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropUnique(['tracking_code']);
            $table->dropColumn(['tracking_code', 'submitted_at']);
        });
    }
};
