<?php

declare(strict_types=1);

use App\Enums\HeartOperationType;
use App\Models\AdminAudit;
use App\Models\AppearanceSetting;
use App\Models\HeartReservation;
use App\Models\HeartTransaction;
use App\Models\HeartWallet;
use App\Models\User;
use App\Services\Hearts\Ledger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);

it('applies a concurrent duplicate admin adjustment once including its audit', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $arguments = [(string) $actor->id, (string) $target->id, 'balance', Str::uuid()->toString(), '200'];
    expect((new ConcurrentOperations)->run('admin-customization-worker.php', [$arguments, $arguments]))->toBe(['accepted', 'accepted']);
    expect(app(Ledger::class)->getWallet($target)->balance)->toBe(220);
    expect(HeartTransaction::query()->where('type', HeartOperationType::AdminAdjustment)->count())->toBe(1);
    expect(AdminAudit::query()->where('operation', 'like', 'hearts.admin_adjustment:%')->count())->toBe(1);
    app(Ledger::class)->reconcile($target);
});

it('serializes distinct credits and debits without losing either amount', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $arguments = [(string) $actor->id, (string) $target->id, 'balance'];
    expect((new ConcurrentOperations)->run('admin-customization-worker.php', [[...$arguments, Str::uuid()->toString(), '200'], [...$arguments, Str::uuid()->toString(), '-5']]))->toBe(['accepted', 'accepted']);
    expect(app(Ledger::class)->getWallet($target)->balance)->toBe(215);
    app(Ledger::class)->reconcile($target);
});

it('protects a held payment when concurrent deductions compete for free hearts', function (): void {
    $actor = User::factory()->create();
    $reservation = HeartReservation::factory()->create(['amount' => 15]);
    $target = User::query()->findOrFail(HeartWallet::query()->findOrFail($reservation->heart_wallet_id)->user_id);
    $arguments = [(string) $actor->id, (string) $target->id, 'balance'];
    $results = (new ConcurrentOperations)->run('admin-customization-worker.php', [[...$arguments, Str::uuid()->toString(), '-5'], [...$arguments, Str::uuid()->toString(), '-5']]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect(app(Ledger::class)->getWallet($target)->balance)->toBe(15);
    app(Ledger::class)->reconcile($target);
});

it('allows only one settings save from two concurrent stale editors', function (): void {
    $actor = User::factory()->create();
    $arguments = [(string) $actor->id, (string) $actor->id, 'appearance'];
    $results = (new ConcurrentOperations)->run('admin-customization-worker.php', [[...$arguments, 'home', '0'], [...$arguments, 'bell', '0']]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect(AppearanceSetting::query()->sole()->revision)->toBe(1);
    expect(AppearanceSetting::query()->sole()->settings['icons'])->toHaveCount(1);
    expect(AdminAudit::query()->where('operation', 'appearance.icons')->count())->toBe(1);
});
