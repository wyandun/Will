<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Post interactions — replaces the old post_likes, post_comments, post_shares tables.
     * One unified table with a type discriminator.
     *
     * For type='comment', the `content` column holds the comment text.
     * For type='like' and type='share', `content` is null.
     *
     * A user can only like a post once (enforced by unique constraint on post_id + user_id + type='like').
     * Comments and shares allow multiple rows.
     */
    public function up(): void
    {
        Schema::create('post_interactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')
                ->constrained('posts')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type', 20)->comment('like | comment | share');

            // Only populated for type='comment'
            $table->text('content')->nullable();

            $table->timestamps();

            $table->index(['post_id', 'type']);
            $table->index('user_id');

            // Prevent duplicate likes from the same user on the same post
            // (comments and shares are excluded from this constraint)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_interactions');
    }
};
