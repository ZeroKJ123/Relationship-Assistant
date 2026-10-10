@extends('layouts.app')
@section('title', __('Выполнение дела'))
@section('content')
<x-card class="start-card form-stack">
    <x-status>{{ __($duty->status->label()) }}</x-status>
    <h1 class="screen-title">{{ __($duty->kind->label()) }}</h1>
    <p class="request-description">{{ $duty->description }}</p>
    <p>{{ __('Исполнитель: :name', ['name'=>$duty->executor->name]) }}</p>
    <p>{{ __('Проверяет: :name', ['name'=>$duty->reviewer->name]) }}</p>
    <p>{{ __('Срок: :date', ['date'=>$duty->due_at->setTimezone($duty->timezone)->format('d.m.Y H:i')]) }}</p>
    @if($duty->reported_at)<p>{{ __('Выполнение отмечено: :date', ['date'=>$duty->reported_at->setTimezone($duty->timezone)->format('d.m.Y H:i')]) }}</p>@endif
    @if($duty->reviewed_at)<p>{{ __('Проверено: :date', ['date'=>$duty->reviewed_at->setTimezone($duty->timezone)->format('d.m.Y H:i')]) }}</p>@endif
    @if($duty->is_reward_eligible)<p>{{ __('Награда после проверки: до :amount сердечек с учётом лимитов.', ['amount'=>$duty->reward]) }}</p>@else<p>{{ __('Денежная оплата и покупка подарка не приносят награду за дело.') }}</p>@endif
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p class="field-error">{{ $error }}</p>@endforeach</div>@endif
    @if($duty->status === \App\Enums\DutyStatus::Overdue)<p>{{ __('Срок прошёл. Выполнение можно отметить; это не отменяет уже применённый штраф.') }}</p>@endif
    @if($duty->status === \App\Enums\DutyStatus::Reported)<p>{{ __('Своевременная отметка не штрафуется, пока ждёт проверки.') }}</p>@endif
    @if($duty->status === \App\Enums\DutyStatus::Disputed)<p>{{ __('Отметка оспорена. Штраф за ложную отметку применён один раз; своевременная отметка не получает дополнительный штраф просрочки.') }}</p>@endif
</x-card>
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
<x-card class="section-space"><h2>{{ __('История ответов') }}</h2>
    @forelse($history as $entry)
        <p>{{ __('Версия :version', ['version'=>$entry->version]) }} · {{ $entry->stage === 'overdue' ? __('Срок прошёл') : ($entry->stage === 'report' ? __('Отметка исполнителя') : __('Проверка партнёром')) }} · {{ $entry->answer === null ? __('Автоматическая проверка') : ($entry->answer ? __('Да') : __('Нет')) }}</p>
    @empty<p>{{ __('Ответов ещё нет.') }}</p>@endforelse
</x-card>
{{ $history->links() }}
<div class="confirmation-actions section-space"><a class="button button--quiet" href="{{ route('requests.show',[$couple,$duty->partner_request_id]) }}">{{ __('К просьбе и условиям') }}</a><a class="button button--quiet" href="{{ route('duties.index',$couple) }}">{{ __('Все дела') }}</a><a class="button button--quiet" href="{{ route('hearts.index') }}">{{ __('Мои сердечки') }}</a></div>
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
