<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_code_hash')->nullable()->after('email_verified_at');
            $table->timestamp('email_code_expires_at')->nullable()->after('email_code_hash');
            $table->unsignedTinyInteger('email_code_attempts')->default(0)->after('email_code_expires_at');
        });

        Schema::table('profiles', function (Blueprint $table) {
            // Rounded to 2 decimals (about 1 km) so exact home locations are never stored.
            $table->decimal('latitude', 5, 2)->nullable()->after('height_cm');
            $table->decimal('longitude', 6, 2)->nullable()->after('latitude');
            $table->timestamp('location_verified_at')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_code_hash', 'email_code_expires_at', 'email_code_attempts']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_verified_at']);
        });
    }
};
