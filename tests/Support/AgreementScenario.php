<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Couples\AcceptInvitation;
use App\Actions\Couples\Agreements\SubmitAnswer;
use App\Actions\Couples\InvitePartner;
use App\Data\Couples\CoupleSetupData;
use App\Data\Couples\WeeklyLimitData;
use App\Models\AgreementProposal;
use App\Models\Couple;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class AgreementScenario
{
    public function __construct(public User $first, public User $second, public Couple $couple) {}

    public static function create(): self
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $invite = app(InvitePartner::class)->execute($first, new CoupleSetupData(CarbonImmutable::yesterday(), 'Europe/Minsk'));
        $couple = app(AcceptInvitation::class)->execute($second, $invite->token);

        return new self($first, $second, $couple);
    }

    public function answerBoth(int $first, int $second): AgreementProposal
    {
        app(SubmitAnswer::class)->execute($this->first, $this->couple, new WeeklyLimitData($first, 0));
        app(SubmitAnswer::class)->execute($this->second, $this->couple, new WeeklyLimitData($second, 0));

        return $this->couple->agreement()->firstOrFail()->proposals()->sole();
    }
}
