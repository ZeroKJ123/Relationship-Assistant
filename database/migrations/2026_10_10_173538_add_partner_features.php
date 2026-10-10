<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couples', function (Blueprint $table): void {
            $table->date('married_on')->nullable();
            $table->unsignedInteger('dates_version')->default(0);
        });
        Schema::table('mood_entries', function (Blueprint $table): void {
            $table->string('emoji', 32)->nullable();
        });
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('rating_comment')->nullable();
        });
        Schema::create('movies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->char('identity_hash', 64);
            $table->text('title');
            $table->unsignedSmallInteger('year')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'identity_hash']);
            $table->index(['user_id', 'archived_at', 'id']);
        });
        Schema::create('movie_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->char('identity_hash', 64);
            $table->boolean('liked');
            $table->timestamps();
            $table->unique(['couple_id', 'user_id', 'identity_hash']);
            $table->index(['couple_id', 'liked', 'identity_hash']);
        });
        Schema::create('cars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_method_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('operation_key');
            $table->text('name');
            $table->string('fuel', 20);
            $table->timestamps();
            $table->unique(['user_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
        Schema::dropIfExists('movie_votes');
        Schema::dropIfExists('movies');
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->dropColumn(['rating', 'rating_comment']);
        });
        Schema::table('mood_entries', function (Blueprint $table): void {
            $table->dropColumn('emoji');
        });
        Schema::table('couples', function (Blueprint $table): void {
            $table->dropColumn(['married_on', 'dates_version']);
        });
    }
};
