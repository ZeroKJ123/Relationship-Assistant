@extends('layouts.app')
@section('title', __('Наши просьбы'))
@section('content')
<div class="page-head">
    <h1 class="screen-title">{{ __('Наши просьбы') }}</h1>
    @if($couple->status === \App\Enums\CoupleStatus::Active)<a class="button button--primary button--small" href="{{ route('requests.create',$couple) }}">+ {{ __('Новая просьба') }}</a>@endif
</div>
<p class="lead">{{ __('Ответ и встречное условие сохраняются отдельно от выполнения.') }}</p>
@if($couple->status !== \App\Enums\CoupleStatus::Active)<p class="lead">{{ __('Новые просьбы доступны действующей паре.') }}</p>@endif
@foreach($groups as $group)
<h2 class="group-label"><span>{{ $group['label'] }}</span><span @class(['group-label__count--alert' => $group['isAlert']])>{{ $group['requests']->count() }}</span></h2>
@foreach($group['requests'] as $item)
<a class="row" href="{{ route('requests.show',[$couple,$item]) }}" aria-label="{{ __('Открыть просьбу') }}: {{ $item->description }}">
    <x-icon :name="$item->type->icon()" />
    <span class="row__body">
        <strong class="row__title request-description--primary">{{ $item->description }}</strong>
        <span class="row__meta">{{ __($item->type->label()) }} · {{ $item->author->name }} → {{ $item->recipient->name }}</span>
        <span class="row__meta">{{ __('Срок: :date', ['date'=>$item->due_at->setTimezone($couple->timezone)->format('d.m.Y H:i')]) }}</span>
        <x-status :tone="$progress->getTone($item)">{{ $progress->getLabel($item) }}</x-status>
    </span>
    @if($progress->isAwaitingAnswerFrom($item, $viewer))<span class="status status--action">{{ __('Ответить') }}</span>@else<x-icon name="chevron" class="row__chevron" />@endif
</a>
@endforeach
@endforeach
@if($requests->isEmpty())<p class="row row--empty">{{ __('Просьб пока нет. Начните с небольшой помощи друг другу.') }}</p>@endif
{{ $requests->links() }}

@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
