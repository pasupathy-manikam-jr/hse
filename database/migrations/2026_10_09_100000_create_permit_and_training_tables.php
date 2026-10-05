<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Electronic signatures, competencies and training records, toolbox talks, and permits to
     * work with their workers, gas tests and isolations (LOTO).
     */
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->morphs('signable');
            $table->string('meaning', 50);
            $table->string('signer_name');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('signed_at')->useCurrent();
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Null: does not expire.
            $table->unsignedSmallInteger('validity_months')->nullable();
            // The permit type that requires it of every worker on the permit.
            $table->string('permit_type', 20)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('user_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->date('issued_on');
            $table->date('expires_on')->nullable();
            $table->string('reference')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('toolbox_talks', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('topic');
            $table->text('notes')->nullable();
            $table->date('held_on');
            $table->foreignId('presenter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('toolbox_talk_attendees', function (Blueprint $table) {
            $table->foreignId('toolbox_talk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['toolbox_talk_id', 'user_id']);
        });

        Schema::create('permits', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('type', 20);
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('risk_assessment_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            // Precaution key => checked, from Permit::PRECAUTIONS for the type.
            $table->json('precautions')->nullable();
            $table->string('status', 20)->default('requested')->index();
            $table->text('status_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('permit_workers', function (Blueprint $table) {
            $table->foreignId('permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['permit_id', 'user_id']);
        });

        Schema::create('permit_gas_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_id')->constrained()->cascadeOnDelete();
            $table->decimal('oxygen', 5, 2);
            $table->decimal('lel', 5, 2);
            $table->decimal('h2s', 6, 2);
            $table->decimal('co', 6, 2);
            $table->boolean('passed');
            $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('tested_at');
            $table->timestamps();
        });

        Schema::create('permit_isolations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_id')->constrained()->cascadeOnDelete();
            $table->string('point');
            $table->string('method');
            $table->string('lock_no', 50)->nullable();
            $table->foreignId('isolated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('isolated_at');
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('removed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permit_isolations');
        Schema::dropIfExists('permit_gas_tests');
        Schema::dropIfExists('permit_workers');
        Schema::dropIfExists('permits');
        Schema::dropIfExists('toolbox_talk_attendees');
        Schema::dropIfExists('toolbox_talks');
        Schema::dropIfExists('user_competencies');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('signatures');
    }
};
