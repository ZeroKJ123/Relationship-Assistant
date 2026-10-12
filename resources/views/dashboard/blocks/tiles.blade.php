<nav class="dashboard-tiles" style="--tile-columns:{{ $appearance->get()['layout']['columns'] }}" aria-label="{{ __('Быстрые действия') }}">
@foreach($appearance->tiles($dashboard) as $tile)
<a class="card @if($tile['tone'] !== 'plain') card--{{ $tile['tone'] }} @endif" data-layout-tile="{{ $tile['id'] }}" href="{{ $appearance->tileDestinations($dashboard)[$tile['id']] }}"><x-illustration :name="$tile['id']" /><strong>{{ $tile['title'] ?: $appearance->tileLabels()[$tile['id']] }}</strong></a>
@endforeach
</nav>
