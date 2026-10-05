<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Risk assessments (revisioned, with scored hazards) and checklist inspections
     * (templates, plus inspections that copy the questions they were run against).
     */
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            // Shared by every revision of the same assessment.
            $table->string('number', 20)->index();
            $table->unsignedSmallInteger('revision')->default(1);
            $table->foreignId('previous_id')->nullable()->constrained('risk_assessments')->nullOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->text('activity');
            $table->date('review_due_on');
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('review_required')->default(false);
            $table->string('review_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['number', 'revision']);
        });

        Schema::create('risk_hazards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('hazard');
            $table->string('who_at_risk')->nullable();
            $table->text('existing_controls')->nullable();
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('severity');
            $table->text('additional_controls')->nullable();
            $table->unsignedTinyInteger('residual_likelihood');
            $table->unsignedTinyInteger('residual_severity');
            $table->timestamps();
        });

        Schema::table('incidents', function (Blueprint $table) {
            // The assessment that should have controlled this; linking flags it for review.
            $table->foreignId('risk_assessment_id')->nullable()->after('observation_id')->constrained()->nullOnDelete();
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->string('response_type', 10);
            $table->decimal('min', 10, 2)->nullable();
            $table->decimal('max', 10, 2)->nullable();
            $table->boolean('critical')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('checklist_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_name');
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('in-progress')->index();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inspection_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            // A copy of the checklist item, so later template edits never change this record.
            $table->string('question');
            $table->string('response_type', 10);
            $table->decimal('min', 10, 2)->nullable();
            $table->decimal('max', 10, 2)->nullable();
            $table->boolean('critical')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('answer')->nullable();
            // Null for N/A and free-text answers, which neither pass nor fail.
            $table->boolean('passed')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_answers');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_templates');
        Schema::table('incidents', fn (Blueprint $table) => $table->dropConstrainedForeignId('risk_assessment_id'));
        Schema::dropIfExists('risk_hazards');
        Schema::dropIfExists('risk_assessments');
    }
};
