@extends('layouts.app')
@section('title', __('Наша серия'))
@section('content')
<h1 class="screen-title">{{ __('Наша серия') }}</h1>
<p class="lead">{{ __('День засчитывается, когда вы оба отметили настроение. Пропустили — серию можно восстановить.') }}</p>
<x-form-feedback />
<div class="streak-hero"><x-flame :level="$streak->level" /><strong>{{ $streak->days }}</strong><span>{{ __('дн. подряд') }}</span></div>
<dl class="facts">
    <div><dt>{{ __('Сегодня') }}</dt><dd>{{ $streak->getStatus() }}</dd></div>
    <div><dt>{{ __('Вы') }}</dt><dd>{{ $streak->hasActorMarked ? __('настроение отмечено') : __('ещё не отметили') }}</dd></div>
    <div><dt>{{ __('Партнёр') }}</dt><dd>{{ $streak->hasPartnerMarked ? __('настроение отмечено') : __('ещё не отметил(а)') }}</dd></div>
    <div><dt>{{ __('Восстановлений в этом месяце') }}</dt><dd>{{ __(':left из :total', ['left'=>$streak->restoresLeft,'total'=>$restoresPerMonth]) }}</dd></div>
</dl>
@unless($streak->hasActorMarked)<a class="button button--primary button--wide section-space" href="{{ route('mood.index',$couple) }}">{{ __('Открыть настроение') }}</a>@endunless
@if($canEdit && $streak->canRestore())
<x-card tone="yellow" class="section-space form-stack"><h2>{{ __('Серия прервалась') }}</h2>
<p>{{ __('Пропущено дней: :missed. Восстановление вернёт серию из :days дн. и потратит столько же восстановлений.', ['missed'=>count($streak->missedDays),'days'=>$streak->restoredDays]) }}</p>
<form method="post" action="{{ route('streak.restore',$couple) }}" class="form-stack" data-submit-lock>@csrf<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю восстановление серии') }}</label><x-button type="submit">{{ __('Восстановить серию') }}</x-button></form></x-card>
@endif
<h2 class="group-label"><span>{{ __('Огоньки') }}</span></h2>
@foreach([__('Искра'), __('Огонёк'), __('Пламя'), __('Костёр'), __('Звёздное пламя')] as $index => $name)
<div class="row"><x-flame :level="$index + 1" /><div class="row__body"><strong class="row__title">{{ $name }}</strong><span class="row__meta">{{ __('от :days дн.', ['days'=>$levelDays[$index]]) }}</span></div>@if($streak->level === $index + 1)<x-status tone="action">{{ __('сейчас') }}</x-status>@endif</div>
@endforeach
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
