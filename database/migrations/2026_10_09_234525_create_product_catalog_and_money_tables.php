<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('operation_key');
            $table->uuid('last_operation_key');
            $table->string('title', 120);
            $table->text('ingredients');
            $table->decimal('estimate', 12, 2);
            $table->char('currency', 3);
            $table->string('kind', 20);
            $table->unsignedSmallInteger('portions');
            $table->unsignedSmallInteger('heart_price');
            $table->string('photo_path')->nullable();
            $table->char('photo_hash', 64)->nullable();
            $table->unsignedInteger('version');
            $table->timestamps();
            $table->unique(['user_id', 'operation_key']);
            $table->index(['user_id', 'kind', 'id']);
        });
        Schema::create('wishlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('operation_key');
            $table->uuid('last_operation_key');
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->char('currency', 3);
            $table->string('photo_path')->nullable();
            $table->char('photo_hash', 64)->nullable();
            $table->unsignedInteger('version');
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('claimed_condition_id')->nullable()->constrained('request_conditions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'operation_key']);
            $table->index(['user_id', 'archived_at', 'id']);
        });
        Schema::create('money_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_request_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->text('payment_details_snapshot')->nullable();
            $table->timestamps();
        });
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->foreignId('dish_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('dish_version')->nullable();
            $table->string('dish_kind', 20)->nullable();
            $table->json('dish_snapshot')->nullable();
        });
        Schema::table('request_conditions', function (Blueprint $table): void {
            $table->foreignId('wishlist_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('wishlist_version')->nullable();
            $table->json('gift_snapshot')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->text('payment_details_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('request_conditions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('wishlist_item_id');
            $table->dropColumn(['wishlist_version', 'gift_snapshot', 'amount', 'currency', 'payment_details_snapshot']);
        });
        Schema::table('partner_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dish_id');
            $table->dropColumn(['dish_version', 'dish_kind', 'dish_snapshot']);
        });
        Schema::dropIfExists('money_requests');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('dishes');
    }
};
