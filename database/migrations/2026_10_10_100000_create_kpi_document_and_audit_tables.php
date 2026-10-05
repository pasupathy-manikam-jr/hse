<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monthly site figures (hours worked for injury rates, environmental quantities), ISO 45001 clauses, controlled documents with revisions
     * and read-and-acknowledge, and internal audits with findings.
     */
    public function up(): void
    {
        Schema::create('site_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // The first day of the month the figure is for.
            $table->date('month');
            // SiteMetric::METRICS key: hours-worked, general-waste, water...
            $table->string('metric', 30);
            $table->decimal('value', 14, 2);
            $table->timestamps();

            $table->unique(['site_id', 'month', 'metric']);
        });

        Schema::create('iso_clauses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 10)->unique();
            $table->string('title');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('title');
            $table->string('type', 30);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('review_interval_months')->default(12);
            $table->date('next_review_on')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_iso_clause', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('iso_clause_id')->constrained()->cascadeOnDelete();
            $table->primary(['document_id', 'iso_clause_id']);
        });

        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('revision', 10);
            $table->text('change_summary')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('file_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'revision']);
        });

        Schema::create('document_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['document_revision_id', 'user_id']);
        });

        Schema::create('internal_audits', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('scope')->nullable();
            $table->foreignId('lead_auditor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_on');
            $table->string('status', 20)->default('planned')->index();
            $table->text('summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('internal_audit_iso_clause', function (Blueprint $table) {
            $table->foreignId('internal_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('iso_clause_id')->constrained()->cascadeOnDelete();
            $table->primary(['internal_audit_id', 'iso_clause_id']);
        });

        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_audit_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->foreignId('iso_clause_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            // The corrective action a nonconformity raised.
            $table->foreignId('action_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('internal_audit_iso_clause');
        Schema::dropIfExists('internal_audits');
        Schema::dropIfExists('document_acknowledgements');
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('document_iso_clause');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('iso_clauses');
        Schema::dropIfExists('site_metrics');
    }
};
