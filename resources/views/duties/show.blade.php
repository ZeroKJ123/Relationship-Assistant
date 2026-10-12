@extends('layouts.app')
@section('title', __('Выполнение дела'))
@section('back', route('duties.index',$couple))
@section('back-label', __('Наши дела'))
@section('content')
<div class="page-head"><p class="eyebrow">{{ __($duty->kind->label()) }}</p><x-status :tone="$duty->status->tone()">{{ __($duty->status->label()) }}</x-status></div>
<h1 class="screen-title request-description">{{ $duty->description }}</h1>
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
</dl>
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
<h2 class="group-label"><span>{{ __('История ответов') }}</span></h2>
@forelse($history as $entry)
    <p class="row">{{ __('Версия :version', ['version'=>$entry->version]) }} · {{ $entry->stage === 'overdue' ? __('Срок прошёл') : ($entry->stage === 'report' ? __('Отметка исполнителя') : __('Проверка партнёром')) }} · {{ $entry->answer === null ? __('Автоматическая проверка') : ($entry->answer ? __('Да') : __('Нет')) }}</p>
@empty<p class="row row--empty">{{ __('Ответов ещё нет.') }}</p>@endforelse
{{ $history->links() }}
<nav class="settings-links" aria-label="{{ __('Связанные разделы') }}"><a href="{{ route('requests.show',[$couple,$duty->partner_request_id]) }}">{{ __('К просьбе и условиям') }}</a><a href="{{ route('duties.index',$couple) }}">{{ __('Все дела') }}</a><a href="{{ route('hearts.index') }}">{{ __('Мои сердечки') }}</a></nav>
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
