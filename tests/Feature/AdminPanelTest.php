<?php

declare(strict_types=1);

use App\Models\AdminAudit;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
    config(['admin_panel.login' => 'ZeroGang3', 'admin_panel.password_hash' => Hash::make('test-panel-password')]);
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('requires the configured owner and the second password without flashing it', function (): void {
    $other = User::factory()->create(['is_admin' => true]);
    $this->asUser($other)->get(route('admin.panel.login'))->assertNotFound();
    $this->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertForbidden();
    $owner = User::factory()->create(['login' => 'ZeroGang3']);
    $this->asUser($owner)->get(route('admin.panel.index'))->assertRedirect(route('admin.panel.login'));
    $this->post(route('admin.panel.unlock'), ['password' => 'wrong'])->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password');
    $this->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect(route('admin.panel.index'));
    $this->get(route('admin.panel.index'))->assertOk()->assertSee('Пользователи')->assertSee('Тест фич');
    $this->get(route('admin.economy.index'))->assertOk();
    config(['admin_panel.password_hash' => Hash::make('changed')]);
    $this->get(route('admin.panel.index'))->assertRedirect(route('admin.panel.login'));
});

it('acts as a selected partner using the common actions and restores the owner on exit or expiration', function (): void {
    $pair = AgreementScenario::create();
    $owner = User::factory()->create(['login' => 'ZeroGang3']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $this->post(route('admin.panel.act_as'), ['user_id' => $pair->first->id])->assertSessionHasErrors('confirmation');
    $this->post(route('admin.panel.act_as'), ['user_id' => $pair->first->id, 'confirmation' => '1'])->assertRedirect(route('home'));
    $this->get('/')->assertOk()->assertSee('Проверка от имени')->assertSee($pair->first->name);
    $this->post(route('requests.store', $pair->couple), ['type' => 'service', 'description' => 'Тест от первого партнёра', 'due_at' => CarbonImmutable::now($pair->couple->timezone)->addDay()->format('Y-m-d\TH:i'), 'operation_key' => Str::uuid()->toString()])->assertSessionHasNoErrors()->assertRedirect();
    expect(PartnerRequest::query()->sole()->author_id)->toBe($pair->first->id)->and(PartnerRequest::query()->sole()->recipient_id)->toBe($pair->second->id);
    expect(AdminAudit::query()->where('target_user_id', $pair->first->id)->where('operation', 'POST requests.store')->count())->toBe(1);
    $this->post(route('logout'))->assertForbidden();
    $this->post(route('admin.panel.stop'))->assertRedirect(route('admin.panel.index'));
    $this->get('/')->assertOk()->assertDontSee('Проверка от имени')->assertSee($owner->name);
    $this->post(route('admin.panel.act_as'), ['user_id' => $pair->second->id, 'confirmation' => '1'])->assertRedirect();
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(PanelAccess::SESSION_SECONDS + 1));
    $this->post(route('mood.self.store', $pair->couple))->assertRedirect(route('admin.panel.login'));
});

it('creates one labelled system request and audits it without recording its private description', function (): void {
    $pair = AgreementScenario::create();
    $owner = User::factory()->create(['login' => 'ZeroGang3']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $data = ['recipient_id' => $pair->first->id, 'description' => 'Личная тестовая просьба', 'operation_key' => Str::uuid()->toString(), 'confirmation' => '1'];
    $this->post(route('admin.panel.system_requests'), $data)->assertSessionHasNoErrors();
    $this->post(route('admin.panel.system_requests'), $data)->assertSessionHasNoErrors();
    $request = PartnerRequest::query()->sole();
    expect($request->created_by_admin_id)->toBe($owner->id)->and($request->recipient_id)->toBe($pair->first->id);
    expect(AdminAudit::query()->where('operation', 'panel.system_request')->count())->toBe(1);
    $this->asUser($pair->first)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('Системная тестовая просьба');
});
