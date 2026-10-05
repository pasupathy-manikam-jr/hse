<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OSHA 300 column M (illness type) and privacy cases (1904.29(b)(7): the name is withheld on the log).
     */
    public function up(): void
    {
        Schema::table('incident_people', function (Blueprint $table) {
            $table->string('illness_type', 20)->nullable()->after('treatment');
            $table->boolean('privacy_case')->default(false)->after('illness_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incident_people', function (Blueprint $table) {
            $table->dropColumn(['illness_type', 'privacy_case']);
        });
    }
};
