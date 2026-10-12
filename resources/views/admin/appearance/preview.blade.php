<!DOCTYPE html><html lang="ru" style="{{ $appearance->css() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">@vite('resources/css/app.css')</head><body data-decoration="{{ $appearance->theme()['decoration'] }}"><main class="app-shell dashboard">
@foreach($settings = $appearance->get()['layout']['blocks'] as $block)
<section data-preview-block="{{ $block['id'] }}" class="dashboard-block"><div data-preview-title></div><div data-preview-body>@if($block['type'] === 'text')<x-card><h2 data-preview-text-title>{{ $block['title'] }}</h2><p data-preview-text>{{ $block['text'] }}</p></x-card>@elseif($block['type'] === 'tiles')
<nav class="dashboard-tiles" aria-label="{{ __('Быстрые действия') }}">@foreach($appearance->get()['layout']['tiles'] as $tile)<a class="card @if($tile['tone'] !== 'plain') card--{{ $tile['tone'] }} @endif" data-preview-tile="{{ $tile['id'] }}" href="#"><x-illustration :name="$tile['id'] === 'chores' ? 'rating' : $tile['id']" /><strong>{{ $tile['title'] ?: $catalog->tileLabels()[$tile['id']] }}</strong></a>@endforeach</nav>
@else @include('dashboard.blocks.'.$block['type']) @endif</div><p class="preview-empty" data-preview-empty hidden>{{ $catalog->blockLabels()[$block['type']] }} · {{ __('В этом аккаунте пока нет данных') }}</p></section>
@endforeach
</main></body></html>
