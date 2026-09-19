<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blood_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->enum('blood_group', ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']);
            $table->unsignedTinyInteger('units');
            $table->string('hospital');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->enum('urgency', ['normal', 'urgent', 'critical'])->default('normal');
            $table->enum('status', ['open', 'matched', 'fulfilled', 'cancelled'])->default('open');
            $table->timestamps();

            $table->index(['status', 'blood_group', 'urgency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blood_requests');
    }
};
