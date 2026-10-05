<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A phone-generated id per report, so a report queued offline and sent twice (the first reply
     * lost) is stored once.
     */
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->uuid('client_ref')->nullable()->unique()->after('number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->dropUnique(['client_ref']);
            $table->dropColumn('client_ref');
        });
    }
};
