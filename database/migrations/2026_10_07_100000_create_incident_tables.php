<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Incidents, the people involved in each, and the investigation (kept on the incident:
     * there is exactly one per incident).
     */
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('observation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->text('description');
            $table->text('immediate_actions')->nullable();
            $table->dateTime('occurred_at')->index();
            // Derived from the worst treatment among the people involved; stored for filtering and the log.
            $table->string('classification', 20)->default('no-injury');
            $table->boolean('recordable')->default(false);
            $table->string('status', 30)->default('reported')->index();

            $table->text('investigation_team')->nullable();
            $table->text('sequence_of_events')->nullable();
            $table->json('whys')->nullable();
            $table->string('root_cause_category', 30)->nullable();
            $table->text('root_cause')->nullable();
            $table->text('contributing_factors')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('incident_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('job_title')->nullable();
            $table->string('role', 20);
            $table->string('treatment', 20)->nullable();
            $table->string('body_part')->nullable();
            $table->string('injury_nature')->nullable();
            $table->unsignedSmallInteger('days_lost')->default(0);
            $table->unsignedSmallInteger('days_restricted')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_people');
        Schema::dropIfExists('incidents');
    }
};
