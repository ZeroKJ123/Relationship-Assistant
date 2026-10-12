@extends('layouts.app')
@section('title', __('Выполнение дела'))
@section('back', route('duties.index',$couple))
@section('back-label', __('Наши дела'))
@section('content')
<div class="page-head"><p class="eyebrow">{{ __($duty->kind->label()) }}</p><x-status :tone="$duty->status->tone()">{{ __($duty->status->label()) }}</x-status></div>
<h1 class="screen-title request-description">{{ $duty->description }}</h1>
@if($isReviewer)<p class="lead">{{ $duty->kind === \App\Enums\DutyKind::Service ? __('Это ваша просьба. Выполняет :name.', ['name'=>$duty->executor->name]) : __('Это ваше условие. Выполняет :name.', ['name'=>$duty->executor->name]) }}</p>@endif
@if($isExecutor)<p class="lead">{{ $duty->kind === \App\Enums\DutyKind::Service ? __('Вас просит :name. Выполняете вы.', ['name'=>$duty->reviewer->name]) : __(':name ставит условие. Выполняете вы.', ['name'=>$duty->reviewer->name]) }}</p>@endif
<ol class="steps" aria-label="{{ __('Этапы дела') }}">
    @foreach([__('Создано'), __('Выполнение'), __('Проверка')] as $index => $step)
    <li @if($duty->status->progressStep() > $index + 1) data-state="done" @elseif($duty->status->progressStep() === $index + 1) data-state="current" aria-current="step" @endif>{{ $step }}</li>
    @endforeach
</ol>
<dl class="facts">
    <div><dt>{{ __('Исполнитель') }}</dt><dd>{{ $duty->executor->name }}</dd></div>
    <div><dt>{{ __('Проверяет') }}</dt><dd>{{ $duty->reviewer->name }}</dd></div>
    <div><dt>{{ __('Срок') }}</dt><dd>{{ $duty->due_at->setTimezone($duty->timezone)->format('d.m.Y H:i') }}</dd></div>
    @if($duty->reported_at)<div><dt>{{ __('Выполнение отмечено') }}</dt><dd>{{ $duty->reported_at->setTimezone($duty->timezone)->format('d.m.Y H:i') }}</dd></div>@endif
    @if($duty->reviewed_at)<div><dt>{{ __('Проверено') }}</dt><dd>{{ $duty->reviewed_at->setTimezone($duty->timezone)->format('d.m.Y H:i') }}</dd></div>@endif
    @if($duty->kind === \App\Enums\DutyKind::Service && $partnerRequest->heart_price > 0)<div><dt>{{ __('Цена услуги') }}</dt><dd>{{ __(':amount сердечек', ['amount'=>$partnerRequest->heart_price]) }}</dd></div>@endif
    @if($duty->kind === \App\Enums\DutyKind::Service && $partnerRequest->moneyRequest)<div><dt>{{ __('Сумма') }}</dt><dd>{{ $partnerRequest->moneyRequest->amount }} {{ $partnerRequest->moneyRequest->currency->value }}</dd></div>@endif
    @if($duty->kind === \App\Enums\DutyKind::Condition && $duty->requestCondition?->amount)<div><dt>{{ __('Сумма') }}</dt><dd>{{ $duty->requestCondition->amount }} {{ $duty->requestCondition->currency->value }}</dd></div>@endif
</dl>
@if($duty->kind === \App\Enums\DutyKind::Condition)<p class="lead">{{ __('Встречное условие к просьбе:') }} <span class="request-description">{{ $partnerRequest->description }}</span></p>@endif
@if($duty->kind === \App\Enums\DutyKind::Service && $partnerRequest->moneyRequest && $partnerRequest->status === \App\Enums\RequestStatus::Accepted)<h2 class="group-label"><span>{{ __('Реквизиты получателя') }}</span></h2><p class="row request-description">{{ $partnerRequest->moneyRequest->payment_details_snapshot }}</p>@endif
@if($duty->kind === \App\Enums\DutyKind::Condition && $duty->requestCondition?->amount && $duty->requestCondition->accepted_at && $partnerRequest->status === \App\Enums\RequestStatus::Accepted)<h2 class="group-label"><span>{{ __('Реквизиты получателя') }}</span></h2><p class="row request-description">{{ $duty->requestCondition->payment_details_snapshot }}</p>@endif
<div class="form-stack">
    <p class="hint"><x-icon name="heart" /><span>@if($duty->is_reward_eligible){{ __('Награда после проверки: до :amount сердечек с учётом лимитов.', ['amount'=>$duty->reward]) }}@else{{ __('Денежная оплата и покупка подарка не приносят награду за дело.') }}@endif</span></p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p class="field-error">{{ $error }}</p>@endforeach</div>@endif
    @if($duty->status === \App\Enums\DutyStatus::Overdue)<p class="hint"><x-icon name="info" /><span>{{ __('Срок прошёл. Выполнение можно отметить; это не отменяет уже применённый штраф.') }}</span></p>@endif
    @if($duty->status === \App\Enums\DutyStatus::Reported)<p class="hint"><x-icon name="info" /><span>{{ __('Своевременная отметка не штрафуется, пока ждёт проверки.') }}</span></p>@endif
    @if($duty->status === \App\Enums\DutyStatus::Disputed)<p class="hint"><x-icon name="info" /><span>{{ __('Отметка оспорена. Штраф за ложную отметку применён один раз; своевременная отметка не получает дополнительный штраф просрочки.') }}</span></p>@endif
</div>
@can('report',$duty)
    @if(in_array($duty->status,[\App\Enums\DutyStatus::Pending,\App\Enums\DutyStatus::Overdue],true))
        <x-card class="section-space"><h2>{{ __('Ваш ответ о выполнении') }}</h2>
            <x-duty-answer :action="route('duties.report',[$couple,$duty])" :duty="$duty" :positive-label="$reportLabel" />
        </x-card>
    @endif
@endcan
@can('verify',$duty)
    @if($duty->status === \App\Enums\DutyStatus::Reported)
        <x-card class="section-space"><h2>{{ __('Проверка партнёром') }}</h2>
            <p>{{ __('Проверьте результат. За оспоренную отметку исполнитель потеряет :amount сердечек.', ['amount'=>$duty->false_report_penalty]) }}</p>
            <x-duty-answer :action="route('duties.verify',[$couple,$duty])" :duty="$duty" :is-review="true" :positive-label="$verifyLabel" />
        </x-card>
    @endif
@endcan
@if($isReviewer && in_array($duty->status,[\App\Enums\DutyStatus::Pending,\App\Enums\DutyStatus::Overdue],true))
    <x-card class="section-space form-stack"><h2>{{ __('Ваше подтверждение') }}</h2>
        <x-button disabled aria-describedby="verify-unavailable">{{ $verifyLabel }}</x-button>
        <p class="field-hint" id="verify-unavailable">{{ __('Кнопка станет доступна, когда :name отметит выполнение.', ['name'=>$duty->executor->name]) }}</p>
    </x-card>
@endif
@if($isExecutor && $duty->status === \App\Enums\DutyStatus::Reported)
    <x-card class="section-space form-stack"><h2>{{ __('Ваш ответ о выполнении') }}</h2>
        <x-button disabled aria-describedby="report-waiting">{{ $reportLabel }}</x-button>
        <p class="field-hint" id="report-waiting">{{ __('Вы уже отметили выполнение. Ждём, когда :name проверит результат.', ['name'=>$duty->reviewer->name]) }}</p>
    </x-card>
@endif
<x-request-cancellation :couple="$couple" :partner-request="$partnerRequest" :return-duty="$duty" />
<h2 class="group-label"><span>{{ __('История ответов') }}</span></h2>
@forelse($history as $entry)
    <p class="row">{{ __('Версия :version', ['version'=>$entry->version]) }} · {{ $entry->stage === 'overdue' ? __('Срок прошёл') : ($entry->stage === 'report' ? __('Отметка исполнителя') : __('Проверка партнёром')) }} · {{ $entry->answer === null ? __('Автоматическая проверка') : ($entry->answer ? __('Да') : __('Нет')) }}</p>
@empty<p class="row row--empty">{{ __('Ответов ещё нет.') }}</p>@endforelse
{{ $history->links() }}
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
