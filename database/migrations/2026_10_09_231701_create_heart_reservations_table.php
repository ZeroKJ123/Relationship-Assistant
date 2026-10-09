<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->unsignedSmallInteger('heart_price')->nullable();
            $table->foreignId('cancellation_proposed_by_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        DB::table('partner_requests')->whereNull('heart_price')->update(['heart_price' => 0]);
        Schema::create('heart_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('heart_wallet_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('amount');
            $table->string('status', 20);
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['heart_wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heart_reservations');
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancellation_proposed_by_id');
            $table->dropColumn('heart_price');
        });
    }
};
