<?php

declare(strict_types=1);
use App\Actions\Couples\EndCouple;
use App\Actions\Dates\CreateDateInvitation;
use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Actions\Friends\ArchiveFriend;
use App\Actions\Friends\SaveFriend;
use App\Actions\Requests\CancelPartnerRequest;
use App\Actions\Requests\CreateGuestVisit;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Dates\DateInvitationData;
use App\Data\Friends\FriendData;
use App\Data\Requests\GuestVisitData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use App\Enums\HeartOperationType;
use App\Enums\InvitationKind;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\DateInvitation;
use App\Models\Friend;
use App\Models\GuestVisit;
use App\Models\PartnerRequest;
use App\Models\RequestTemplate;
use App\Models\SettlementMethod;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Hearts\Ledger;
use App\Services\Requests\InvitationTime;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});
function socialVisitData(Friend $friend): GuestVisitData
{
    return new GuestVisitData(friends: [['id' => $friend->id, 'version' => $friend->version]], startsAt: CarbonImmutable::now()->addDays(3), durationMinutes: 60, reason: 'Собраться дома', operationKey: Str::uuid()->toString());
}
it('keeps the friend list personal with safe editing archiving and creation retries', function (): void {
    $pair = AgreementScenario::create();
    $data = new FriendData('Друг', Str::uuid()->toString());
    $friend = app(SaveFriend::class)->execute($pair->first, $data);
    expect(app(SaveFriend::class)->execute($pair->first, $data)->id)->toBe($friend->id);
    $this->asUser($pair->first)->get(route('friends.index'))->assertOk()->assertSee('Друг');
    $this->asUser($pair->second)->get(route('friends.index'))->assertOk()->assertDontSee('Друг');
    $this->get(route('friends.edit', $friend))->assertForbidden();
    expect(fn () => app(SaveFriend::class)->execute($pair->second, $data, $friend))->toThrow(AuthorizationException::class);
    $change = new FriendData('Новое имя', Str::uuid()->toString());
    app(SaveFriend::class)->execute($pair->first, $change, $friend);
    expect(app(SaveFriend::class)->execute($pair->first, $change, $friend)->version)->toBe(1);
    expect(fn () => app(SaveFriend::class)->execute($pair->first, $data))->toThrow(ValidationException::class);
    $this->asUser(User::factory()->create(['is_admin' => true]))->get(route('friends.edit', $friend))->assertForbidden();
    $this->asUser($pair->first)->delete(route('friends.destroy', $friend), ['expected_version' => 1])->assertSessionHasErrors('confirmation');
    $this->delete(route('friends.destroy', $friend), ['expected_version' => 1, 'confirmation' => 1])->assertRedirect();
    expect($friend->refresh()->archived_at)->not->toBeNull();
    expect(fn () => app(SaveFriend::class)->execute($pair->first, new FriendData('Иное', Str::uuid()->toString(), 2), $friend))->toThrow(ValidationException::class);
});
it('creates and updates a friend through validated forms', function (): void {
    $user = User::factory()->create();
    $this->asUser($user)->get(route('friends.create'))->assertOk();
    $this->post(route('friends.store'), ['name' => 'Тест друг', 'operation_key' => Str::uuid()->toString(), 'expected_version' => 0])->assertRedirect();
    $friend = Friend::query()->sole();
    $this->put(route('friends.update', $friend), ['name' => 'Обновлено', 'operation_key' => Str::uuid()->toString(), 'expected_version' => 0])->assertRedirect();
    expect($friend->refresh()->name)->toBe('Обновлено');
});
it('freezes guest names time and duration while preserving exact retries after edits', function (): void {
    $pair = AgreementScenario::create();
    $friends = Friend::factory()->count(2)->create(['user_id' => $pair->first->id]);
    $data = new GuestVisitData(friends: $friends->reverse()->map(fn (Friend $friend): array => ['id' => $friend->id, 'version' => 0])->values()->all(), startsAt: CarbonImmutable::now()->addDays(3), durationMinutes: 90, reason: 'Игры', operationKey: Str::uuid()->toString());
    $request = app(CreateGuestVisit::class)->execute($pair->first, $pair->couple, $data);
    $visit = $request->guestVisit()->sole();
    expect($visit->duration_minutes)->toBe(90);
    expect($visit->ends_at->equalTo($request->due_at))->toBeTrue();
    expect(array_column($visit->guests_snapshot, 'id'))->toBe($friends->modelKeys());
    app(SaveFriend::class)->execute($pair->first, new FriendData('Переименован', Str::uuid()->toString()), $friends->first());
    app(ArchiveFriend::class)->execute($pair->first, $friends->last(), 0);
    expect(app(CreateGuestVisit::class)->execute($pair->first, $pair->couple, $data)->id)->toBe($request->id);
    expect(GuestVisit::query()->count())->toBe(1);
    $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee($visit->guests_snapshot[0]['name'])->assertDontSee('Переименован')->assertSee('90');
    $this->get(route('friends.index'))->assertDontSee($visit->guests_snapshot[0]['name']);
});
it('rejects foreign archived stale or duplicate guests without partial requests', function (string $case): void {
    $pair = AgreementScenario::create();
    $friend = Friend::factory()->create(['user_id' => $case === 'foreign' ? $pair->second->id : $pair->first->id]);
    if ($case === 'archived') {
        app(ArchiveFriend::class)->execute($pair->first, $friend, 0);
    }
    $choices = [['id' => $friend->id, 'version' => $case === 'stale' ? 1 : 0]];
    if ($case === 'duplicate') {
        $choices[] = $choices[0];
    }
    if ($case === 'empty') {
        $choices = [];
    }
    $data = new GuestVisitData($choices, CarbonImmutable::tomorrow(), 60, 'Встреча', Str::uuid()->toString());
    expect(fn () => app(CreateGuestVisit::class)->execute($pair->first, $pair->couple, $data))->toThrow(ValidationException::class);
    expect(PartnerRequest::query()->count())->toBe(0);
})->with(['foreign', 'archived', 'stale', 'duplicate', 'empty']);
it('rejects invalid duration and already started visits even when the end is future', function (int $duration, bool $past): void {
    $pair = AgreementScenario::create();
    $friend = Friend::factory()->create(['user_id' => $pair->first->id]);
    $data = new GuestVisitData([['id' => $friend->id, 'version' => 0]], $past ? CarbonImmutable::now()->subMinute() : CarbonImmutable::tomorrow(), $duration, 'Встреча', Str::uuid()->toString());
    expect(fn () => app(CreateGuestVisit::class)->execute($pair->first, $pair->couple, $data))->toThrow(ValidationException::class);
    expect(GuestVisit::query()->count())->toBe(0);
})->with([[14, false], [1441, false], [60, true]]);
it('preserves guest selection and private form data across picker pages without sending an invitation', function (): void {
    $pair = AgreementScenario::create();
    $friends = Friend::factory()->count(21)->create(['user_id' => $pair->first->id]);
    $selected = $friends->last();
    $key = Str::uuid()->toString();
    $response = $this->asUser($pair->first)->post(route('guests.preview', $pair->couple), ['page' => 2, 'friends' => [sprintf('%d:0', $selected->id)], 'reason' => 'Личная причина', 'duration_minutes' => 90, 'starts_at' => '2026-11-01T18:00', 'operation_key' => $key]);
    $response->assertOk()->assertSee(sprintf('Выбран: %s', $selected->name))->assertSee('Личная причина')->assertSee($key);
    expect(GuestVisit::query()->count())->toBe(0);
});
it('validates explicit confirmation and converts guest time from the couple timezone', function (): void {
    $pair = AgreementScenario::create();
    $friend = Friend::factory()->create(['user_id' => $pair->first->id]);
    $date = CarbonImmutable::now()->addDays(3)->setTimezone($pair->couple->timezone)->format('Y-m-d\TH:i');
    $payload = ['friends' => [sprintf('%d:0', $friend->id)], 'starts_at' => $date, 'duration_minutes' => 60, 'reason' => 'Встреча', 'operation_key' => Str::uuid()->toString()];
    $this->asUser($pair->first)->get(route('guests.create', $pair->couple))->assertOk();
    $this->post(route('guests.store', $pair->couple), $payload)->assertSessionHasErrors('confirmation');
    $this->post(route('guests.store', $pair->couple), [...$payload, 'confirmation' => 1])->assertRedirect();
    $visit = GuestVisit::query()->sole();
    expect($visit->starts_at->format('Y-m-d H:i'))->toBe(CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $date, $pair->couple->timezone)->utc()->format('Y-m-d H:i'));
});
it('rejects nonexistent local times instead of silently changing the meeting', function (): void {
    expect(fn () => app(InvitationTime::class)->parse('2027-03-28T02:30', 'Europe/Berlin'))->toThrow(ValidationException::class);
});
it('allows ordinary invitations at negative balance and accepts sushi through the shared request flow', function (): void {
    $pair = AgreementScenario::create();
    $this->seed(CatalogSeeder::class);
    app(Ledger::class)->apply($pair->first, -30, HeartOperationType::FalseReportPenalty, 'social:negative');
    $friend = Friend::factory()->create(['user_id' => $pair->first->id]);
    $request = app(CreateGuestVisit::class)->execute($pair->first, $pair->couple, socialVisitData($friend));
    $sushi = SettlementMethod::query()->where('kind', 'sushi')->sole();
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Custom, description: 'Суши', dueAt: CarbonImmutable::tomorrow(), methodId: $sushi->id, sushiSize: 'medium'));
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted);
    expect($request->conditions()->sole()->sushi_size)->toBe('medium');
    expect($request->duties()->count())->toBe(2);
    $date = new DateInvitationData(InvitationKind::Date, 'Прогулка', CarbonImmutable::tomorrow(), null, 'Удобно', Str::uuid()->toString());
    expect(app(CreateDateInvitation::class)->execute($pair->first, $pair->couple, $date)->heart_price)->toBe(0);
});
it('uses a gift condition for a date with independent service completion and historical access', function (): void {
    $pair = AgreementScenario::create();
    $date = new DateInvitationData(InvitationKind::Date, 'Кино', CarbonImmutable::now()->addDays(3), 'Вечером', 'Красный', Str::uuid()->toString());
    $request = app(CreateDateInvitation::class)->execute($pair->first, $pair->couple, $date);
    expect(app(CreateDateInvitation::class)->execute($pair->first, $pair->couple, $date)->id)->toBe($request->id);
    $wish = WishlistItem::factory()->create(['user_id' => $pair->second->id]);
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Gift, description: 'Подарок', dueAt: CarbonImmutable::tomorrow(), wishlistItemId: $wish->id, wishlistVersion: 0));
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    $duty = $request->duties()->where('kind', DutyKind::Service)->sole();
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $duty, 0, true);
    app(VerifyDuty::class)->execute($pair->first, $pair->couple, $duty, 1, true);
    expect($duty->refresh()->status)->toBe(DutyStatus::Confirmed);
    expect($wish->refresh()->received_at)->toBeNull();
    app(EndCouple::class)->execute($pair->first, $pair->couple);
    $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('Кино')->assertSee('Красный');
    $this->asUser(User::factory()->create(['is_admin' => true]))->get(route('requests.show', [$pair->couple, $request]))->assertForbidden();
    $this->asUser($pair->first)->get(route('dates.create', $pair->couple))->assertForbidden();
});
it('creates events through confirmed forms and protects changed duplicate details and past times', function (): void {
    $pair = AgreementScenario::create();
    $date = CarbonImmutable::now()->addDays(3)->setTimezone($pair->couple->timezone)->format('Y-m-d\TH:i');
    $payload = ['kind' => 'activity', 'title' => 'Театр', 'starts_at' => $date, 'description' => 'Вечер', 'dress_code' => 'Костюм', 'operation_key' => Str::uuid()->toString()];
    $this->asUser($pair->first)->get(route('dates.create', $pair->couple))->assertOk();
    $this->post(route('dates.store', $pair->couple), $payload)->assertSessionHasErrors('confirmation');
    $this->post(route('dates.store', $pair->couple), [...$payload, 'confirmation' => 1])->assertRedirect();
    $this->post(route('dates.store', $pair->couple), [...$payload, 'confirmation' => 1])->assertRedirect();
    expect(DateInvitation::query()->count())->toBe(1);
    $this->post(route('dates.store', $pair->couple), [...$payload, 'dress_code' => 'Изменено', 'confirmation' => 1])->assertSessionHasErrors('partner_request');
    expect(fn () => app(CreateDateInvitation::class)->execute($pair->first, $pair->couple, new DateInvitationData(InvitationKind::Date, 'Поздно', CarbonImmutable::yesterday(), null, null, Str::uuid()->toString())))->toThrow(ValidationException::class);
});
it('cancels accepted guest visits only with two agreements and redirects catalog templates', function (): void {
    $pair = AgreementScenario::create();
    $this->seed(CatalogSeeder::class);
    $guestTemplate = RequestTemplate::query()->where('type', RequestType::Guests)->firstOrFail();
    $dateTemplate = RequestTemplate::query()->where('type', RequestType::Event)->firstOrFail();
    $this->asUser($pair->first)->get(route('requests.create', [$pair->couple, 'request_template_id' => $guestTemplate->id]))->assertRedirect(route('guests.create', [$pair->couple, 'request_template_id' => $guestTemplate->id]));
    $this->get(route('requests.create', [$pair->couple, 'request_template_id' => $dateTemplate->id]))->assertRedirect(route('dates.create', [$pair->couple, 'request_template_id' => $dateTemplate->id]));
    $friend = Friend::factory()->create(['user_id' => $pair->first->id]);
    $request = app(CreateGuestVisit::class)->execute($pair->first, $pair->couple, socialVisitData($friend));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    app(CancelPartnerRequest::class)->execute($pair->first, $pair->couple, $request, 1);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted);
    app(CancelPartnerRequest::class)->execute($pair->second, $pair->couple, $request, 2);
    expect($request->refresh()->status)->toBe(RequestStatus::Cancelled);
    expect($request->guestVisit->guests_snapshot[0]['name'])->toBe($friend->name);
});

it('rejects inconsistent invitation deadlines without creating partial requests', function (RequestType $type): void {
    $pair = AgreementScenario::create();
    $start = CarbonImmutable::now()->addDays(3);
    $friend = Friend::factory()->create(['user_id' => $pair->first->id]);
    $key = Str::uuid()->toString();
    $data = new NewRequestData(type: $type, description: 'Встреча', dueAt: $start->addDays(1), operationKey: $key, guestVisit: $type === RequestType::Guests ? new GuestVisitData([['id' => $friend->id, 'version' => 0]], $start, 60, 'Встреча', $key) : null, dateInvitation: $type === RequestType::Event ? new DateInvitationData(InvitationKind::Date, 'Встреча', $start, null, null, $key) : null);
    expect(fn () => app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, $data))->toThrow(ValidationException::class);
    expect(PartnerRequest::query()->count())->toBe(0);
    expect(GuestVisit::query()->count())->toBe(0);
    expect(DateInvitation::query()->count())->toBe(0);
})->with([RequestType::Guests, RequestType::Event]);
