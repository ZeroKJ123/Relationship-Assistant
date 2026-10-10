@props(['duty','couple'])
<x-card class="section-space duty-card form-stack">
    <div class="section-heading"><x-status>{{ __($duty->status->label()) }}</x-status><span class="pill">{{ __($duty->kind->label()) }}</span></div>
    <h2 class="request-description">{{ $duty->description }}</h2>
    <p>{{ __('Исполнитель: :name', ['name'=>$duty->executor->name]) }}<br>{{ __('Проверяет: :name', ['name'=>$duty->reviewer->name]) }}</p>
    <p>{{ __('Срок: :date', ['date'=>$duty->due_at->setTimezone($duty->timezone)->format('d.m.Y H:i')]) }}</p>
    @if($duty->is_reward_eligible && in_array($duty->status,[\App\Enums\DutyStatus::Pending,\App\Enums\DutyStatus::Overdue,\App\Enums\DutyStatus::Reported],true))<p class="reward-preview">{{ __('После подтверждения: до +:amount ♥ с учётом лимитов', ['amount'=>$duty->reward]) }}</p>@endif
    @if(!$duty->is_reward_eligible)<p>{{ __('Без награды за денежную оплату или подарок') }}</p>@endif
    <a class="button button--quiet" href="{{ route('duties.show',[$couple,$duty]) }}">{{ __('Открыть дело') }}</a>
</x-card>
