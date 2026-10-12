<div class="dashboard" data-dashboard>
<x-form-feedback />

    @if($dashboard->pendingMoodPrompt?->is_manual)<x-card tone="purple" class="section-space form-stack"><p>{{ __('Партнёр спрашивает, как вы себя чувствуете.') }}</p><a class="button button--primary" href="{{ route('mood.index',$dashboard->couple) }}">{{ __('Ответить о настроении') }}</a></x-card>@endif
    @if($dashboard->movieChoice)<x-card class="section-space"><p>{{ __('Партнёр приглашает выбрать фильм вместе') }}</p><a class="button button--primary" href="{{ route('movies.choice',$dashboard->couple) }}">{{ __('Открыть выбор фильма') }}</a></x-card>@endif
    @if($dashboard->choreDraw)<x-card class="section-space"><p>{{ __('Предложена жеребьёвка дела') }}</p><strong>{{ $dashboard->choreDraw->description }}</strong><a class="button button--primary" href="{{ route('chores.show',[$dashboard->couple,$dashboard->choreDraw]) }}">{{ __('Открыть жеребьёвку') }}</a></x-card>@endif

@foreach($appearance->blocks($dashboard) as $block)
<div data-layout-block="{{ $block['id'] }}" class="dashboard-block @if($block['tone'] !== 'plain') card card--{{ $block['tone'] }} @endif">
@if($block['title'] !== '' && $block['type'] !== 'text')<h2>{{ $block['title'] }}</h2>@endif
@include('dashboard.blocks.'.$block['type'])
</div>
@endforeach
<p class="page-transition" role="status" hidden data-page-transition>{{ __('Открываем страницу…') }}</p>
</div>
