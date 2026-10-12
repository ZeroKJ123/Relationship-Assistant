@extends('layouts.app')
@section('title', __('История настроения'))
@section('content')
<h1 class="screen-title">{{ __('Настроение партнёра') }}</h1>
<p class="lead">{{ $partner->name }}</p>
<nav class="catalog-tabs section-space" aria-label="{{ __('Чьё настроение') }}"><a href="{{ route('mood.index',$couple) }}">{{ __('Моё') }}</a><a href="{{ route('mood.day',$couple) }}" aria-current="page">{{ $partner->name }}</a></nav>
<section class="section-space"><x-mood-calendar :calendar="$calendar" :caption="__('Настроение партнёра по дням')" :couple="$couple" /></section>
<section class="section-space">
<h2 class="group-label"><span>{{ $dateTitle }}</span><span>{{ __('Записей: :count', ['count'=>$summary['count']]) }}</span></h2>
@if($summary['average'] !== null)<p class="request-description--primary section-space">{{ __('Среднее за день: :value из 3', ['value'=>$summary['average']]) }}</p><svg class="mood-chart" viewBox="0 0 320 140" role="img" aria-label="{{ __('Настроение за день. Пунктир — среднее значение.') }}"><path d="M20 20H300 M20 65H300 M20 110H300" fill="none" class="mood-chart__grid"/><text x="2" y="24">3</text><text x="2" y="69">2</text><text x="2" y="114">1</text><text x="20" y="133">00:00</text><text x="265" y="133">24:00</text><polyline points="{{ $chart['points'] }}" fill="none" class="mood-chart__line" stroke-width="3"/><line x1="20" x2="300" y1="{{ $chart['average_y'] }}" y2="{{ $chart['average_y'] }}" class="mood-chart__average" stroke-width="2" stroke-dasharray="5 4"/>@foreach($chart['dots'] as $dot)<circle cx="{{ $dot['x'] }}" cy="{{ $dot['y'] }}" r="4" class="mood-chart__dot" />@endforeach</svg><p class="field-hint">{{ __('Пунктир — среднее за день. График показывает последние 200 отметок; среднее учитывает все.') }}</p><p class="field-hint">{{ __('1 — не очень · 2 — нейтрально · 3 — хорошо') }}</p>@else<p class="row row--empty">{{ __('За этот день записей нет') }}</p>@endif
@foreach($entries as $entry)<div class="row"><span class="mood-level__face" data-level="{{ $entry->level->value }}" aria-hidden="true"><x-icon :name="$entry->level->icon()" /></span><div class="row__body"><strong class="row__title">{{ $entry->level->label() }}</strong><time class="row__meta" datetime="{{ $entry->recorded_at->toIso8601String() }}">{{ $entry->recorded_at->setTimezone($couple->timezone)->format('H:i') }}</time></div><x-mood-decoration :entry="$entry" /></div>@endforeach
{{ $entries->links() }}
</section>
@endsection
@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
