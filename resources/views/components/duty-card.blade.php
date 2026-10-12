@props(['duty','couple'])
<a class="row duty-card" href="{{ route('duties.show',[$couple,$duty]) }}" aria-label="{{ __('Открыть дело') }}: {{ $duty->description }}">
    <span @class(['row__mark', 'row__mark--alert' => in_array($duty->status,[\App\Enums\DutyStatus::Overdue,\App\Enums\DutyStatus::Disputed],true), 'row__mark--done' => in_array($duty->status,[\App\Enums\DutyStatus::Reported,\App\Enums\DutyStatus::Confirmed],true)]) aria-hidden="true"></span>
    <span class="row__body">
        <strong class="row__title request-description">{{ $duty->description }}</strong>
        <span class="row__meta">{{ __($duty->kind->label()) }} · {{ __('Срок: :date', ['date'=>$duty->due_at->setTimezone($duty->timezone)->format('d.m.Y H:i')]) }}</span>
        <span class="row__meta">{{ __('Исполнитель: :name', ['name'=>$duty->executor->name]) }} · {{ __('Проверяет: :name', ['name'=>$duty->reviewer->name]) }}</span>
        @if($duty->is_reward_eligible && in_array($duty->status,[\App\Enums\DutyStatus::Pending,\App\Enums\DutyStatus::Overdue,\App\Enums\DutyStatus::Reported],true))<span class="row__meta">{{ __('После подтверждения: до +:amount ♥ с учётом лимитов', ['amount'=>$duty->reward]) }}</span>@endif
        @if(!$duty->is_reward_eligible)<span class="row__meta">{{ __('Без награды за денежную оплату или подарок') }}</span>@endif
        <x-status :tone="$duty->status->tone()">{{ __($duty->status->label()) }}</x-status>
    </span>
    <x-icon name="chevron" class="row__chevron" />
</a>
