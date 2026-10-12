@extends('layouts.app')
@section('title', __('Наши дела'))
@section('content')
<h1 class="screen-title">{{ __('Наши дела') }}</h1>
<p class="lead">{{ __('Согласие создаёт дело. Отметьте выполнение, затем дождитесь проверки партнёром.') }}</p>
<nav class="catalog-tabs duty-tabs section-space" aria-label="{{ __('Статус дела') }}">@foreach($filters as $option)<a href="{{ route('duties.index',[$couple,'filter'=>$option->value]) }}" @if($filter === $option) aria-current="page" @endif>{{ __($option->label()) }}</a>@endforeach</nav>
@foreach($groups as $group)
<h2 class="group-label"><span>{{ $group['label'] }}</span><span @class(['group-label__count--alert' => $group['isAlert']])>{{ $group['duties']->count() }}</span></h2>
@foreach($group['duties'] as $duty)<x-duty-card :duty="$duty" :couple="$couple" />@endforeach
@endforeach
@if($duties->isEmpty())<x-card class="section-space form-stack"><h2>{{ __('Здесь пока нет дел') }}</h2><p>{{ __('В выбранной вкладке нет дел. Посмотрите все или создайте новую просьбу.') }}</p><a href="{{ route('duties.index',[$couple,'filter'=>'all']) }}">{{ __('Показать все дела') }}</a></x-card>@endif
{{ $duties->links() }}
<a class="button button--quiet section-space" href="{{ route('couples.show',$couple) }}">{{ __('Наша пара') }}</a>
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
