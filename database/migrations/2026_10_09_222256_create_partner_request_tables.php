<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->unique(['author_id', 'operation_key']);
            $table->string('type', 20);
            $table->text('description');
            $table->timestamp('due_at');
            $table->boolean('is_very_wanted')->default(false);
            $table->unsignedSmallInteger('refusal_tariff');
            $table->unsignedInteger('refusal_tariff_version');
            $table->unsignedSmallInteger('decline_penalty')->nullable();
            $table->string('decline_reason', 30)->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('version');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['couple_id', 'created_at']);
        });
        Schema::create('request_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposed_by_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('type', 20);
            $table->text('description');
            $table->timestamp('due_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['partner_request_id', 'version']);
        });
        Schema::create('request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('status', 20);
            $table->unsignedInteger('version');
            $table->timestamp('created_at');
            $table->unique(['partner_request_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_events');
        Schema::dropIfExists('request_conditions');
        Schema::dropIfExists('partner_requests');
    }
};
