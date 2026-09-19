<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->enum('role', ['donor', 'recipient', 'bloodbank', 'admin'])->default('recipient');
            $table->enum('blood_group', ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'])->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->boolean('availability')->default(false); // donors only
            $table->date('last_donation_date')->nullable();
            $table->unsignedInteger('donation_count')->default(0);
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['role', 'blood_group', 'availability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
