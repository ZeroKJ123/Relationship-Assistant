<?php

declare(strict_types=1);

use App\Enums\HeartOperationType;
use App\Models\AdminAudit;
use App\Models\HeartReservation;
use App\Models\HeartTransaction;
use App\Models\HeartWallet;
use App\Models\User;
use App\Services\Hearts\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
    config(['admin_panel.login' => 'panel-owner', 'admin_panel.password_hash' => Hash::make('test-panel-password')]);
});

it('requires the unlocked owner for balance viewing and changes', function (): void {
    $this->get(route('admin.panel.balance.index'))->assertRedirect(route('login'));
    $other = User::factory()->create(['is_admin' => true]);
    $this->asUser($other)->post(route('admin.panel.balance.update'), [])->assertNotFound();
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->post(route('admin.panel.balance.update'), [])->assertRedirect(route('admin.panel.login'));
    expect(HeartTransaction::query()->count())->toBe(0);
});

it('adds and subtracts exact test balances once and preserves a reconcilable journal', function (): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $target = User::factory()->create();
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $this->get(route('admin.panel.balance.index'))->assertOk()->assertSee($target->login);
    $data = ['user_id' => $target->id, 'direction' => 'add', 'amount' => 200, 'reason' => 'Тест нового экрана', 'operation_key' => Str::uuid()->toString(), 'confirmation' => 1];
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasNoErrors()->assertRedirect(route('admin.panel.balance.index'));
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasNoErrors();
    expect(HeartWallet::query()->sole()->balance)->toBe(220);
    expect(HeartTransaction::query()->where('type', HeartOperationType::AdminAdjustment)->count())->toBe(1);
    expect(AdminAudit::query()->where('operation', 'like', 'hearts.admin_adjustment:%')->count())->toBe(1);
    $data['amount'] = 201;
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasErrors('hearts');
    $data['operation_key'] = Str::uuid()->toString();
    $data['direction'] = 'subtract';
    $data['amount'] = 230;
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasNoErrors();
    expect(HeartWallet::query()->sole()->balance)->toBe(-10);
    $entry = HeartTransaction::query()->latest('id')->firstOrFail();
    expect($entry->actor_id)->toBe($owner->id);
    expect($entry->reason)->toBe('Тест нового экрана');
    app(Ledger::class)->reconcile($target);
    $this->asUser($target)->get(route('hearts.index'))->assertOk()->assertSee('Изменение администратором')->assertSee('Тест нового экрана');
});

it('protects held service payments from administrative deductions', function (): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $reservation = HeartReservation::factory()->create(['amount' => 15]);
    $wallet = HeartWallet::query()->findOrFail($reservation->heart_wallet_id);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $data = ['user_id' => $wallet->user_id, 'direction' => 'subtract', 'amount' => 6, 'reason' => 'Тест', 'operation_key' => Str::uuid()->toString(), 'confirmation' => 1];
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasErrors('amount');
    expect($wallet->refresh()->balance)->toBe(20);
    $data['amount'] = 5;
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasNoErrors();
    expect($wallet->refresh()->balance)->toBe(15);
    expect($reservation->refresh()->amount)->toBe(15);
});

it('refuses reuse of the same operation key for a different user', function (): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $first = User::factory()->create();
    $second = User::factory()->create();
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $data = ['user_id' => $first->id, 'direction' => 'add', 'amount' => 5, 'reason' => 'Тест', 'operation_key' => Str::uuid()->toString(), 'confirmation' => 1];
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasNoErrors();
    $data['user_id'] = $second->id;
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasErrors('hearts');
    expect(HeartWallet::query()->where('user_id', $first->id)->sole()->balance)->toBe(25);
    expect(HeartTransaction::query()->where('type', HeartOperationType::AdminAdjustment)->count())->toBe(1);
});

it('rejects invalid and unconfirmed balance changes', function (string $field, mixed $value): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $target = User::factory()->create();
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $data = ['user_id' => $target->id, 'direction' => 'add', 'amount' => 5, 'reason' => 'Тест', 'operation_key' => Str::uuid()->toString(), 'confirmation' => 1];
    $data[$field] = $value;
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasErrors($field);
    expect(HeartTransaction::query()->count())->toBe(0);
})->with([['amount', 0], ['amount', 1.5], ['amount', 1001], ['direction', 'set'], ['reason', ''], ['operation_key', 'invalid'], ['confirmation', 0]]);

it('rolls back credits outside the allowed final balance and expires write access', function (): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $target = User::factory()->create();
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $data = ['user_id' => $target->id, 'direction' => 'add', 'amount' => 1000, 'reason' => 'Тест', 'operation_key' => Str::uuid()->toString(), 'confirmation' => 1];
    $this->post(route('admin.panel.balance.update'), $data)->assertSessionHasErrors('amount');
    expect(HeartTransaction::query()->count())->toBe(0);
    $this->travel(31)->minutes();
    $data['amount'] = 5;
    $this->post(route('admin.panel.balance.update'), $data)->assertRedirect(route('admin.panel.login'));
    expect(HeartTransaction::query()->count())->toBe(0);
    $this->travelBack();
});
