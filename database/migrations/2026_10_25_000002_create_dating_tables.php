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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 40);
            $table->string('gender', 20);
            $table->string('seeking', 20);
            $table->string('relationship_goal', 30)->nullable();
            $table->text('bio')->nullable();
            $table->string('city', 80);
            $table->string('state', 2)->index();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('photo_verified_at')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('show_last_active')->default(true);
            $table->timestamps();
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('path_sm');
            $table->unsignedTinyInteger('position')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('selfie_path')->nullable();
            $table->string('gesture');
            $table->string('status', 20)->default('pending')->index();
            $table->string('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('liked');
            $table->timestamps();

            $table->unique(['user_id', 'target_id']);
            $table->index(['target_id', 'liked']);
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_one_id', 'user_two_id']);
            $table->index('user_two_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('flag')->nullable()->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['match_id', 'id']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'blocked_id']);
            $table->index('blocked_id');
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->string('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('likes');
        Schema::dropIfExists('verification_requests');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('profiles');
    }
};
