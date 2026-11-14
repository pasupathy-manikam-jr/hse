<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Observation review: the topic and the crew involved, dismissal with a reason, and the
     * monthly "observation of the month" per site. Sites get the weekly report's mailing list.
     */
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            // Observation::TOPICS; set when reporting or on review.
            $table->string('topic', 30)->nullable()->after('type')->index();
            $table->foreignId('contractor_id')->nullable()->after('area_id')->constrained()->nullOnDelete();
            $table->text('status_reason')->nullable()->after('status');
            // The first day of the month it was chosen for; one per site and month.
            $table->date('recognised_for')->nullable();
            $table->string('recognition_note', 500)->nullable();
            $table->unique(['site_id', 'recognised_for']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->json('report_recipients')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn('report_recipients'));
        Schema::table('observations', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'recognised_for']);
            $table->dropConstrainedForeignId('contractor_id');
            $table->dropColumn(['topic', 'status_reason', 'recognised_for', 'recognition_note']);
        });
    }
};
