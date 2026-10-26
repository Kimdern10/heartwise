<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding wizard, Google/Apple sign-in, site settings and
     * connection (VPN / location) checks.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable()->after('terms_accepted_at');
            $table->string('flag_reason')->nullable()->after('status_reason');
        });

        // Anyone who already has a profile has effectively finished onboarding.
        DB::table('users')->whereIn('id', DB::table('profiles')->select('user_id'))->update(['onboarded_at' => now()]);

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_id');
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('connection_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->index();
            $table->string('country', 2)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('ip_timezone', 64)->nullable();
            $table->string('browser_timezone', 64)->nullable();
            $table->string('isp', 120)->nullable();
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_hosting')->default(false);
            $table->boolean('timezone_mismatch')->default(false);
            $table->boolean('blocked')->default(false)->index();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_checks');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('social_accounts');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarded_at', 'flag_reason']);
        });
    }
};
