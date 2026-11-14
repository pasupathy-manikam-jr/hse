<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Health surveillance (USECHH Regulations 2000; Noise Exposure Regulations 2019): programmes
     * per exposure, the workers in them, their examinations, and exposure monitoring.
     * Medical records: kept 30 years (USECHH reg. 27(4)), so nothing here is deleted in the app.
     */
    public function up(): void
    {
        Schema::create('health_programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('name');
            // HealthProgramme::HAZARDS: noise or chemical.
            $table->string('hazard', 20);
            $table->foreignId('chemical_id')->nullable()->constrained()->nullOnDelete();
            // A USECHH Schedule II chemical: medical surveillance at least every 12 months, monitoring every 6.
            $table->boolean('schedule_ii')->default(false);
            $table->unsignedTinyInteger('exam_interval_months')->default(12);
            $table->unsignedTinyInteger('monitoring_interval_months')->nullable();
            $table->decimal('exposure_limit', 10, 3)->nullable();
            $table->string('unit', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('health_programme_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_programme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // When the exposure began: the baseline audiogram is due within 3 months of it.
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();
            $table->unique(['health_programme_id', 'user_id']);
        });

        Schema::create('health_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_programme_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->date('examined_on');
            // The audiometric test centre or occupational health doctor.
            $table->string('provider');
            $table->string('outcome', 30);
            $table->text('notes')->nullable();
            $table->date('informed_on')->nullable();
            $table->date('dosh_notified_on')->nullable();
            $table->string('dosh_reference', 100)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('file_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['health_programme_id', 'user_id', 'examined_on']);
        });

        Schema::create('exposure_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_programme_id')->constrained()->restrictOnDelete();
            $table->date('measured_on');
            $table->decimal('value', 10, 3);
            // A personal reading (a dosimeter on a named worker) rather than an area one.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The noise risk assessor or hygiene technician.
            $table->string('assessor');
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('file_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exposure_readings');
        Schema::dropIfExists('health_exams');
        Schema::dropIfExists('health_programme_members');
        Schema::dropIfExists('health_programmes');
    }
};
