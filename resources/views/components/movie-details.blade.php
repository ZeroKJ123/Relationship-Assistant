@props(['movie'])
@if($movie->photo_path)<img class="movie-poster" src="{{ route('movies.photo',[$movie,'v'=>$movie->updated_at?->timestamp]) }}" alt="{{ $movie->title }}" loading="lazy">@endif
<h2>{{ $movie->title }}</h2>@if($movie->link)<a href="{{ $movie->link }}" target="_blank" rel="noopener noreferrer">{{ __('О фильме') }} ↗</a>@endif
