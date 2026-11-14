<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Malaysian notification under OSHA 1994 s.32 and NADOPOD 2004: the dangerous occurrence
     * code, the immediate notice to DOSH, the JKKP 6 / JKKP 7 form, and serious bodily injury.
     */
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            // Incident::DANGEROUS_OCCURRENCES code (NADOPOD Second Schedule, guideline Table 6).
            $table->string('dangerous_occurrence', 3)->nullable()->after('type');
            $table->timestamp('dosh_notified_at')->nullable();
            $table->foreignId('dosh_notified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dosh_notice', 255)->nullable();
            $table->timestamp('jkkp_submitted_at')->nullable();
            $table->foreignId('jkkp_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jkkp_reference', 100)->nullable();
        });

        Schema::table('incident_people', function (Blueprint $table) {
            $table->boolean('serious_injury')->default(false)->after('treatment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incident_people', fn (Blueprint $table) => $table->dropColumn('serious_injury'));
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dosh_notified_by');
            $table->dropConstrainedForeignId('jkkp_submitted_by');
            $table->dropColumn(['dangerous_occurrence', 'dosh_notified_at', 'dosh_notice', 'jkkp_submitted_at', 'jkkp_reference']);
        });
    }
};
