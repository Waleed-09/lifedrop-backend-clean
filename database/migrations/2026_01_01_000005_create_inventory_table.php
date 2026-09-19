<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blood_bank_id')->constrained('users')->cascadeOnDelete();
            $table->enum('blood_group', ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']);
            $table->unsignedInteger('units_available')->default(0);
            $table->timestamps();

            $table->unique(['blood_bank_id', 'blood_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
