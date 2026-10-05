<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Observations and near misses, photos attached to any record, and the one corrective
     * action register that every module feeds.
     */
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            // No uploader column: an anonymous report's photos must not identify the reporter.
            $table->morphs('photoable');
            $table->string('path');
            $table->string('sha256', 64);
            $table->unsignedInteger('size');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('potential', 10);
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->dateTime('observed_at');
            $table->boolean('anonymous')->default(false);
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->morphs('source');
            // Copied from the source record so the register can be scoped by site.
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->string('control_level', 20);
            $table->string('priority', 10);
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->date('due_on');
            $table->string('status', 20)->default('open')->index();
            $table->text('completion_notes')->nullable();
            $table->dateTime('done_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actions');
        Schema::dropIfExists('observations');
        Schema::dropIfExists('photos');
    }
};
