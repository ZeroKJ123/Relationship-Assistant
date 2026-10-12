<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('bio')->nullable();
        });
        foreach (['chore_draws', 'movie_choices'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('spin_started_at')->nullable();
            });
        }
        Schema::table('mood_prompts', function (Blueprint $table): void {
            $table->boolean('is_self_requested')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('mood_prompts', function (Blueprint $table): void {
            $table->dropColumn('is_self_requested');
        });
        foreach (['chore_draws', 'movie_choices'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('spin_started_at');
            });
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('bio');
        });
    }
};
