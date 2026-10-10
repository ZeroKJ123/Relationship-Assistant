@extends('layouts.app')
@section('title', __('Наши дела'))
@section('content')
<x-card class="start-card"><h1 class="screen-title">{{ __('Наши дела') }}</h1><p>{{ __('Согласие создаёт дело. Отметьте выполнение, затем дождитесь проверки партнёром.') }}</p></x-card>
<nav class="catalog-tabs duty-tabs section-space" aria-label="{{ __('Статус дела') }}">@foreach($filters as $option)<a class="button {{ $filter === $option ? 'button--primary' : 'button--quiet' }}" href="{{ route('duties.index',[$couple,'filter'=>$option->value]) }}" @if($filter === $option) aria-current="page" @endif>{{ __($option->label()) }}</a>@endforeach</nav>
@forelse($duties as $duty)<x-duty-card :duty="$duty" :couple="$couple" />@empty<x-card class="section-space form-stack"><h2>{{ __('Здесь пока нет дел') }}</h2><p>{{ __('В выбранной вкладке нет дел. Посмотрите все или создайте новую просьбу.') }}</p><a href="{{ route('duties.index',[$couple,'filter'=>'all']) }}">{{ __('Показать все дела') }}</a></x-card>@endforelse
{{ $duties->links() }}
<a class="button button--quiet section-space" href="{{ route('couples.show',$couple) }}">{{ __('Наша пара') }}</a>
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
