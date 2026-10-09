<?php

declare(strict_types=1);

use App\Actions\Couples\Agreements\AcceptProposal;
use App\Actions\Couples\Agreements\DeclineProposal;
use App\Actions\Couples\Agreements\ProposeWeeklyLimit;
use App\Actions\Couples\Agreements\SubmitAnswer;
use App\Actions\Couples\EndCouple;
use App\Actions\Couples\InvitePartner;
use App\Data\Couples\CoupleSetupData;
use App\Data\Couples\WeeklyLimitData;
use App\Enums\AgreementProposalStatus;
use App\Models\AgreementApproval;
use App\Models\Couple;
use App\Models\CoupleAgreement;
use App\Services\Couples\WeeklyLimit;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('waits for both individual answers without activating the limit', function (): void {
    $scenario = AgreementScenario::create();
    $this->asUser($scenario->first)->get(route('agreements.show', $scenario->couple))->assertOk()->assertSee('Лимит ещё не согласован');
    expect(CoupleAgreement::query()->count())->toBe(0);
    $this->post(route('agreements.answers.store', $scenario->couple), ['weekly_limit' => 3, 'expected_version' => 0, 'user_id' => $scenario->second->id])->assertRedirect();
    $agreement = $scenario->couple->agreement()->firstOrFail();
    expect($agreement->answers()->sole()->user_id)->toBe($scenario->first->id);
    expect($agreement->proposals()->count())->toBe(0);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBeNull();
    $this->asUser($scenario->second)->get(route('agreements.show', $scenario->couple))->assertSee($scenario->first->name)->assertSee('Ждём ответ второго партнёра.');
});

it('offers the rounded-up integer compromise and requires two explicit approvals', function (int $first, int $second, int $expected): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth($first, $second);
    expect($proposal->weekly_limit)->toBe($expected);
    expect($proposal->status)->toBe(AgreementProposalStatus::Proposed);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBeNull();
    $this->asUser($scenario->first)->post(route('agreements.approvals.store', [$scenario->couple, $proposal]))->assertSessionHasErrors('confirmation');
    $this->post(route('agreements.approvals.store', [$scenario->couple, $proposal]), ['confirmation' => '1'])->assertRedirect();
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBeNull();
    app(AcceptProposal::class)->execute($scenario->first, $scenario->couple, $proposal);
    expect($proposal->approvals()->count())->toBe(1);
    app(AcceptProposal::class)->execute($scenario->second, $scenario->couple, $proposal);
    app(AcceptProposal::class)->execute($scenario->second, $scenario->couple, $proposal);
    expect($proposal->refresh()->status)->toBe(AgreementProposalStatus::Accepted);
    expect($proposal->approvals()->count())->toBe(2);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBe($expected);
})->with([[1, 14, 8], [3, 4, 4], [5, 5, 5], [1, 1, 1], [14, 14, 14]]);

it('validates bounds and rejects malformed or unversioned answers', function (mixed $limit, mixed $version, string $field): void {
    $scenario = AgreementScenario::create();
    $this->asUser($scenario->first)->post(route('agreements.answers.store', $scenario->couple), ['weekly_limit' => $limit, 'expected_version' => $version])->assertSessionHasErrors($field);
    expect(CoupleAgreement::query()->count())->toBe(0);
})->with([[0, 0, 'weekly_limit'], [15, 0, 'weekly_limit'], ['3.5', 0, 'weekly_limit'], [['3'], 0, 'weekly_limit'], [3, null, 'expected_version'], [3, -1, 'expected_version']]);

it('rejects invalid values even when business actions are called directly', function (): void {
    $scenario = AgreementScenario::create();
    expect(fn () => app(SubmitAnswer::class)->execute($scenario->first, $scenario->couple, new WeeklyLimitData(15, 0)))->toThrow(ValidationException::class);
    expect(CoupleAgreement::query()->count())->toBe(0);
});

it('requires both initial answers before a counteroffer', function (): void {
    $scenario = AgreementScenario::create();
    $this->asUser($scenario->first)->post(route('agreements.proposals.store', $scenario->couple), ['weekly_limit' => 5, 'expected_version' => 0])->assertSessionHasErrors('agreement');
    expect(CoupleAgreement::query()->count())->toBe(0);
});

it('invalidates previous approvals when a counteroffer replaces the proposal', function (): void {
    $scenario = AgreementScenario::create();
    $old = $scenario->answerBoth(2, 4);
    app(AcceptProposal::class)->execute($scenario->first, $scenario->couple, $old);
    $new = app(ProposeWeeklyLimit::class)->execute($scenario->second, $scenario->couple, new WeeklyLimitData(5, $old->version));
    expect($old->refresh()->status)->toBe(AgreementProposalStatus::Superseded);
    expect($new->approvals()->count())->toBe(0);
    expect(fn () => app(AcceptProposal::class)->execute($scenario->second, $scenario->couple, $old))->toThrow(ValidationException::class);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBeNull();
    app(AcceptProposal::class)->execute($scenario->first, $scenario->couple, $new);
    app(AcceptProposal::class)->execute($scenario->second, $scenario->couple, $new);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBe(5);
});

it('allows rejection and a later counteroffer without activating rejected approvals', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(2, 4);
    $this->asUser($scenario->second)->delete(route('agreements.approvals.destroy', [$scenario->couple, $proposal]))->assertSessionHasErrors('confirmation');
    app(AcceptProposal::class)->execute($scenario->first, $scenario->couple, $proposal);
    app(DeclineProposal::class)->execute($scenario->second, $scenario->couple, $proposal);
    app(DeclineProposal::class)->execute($scenario->second, $scenario->couple, $proposal);
    expect($proposal->refresh()->status)->toBe(AgreementProposalStatus::Declined);
    expect(fn () => app(AcceptProposal::class)->execute($scenario->second, $scenario->couple, $proposal))->toThrow(ValidationException::class);
    $new = app(ProposeWeeklyLimit::class)->execute($scenario->first, $scenario->couple, new WeeklyLimitData(6, $proposal->version));
    expect($new->version)->toBe(2);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBeNull();
});

it('preserves the agreed rule while another version is discussed', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(2, 4);
    foreach ([$scenario->first, $scenario->second] as $actor) {
        app(AcceptProposal::class)->execute($actor, $scenario->couple, $proposal);
    }
    expect(fn () => app(DeclineProposal::class)->execute($scenario->first, $scenario->couple, $proposal))->toThrow(ValidationException::class);
    $new = app(ProposeWeeklyLimit::class)->execute($scenario->first, $scenario->couple, new WeeklyLimitData(7, 1));
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBe(3);
    app(AcceptProposal::class)->execute($scenario->first, $scenario->couple, $new);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBe(3);
    app(AcceptProposal::class)->execute($scenario->second, $scenario->couple, $new);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple))->toBe(7);
    expect($proposal->refresh()->status)->toBe(AgreementProposalStatus::Accepted);
});

it('rejects a stale answer or counteroffer without changing stored answers', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(2, 4);
    $this->asUser($scenario->first)->post(route('agreements.answers.store', $scenario->couple), ['weekly_limit' => 8, 'expected_version' => 0])->assertSessionHasErrors('agreement');
    $this->post(route('agreements.proposals.store', $scenario->couple), ['weekly_limit' => 8, 'expected_version' => 0])->assertSessionHasErrors('agreement');
    expect($scenario->couple->agreement()->firstOrFail()->answers()->where('user_id', $scenario->first->id)->sole()->weekly_limit)->toBe(2);
    expect($scenario->couple->agreement()->firstOrFail()->proposals()->count())->toBe(1);
    expect($proposal->version)->toBe(1);
});

it('changes an answer by proposing a new compromise and leaves duplicate answers unchanged', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(2, 4);
    app(SubmitAnswer::class)->execute($scenario->first, $scenario->couple, new WeeklyLimitData(2, 1));
    expect($scenario->couple->agreement()->firstOrFail()->proposals()->count())->toBe(1);
    app(SubmitAnswer::class)->execute($scenario->first, $scenario->couple, new WeeklyLimitData(5, 1));
    $agreement = $scenario->couple->agreement()->firstOrFail();
    expect($agreement->current_version)->toBe(2);
    expect($agreement->proposals()->where('version', 2)->sole()->weekly_limit)->toBe(5);
    expect($proposal->refresh()->status)->toBe(AgreementProposalStatus::Superseded);
});

it('denies strangers and cross-couple proposal IDs', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(2, 4);
    $other = AgreementScenario::create();
    $otherProposal = $other->answerBoth(3, 5);
    $this->asUser($other->first)->get(route('agreements.show', $scenario->couple))->assertForbidden();
    $this->post(route('agreements.answers.store', $scenario->couple), ['weekly_limit' => 3, 'expected_version' => 1])->assertForbidden();
    $this->asUser($scenario->first)->post(route('agreements.approvals.store', [$scenario->couple, $otherProposal]), ['confirmation' => '1'])->assertNotFound();
    expect($proposal->approvals()->count())->toBe(0);
    expect($otherProposal->approvals()->count())->toBe(0);
});

it('restricts ended and pending couples while preserving the old agreement history', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(2, 4);
    app(EndCouple::class)->execute($scenario->first, $scenario->couple);
    $this->asUser($scenario->first)->get(route('agreements.show', $scenario->couple))->assertOk()->assertDontSee('Сохранить мой ответ')->assertSee('Вариант 1');
    expect(fn () => app(AcceptProposal::class)->execute($scenario->second, $scenario->couple, $proposal))->toThrow(AuthorizationException::class);
    expect(app(WeeklyLimit::class)->getActiveLimit($scenario->couple->refresh()))->toBeNull();
    $invite = app(InvitePartner::class)->execute($scenario->first, new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
    $pending = Couple::query()->findOrFail($invite->coupleId);
    $this->post(route('agreements.answers.store', $pending), ['weekly_limit' => 3, 'expected_version' => 0])->assertSessionHasErrors('agreement');
    expect($pending->agreement()->count())->toBe(0);
});

it('requires CSRF for approval and keeps fields uniquely labelled', function (): void {
    $scenario = AgreementScenario::create();
    $proposal = $scenario->answerBoth(2, 4);
    $this->asUser($scenario->first)->get(route('agreements.show', $scenario->couple))->assertSee('id="answer-weekly-limit"', false)->assertSee('for="answer-weekly-limit"', false)->assertSee('id="proposal-weekly-limit"', false)->assertSee('for="proposal-weekly-limit"', false);
    $this->app->instance('env', 'local');
    $this->post(route('agreements.approvals.store', [$scenario->couple, $proposal]), ['confirmation' => '1'])->assertSee('Page Expired');
    expect(AgreementApproval::query()->count())->toBe(0);
});
