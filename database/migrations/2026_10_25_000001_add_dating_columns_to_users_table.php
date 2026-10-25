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
            $table->date('date_of_birth')->nullable()->after('email');
            $table->timestamp('terms_accepted_at')->nullable()->after('date_of_birth');
            $table->boolean('is_admin')->default(false)->after('terms_accepted_at');
            $table->string('status')->default('active')->index()->after('is_admin');
            $table->timestamp('suspended_until')->nullable()->after('status');
            $table->string('status_reason')->nullable()->after('suspended_until');
            $table->timestamp('last_active_at')->nullable()->index()->after('status_reason');
            $table->boolean('is_sample')->default(false)->after('last_active_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth', 'terms_accepted_at', 'is_admin', 'status',
                'suspended_until', 'status_reason', 'last_active_at', 'is_sample',
            ]);
        });
    }
};
