<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notification settings, Boost, device IDs for ban-evasion checks,
     * and date check-ins.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_matches')->default(true)->after('flag_reason');
            $table->boolean('notify_messages')->default(true)->after('notify_matches');
            $table->boolean('notify_likes')->default(true)->after('notify_messages');
            $table->boolean('notify_digest')->default(true)->after('notify_likes');
            $table->timestamp('last_like_email_at')->nullable()->after('notify_digest');
            $table->timestamp('last_message_email_at')->nullable()->after('last_like_email_at');
            $table->timestamp('last_boost_at')->nullable()->after('last_message_email_at');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->timestamp('boosted_until')->nullable()->index()->after('location_verified_at');
        });

        Schema::table('connection_checks', function (Blueprint $table) {
            $table->string('device_id', 64)->nullable()->index()->after('ip');
        });

        Schema::create('date_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->string('match_name', 60);
            $table->timestamp('meeting_at')->index();
            $table->string('place', 200);
            $table->string('friend_name', 60);
            $table->string('friend_email');
            $table->string('note', 300)->nullable();
            $table->timestamp('safe_at')->nullable();
            $table->timestamp('alert_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_checkins');

        Schema::table('connection_checks', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('boosted_until');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notify_matches', 'notify_messages', 'notify_likes', 'notify_digest',
                'last_like_email_at', 'last_message_email_at', 'last_boost_at',
            ]);
        });
    }
};
