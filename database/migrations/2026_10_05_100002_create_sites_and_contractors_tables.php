<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sites and their areas (where things happen), contractor companies, and each user's
     * home site and employer. Users are soft deleted so HSE records keep who did what.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['site_id', 'name']);
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_no', 50)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('insurance_expires_on')->nullable();
            $table->boolean('approved')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // Null site = works across all sites (HSE manager, admin).
            $table->foreignId('site_id')->nullable()->after('email')->constrained()->nullOnDelete();
            $table->foreignId('contractor_id')->nullable()->after('site_id')->constrained()->nullOnDelete();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contractor_id');
            $table->dropConstrainedForeignId('site_id');
            $table->dropSoftDeletes();
        });

        Schema::dropIfExists('contractors');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('sites');
    }
};
