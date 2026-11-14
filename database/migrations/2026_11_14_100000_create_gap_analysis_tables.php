<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ISO 45001 gap analyses: a site's self-assessment against every clause, rated with evidence.
     */
    public function up(): void
    {
        Schema::create('gap_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->date('assessed_on');
            $table->string('status', 20)->default('in-progress');
            $table->text('summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gap_analysis_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gap_analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('iso_clause_id')->constrained()->restrictOnDelete();
            // GapAnalysisItem::RATINGS; null until assessed.
            $table->string('rating', 20)->nullable();
            $table->text('evidence')->nullable();
            $table->timestamps();
            $table->unique(['gap_analysis_id', 'iso_clause_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gap_analysis_items');
        Schema::dropIfExists('gap_analyses');
    }
};
