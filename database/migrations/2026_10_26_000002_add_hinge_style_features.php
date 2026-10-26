<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profile details, prompts, comment likes, profile views, favorites,
     * unmatching and "We met" feedback.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Vitals (keys from config('dating.vitals'))
            $table->string('job_title', 80)->nullable()->after('height_cm');
            $table->string('school', 80)->nullable()->after('job_title');
            $table->string('education', 30)->nullable()->after('school');
            $table->string('religion', 30)->nullable()->after('education');
            $table->string('politics', 30)->nullable()->after('religion');
            $table->string('has_kids', 30)->nullable()->after('politics');
            $table->string('wants_kids', 30)->nullable()->after('has_kids');
            $table->string('drinking', 30)->nullable()->after('wants_kids');
            $table->string('smoking', 30)->nullable()->after('drinking');
            $table->json('interests')->nullable()->after('smoking');

            // Preferences + dealbreakers
            $table->unsignedTinyInteger('pref_age_min')->nullable()->after('interests');
            $table->unsignedTinyInteger('pref_age_max')->nullable()->after('pref_age_min');
            $table->unsignedSmallInteger('pref_max_miles')->nullable()->after('pref_age_max');
            $table->json('dealbreakers')->nullable()->after('pref_max_miles');
            $table->json('pref_values')->nullable()->after('dealbreakers');

            $table->boolean('browse_privately')->default(false)->after('show_last_active');
        });

        Schema::create('profile_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('prompt', 60);
            $table->string('answer', 300);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'position']);
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->string('comment', 300)->nullable()->after('liked');
            $table->foreignId('photo_id')->nullable()->after('comment')->constrained()->nullOnDelete();
            $table->foreignId('prompt_id')->nullable()->after('photo_id')->constrained('profile_prompts')->nullOnDelete();
            $table->boolean('is_spark')->default(false)->after('prompt_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('unmatched_by')->nullable()->after('last_message_at')->constrained('users')->nullOnDelete();
            $table->timestamp('unmatched_at')->nullable()->after('unmatched_by');
        });

        Schema::create('profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('viewed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['viewer_id', 'viewed_id']);
            $table->index(['viewed_id', 'viewed_at']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('favorite_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'favorite_id']);
        });

        Schema::create('date_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('met');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_feedback');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('profile_views');

        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unmatched_by');
            $table->dropColumn('unmatched_at');
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photo_id');
            $table->dropConstrainedForeignId('prompt_id');
            $table->dropColumn(['comment', 'is_spark']);
        });

        Schema::dropIfExists('profile_prompts');

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'job_title', 'school', 'education', 'religion', 'politics', 'has_kids', 'wants_kids',
                'drinking', 'smoking', 'interests', 'pref_age_min', 'pref_age_max', 'pref_max_miles',
                'dealbreakers', 'pref_values', 'browse_privately',
            ]);
        });
    }
};
