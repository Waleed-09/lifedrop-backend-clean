<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city', 100)->nullable()->after('address');
            }
            if (! Schema::hasColumn('users', 'district')) {
                $table->string('district', 100)->nullable()->after('city');
            }
            if (! Schema::hasColumn('users', 'is_available')) {
                $table->boolean('is_available')->default(true)->after('availability');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('users', 'city')) {
                $drops[] = 'city';
            }
            if (Schema::hasColumn('users', 'district')) {
                $drops[] = 'district';
            }
            if (Schema::hasColumn('users', 'is_available')) {
                $drops[] = 'is_available';
            }
            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
