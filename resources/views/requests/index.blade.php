@extends('layouts.app')
@section('title', __('Наши просьбы'))
@section('content')
@can('update',$couple)<a class="button button--quiet section-space" href="{{ route('money.create',$couple) }}">{{ __('Попросить деньги') }}</a>@endcan
<x-card class="start-card">
<h1 class="screen-title">{{ __('Наши просьбы') }}</h1>
<p>{{ __('Ответ и встречное условие сохраняются отдельно от выполнения.') }}</p>
@if($couple->status === \App\Enums\CoupleStatus::Active)<a class="button button--primary" href="{{ route('requests.create',$couple) }}">{{ __('Новая просьба') }}</a>
@else<p>{{ __('Новые просьбы доступны действующей паре.') }}</p>
@endif
</x-card>
@forelse($requests as $item)
<x-card class="section-space form-stack">
<x-status>{{ __($item->status->label()) }}</x-status>
<h2>{{ __($item->type->label()) }}</h2>
<p class="request-description">{{ $item->description }}</p><p>{{ $item->author->name }} → {{ $item->recipient->name }}</p>
<p>{{ __('Срок: :date', ['date'=>$item->due_at->setTimezone($couple->timezone)->format('d.m.Y H:i')]) }}</p>
<a class="button button--quiet" href="{{ route('requests.show',[$couple,$item]) }}">{{ __('Открыть просьбу') }}</a>
</x-card>
@empty<x-card class="section-space">
<p>{{ __('Просьб пока нет. Начните с небольшой помощи друг другу.') }}</p>
</x-card>
@endforelse
{{ $requests->links() }}
<a class="button button--quiet section-space" href="{{ route('couples.show',$couple) }}">{{ __('Наша пара') }}</a>
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
