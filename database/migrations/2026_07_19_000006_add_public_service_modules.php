<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->string('citizen_pin_hash')->nullable()->after('notes');
            $table->timestamp('citizen_last_login_at')->nullable()->after('citizen_pin_hash');
        });
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('resident_id')->constrained('users')->nullOnDelete();
            $table->text('officer_notes')->nullable()->after('form_data');
            $table->timestamp('completed_at')->nullable()->after('submitted_at');
        });
        Schema::create('village_budgets', function (Blueprint $table) {
            $table->id(); $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year'); $table->string('type', 30); $table->string('sector');
            $table->string('name'); $table->decimal('budget_amount', 18, 2); $table->decimal('realized_amount', 18, 2)->default(0);
            $table->boolean('published')->default(false); $table->timestamps();
            $table->index(['village_id', 'year', 'type']);
        });
        Schema::create('public_documents', function (Blueprint $table) {
            $table->id(); $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30); $table->string('category'); $table->string('title');
            $table->unsignedSmallInteger('year')->nullable(); $table->string('document_number')->nullable();
            $table->date('issued_at')->nullable(); $table->string('status', 30)->default('published');
            $table->text('summary')->nullable(); $table->string('file_path')->nullable();
            $table->boolean('published')->default(true); $table->timestamps();
            $table->index(['village_id', 'kind', 'year']);
        });
        Schema::create('village_articles', function (Blueprint $table) {
            $table->id(); $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title'); $table->string('slug')->unique(); $table->string('category');
            $table->text('excerpt')->nullable(); $table->longText('content'); $table->string('status', 20)->default('draft')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('village_articles'); Schema::dropIfExists('public_documents'); Schema::dropIfExists('village_budgets');
        Schema::table('service_requests', function (Blueprint $table) { $table->dropConstrainedForeignId('assigned_to'); $table->dropColumn(['officer_notes', 'completed_at']); });
        Schema::table('residents', fn (Blueprint $table) => $table->dropColumn(['citizen_pin_hash', 'citizen_last_login_at']));
    }
};
