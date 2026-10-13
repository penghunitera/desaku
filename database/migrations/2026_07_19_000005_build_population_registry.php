<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->text('number_encrypted');
            $table->string('number_hash', 64)->index();
            $table->string('address')->nullable();
            $table->string('hamlet')->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('rt', 5)->nullable();
            $table->date('issued_at')->nullable();
            $table->string('social_classification')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['village_id', 'number_hash']);
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->foreignId('family_card_id')->nullable()->after('village_id')->constrained()->nullOnDelete();
            $table->string('family_relationship')->nullable()->after('family_card_hash');
            $table->string('gender', 20)->nullable()->after('full_name');
            $table->string('birth_place')->nullable()->after('gender');
            $table->string('religion', 30)->nullable()->after('birth_date');
            $table->string('marital_status', 30)->nullable()->after('religion');
            $table->string('education')->nullable()->after('marital_status');
            $table->string('occupation')->nullable()->after('education');
            $table->string('resident_status', 30)->default('tetap')->after('occupation');
            $table->string('blood_type', 5)->nullable()->after('resident_status');
            $table->string('email')->nullable()->after('phone');
            $table->string('address')->nullable()->after('email');
            $table->string('rw', 5)->nullable()->after('hamlet');
            $table->string('rt', 5)->nullable()->after('rw');
            $table->text('notes')->nullable()->after('rt');
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('family_card_id');
            $table->dropColumn(['family_relationship', 'gender', 'birth_place', 'religion', 'marital_status', 'education', 'occupation', 'resident_status', 'blood_type', 'email', 'address', 'rw', 'rt', 'notes']);
        });
        Schema::dropIfExists('family_cards');
    }
};
