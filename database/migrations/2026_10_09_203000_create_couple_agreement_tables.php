<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couple_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('current_version');
            $table->unsignedInteger('active_version')->nullable();
            $table->unsignedSmallInteger('active_weekly_limit')->nullable();
            $table->timestamps();
        });
        Schema::create('agreement_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_agreement_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('weekly_limit');
            $table->timestamps();
            $table->unique(['couple_agreement_id', 'user_id']);
        });
        Schema::create('agreement_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_agreement_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedSmallInteger('weekly_limit');
            $table->foreignId('proposed_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20); // Fits the proposal status enum values.
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['couple_agreement_id', 'version']);
        });
        Schema::create('agreement_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agreement_proposal_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['agreement_proposal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_approvals');
        Schema::dropIfExists('agreement_proposals');
        Schema::dropIfExists('agreement_answers');
        Schema::dropIfExists('couple_agreements');
    }
};
