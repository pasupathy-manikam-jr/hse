<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shift handovers: what the outgoing supervisor passes on (open permits and isolations
     * captured at the time, plus notes), and the incoming supervisor's acknowledgement.
     */
    public function up(): void
    {
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('shift', 10);
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes');
            // Snapshot at handover: [{number, type, status, area, valid_to, isolations: [...]}].
            $table->json('open_permits');
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
