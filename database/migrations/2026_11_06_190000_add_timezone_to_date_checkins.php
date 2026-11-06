<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('date_checkins', function (Blueprint $table) {
            $table->string('timezone', 64)->default('America/Chicago')->after('meeting_at');
        });
    }

    public function down(): void
    {
        Schema::table('date_checkins', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
