<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The equipment register: plant and equipment that needs a periodic statutory inspection
     * (lifting gear, pressure vessels, extinguishers, scaffolds), and the equipment a permit uses.
     */
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_tag', 50)->unique();
            $table->string('name');
            $table->string('type', 30);
            $table->string('serial_number', 100)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('inspection_interval_months');
            // The latest inspection's date (kept by Equipment::recordInspection()).
            $table->date('last_inspected_on')->nullable();
            // last + interval, kept by the model; null (never inspected) is due now.
            $table->date('next_inspection_on')->nullable()->index();
            $table->string('status', 20)->default('in-service');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Every inspection / thorough examination, with its certificate (StoresUploads). Never edited.
        Schema::create('equipment_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->date('inspected_on');
            $table->boolean('passed');
            // The competent person or inspection body, often from outside the company.
            $table->string('inspector');
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('file_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('permit_equipment', function (Blueprint $table) {
            $table->foreignId('permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->primary(['permit_id', 'equipment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permit_equipment');
        Schema::dropIfExists('equipment_inspections');
        Schema::dropIfExists('equipment');
    }
};
