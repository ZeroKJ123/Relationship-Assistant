<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('couple_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_condition_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('executor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 20);
            $table->text('description');
            $table->timestamp('due_at');
            $table->string('timezone', 64);
            $table->string('status', 20);
            $table->unsignedInteger('version');
            $table->boolean('is_reward_eligible');
            $table->unsignedInteger('tariff_version');
            $table->unsignedSmallInteger('reward');
            $table->unsignedSmallInteger('overdue_penalty');
            $table->unsignedSmallInteger('false_report_penalty');
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->timestamps();
            $table->unique(['partner_request_id', 'kind']);
            $table->index(['couple_id', 'status', 'id']);
            $table->index(['status', 'due_at']);
        });
        Schema::create('duty_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duty_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('stage', 20);
            $table->boolean('answer')->nullable();
            $table->unsignedInteger('version');
            $table->timestamp('created_at');
            $table->unique(['duty_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_confirmations');
        Schema::dropIfExists('duties');
    }
};
