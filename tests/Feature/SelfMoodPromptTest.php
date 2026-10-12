<?php

declare(strict_types=1);

use App\Actions\Mood\RecordMood;
use App\Data\Mood\MoodData;
use App\Enums\MoodLevel;
use App\Enums\NotificationKind;
use App\Models\MoodPrompt;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\DeliveryContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});

it('sends a self question to the actor and sends the mood answer to the partner without duplicates', function (): void {
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->postJson(route('mood.self.store', $pair->couple))->assertOk();
    $this->postJson(route('mood.self.store', $pair->couple))->assertOk();
    $prompt = MoodPrompt::query()->sole();
    expect($prompt->user_id)->toBe($pair->first->id)->and($prompt->is_self_requested)->toBeTrue();
    $question = NotificationDelivery::query()->sole();
    expect($question->recipient_id)->toBe($pair->first->id)->and(app(DeliveryContent::class)->getText($question))->toContain('Ваше настроение', 'Партнёр её увидит')->not->toContain('Партнёр хочет');
    app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString(), promptId: $prompt->id));
    expect(NotificationDelivery::query()->where('kind', NotificationKind::MoodEntry)->sole()->recipient_id)->toBe($pair->second->id);
    $this->asUser(User::factory()->create())->postJson(route('mood.self.store', $pair->couple))->assertForbidden();
});
