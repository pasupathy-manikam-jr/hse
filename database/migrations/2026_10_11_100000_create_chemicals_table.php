<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The chemical (hazardous substance) register: what is stored where, its GHS hazards, its
     * safety data sheet and the COSHH assessment covering its use.
     */
    public function up(): void
    {
        Schema::create('chemicals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('supplier')->nullable();
            $table->string('product_code', 100)->nullable();
            // GHS pictogram keys (Chemical::HAZARDS).
            $table->json('hazards')->nullable();
            $table->decimal('max_quantity', 10, 2)->nullable();
            $table->string('unit', 10)->nullable();
            $table->date('sds_issued_on')->nullable();
            $table->foreignId('risk_assessment_id')->nullable()->constrained()->nullOnDelete();
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
        Schema::dropIfExists('chemicals');
    }
};
