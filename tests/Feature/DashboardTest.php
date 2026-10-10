<?php

declare(strict_types=1);

use App\Actions\Couples\EndCouple;
use App\Enums\DutyStatus;
use App\Enums\HeartOperationType;
use App\Enums\RequestStatus;
use App\Models\Duty;
use App\Models\MoodEntry;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Hearts\Ledger;
use App\ViewModels\DashboardViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('shows a personal dashboard without a partner and keeps the guest introduction', function (): void {
    $this->get('/')->assertOk()->assertSee('Больше тепла.');
    $user = User::factory()->create(['name' => '<script>Очень длинное имя</script>']);
    $this->asUser($user)->get('/')->assertOk()->assertSee($user->name)->assertDontSee('<script>Очень', false)->assertSee('Создать пару')->assertSee('Мои сердечки')->assertDontSee('София')->assertDontSee('Приложение в разработке');
});

it('shows current couple data and bounded personal duties with correct quick links', function (): void {
    $pair = AgreementScenario::create();
    $pair->couple->forceFill(['started_on' => CarbonImmutable::today()->subDays(7)])->save();
    $incoming = PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->second->id, 'recipient_id' => $pair->first->id, 'description' => 'Пожалуйста, принеси чай']);
    foreach (range(1, 6) as $number) {
        $source = PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->second->id, 'recipient_id' => $pair->first->id, 'status' => RequestStatus::Accepted]);
        Duty::factory()->create(['partner_request_id' => $source->id, 'couple_id' => $pair->couple->id, 'executor_id' => $number === 5 ? $pair->second->id : $pair->first->id, 'reviewer_id' => $number === 5 ? $pair->first->id : $pair->second->id, 'status' => $number === 6 ? DutyStatus::Confirmed : DutyStatus::Pending, 'description' => match ($number) {
            5 => 'Чужое дело', 6 => 'Завершённое дело', default => 'Своё ближайшее дело'
        }]);
    }
    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->second->id]);
    $response = $this->asUser($pair->first)->get('/')->assertOk()->assertSee($pair->second->name)->assertSee('Вместе 7 дн.')->assertSee('Пожалуйста, принеси чай')->assertDontSee('Чужое дело')->assertDontSee('Завершённое дело')->assertSee(route('dishes.index', ['owner_id' => $pair->second->id]), false)->assertSee(route('dates.create', $pair->couple), false);
    $response->assertViewHas('dashboard', fn (DashboardViewModel $dashboard): bool => $dashboard->duties->count() === 3 && $dashboard->newRequest?->id === $incoming->id && $dashboard->isAgreementPending);
});

it('does not expose a previous couple or another couple on the dashboard', function (): void {
    $pair = AgreementScenario::create();
    PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->second->id, 'recipient_id' => $pair->first->id, 'description' => 'Старая личная просьба']);
    app(EndCouple::class)->execute($pair->first, $pair->couple);
    $other = AgreementScenario::create();
    PartnerRequest::factory()->create(['couple_id' => $other->couple->id, 'author_id' => $other->second->id, 'recipient_id' => $other->first->id, 'description' => 'Другая личная просьба']);
    $this->asUser($pair->first)->get('/')->assertOk()->assertDontSee($pair->second->name)->assertDontSee('Старая личная просьба')->assertDontSee('Другая личная просьба');
});

it('displays a real negative wallet without clamping it to zero', function (): void {
    $user = User::factory()->create();
    app(Ledger::class)->apply($user, -25, HeartOperationType::ManualDeduction, 'dashboard:negative');
    $this->asUser($user)->get('/')->assertOk()->assertSee('Баланс в минусе')->assertViewHas('dashboard', fn (DashboardViewModel $dashboard): bool => $dashboard->wallet->balance === -5 && $dashboard->wallet->available === -5);
});

it('offers a retry and safe reference when the dashboard cannot load', function (): void {
    $user = User::factory()->create();
    Log::spy();
    DB::listen(function (QueryExecuted $query): void {
        if (str_contains($query->sql, 'from "couples"')) {
            throw new RuntimeException('private failure contents');
        }
    });
    $this->asUser($user)->get('/')->assertStatus(Response::HTTP_SERVICE_UNAVAILABLE)->assertSee('Не удалось загрузить главную')->assertSee('Повторить')->assertDontSee('private failure contents');
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'dashboard.load_failed' && isset($context['request_id']) && ! isset($context['message']))->once();
});
