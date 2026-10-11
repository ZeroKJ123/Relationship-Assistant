@props(['couple','latestMoods'])
<x-card tone="purple" class="section-space">
<h2>{{ __('Как мы себя чувствуем') }}</h2>
@forelse($latestMoods as $entry)<p><strong>{{ $entry->user->name }}</strong> · {{ $entry->level->label() }} <x-mood-decoration :entry="$entry" /><br><span>{{ $entry->recorded_at->setTimezone($couple->timezone)->format('d.m.Y H:i') }}</span></p>@empty<p>{{ __('За последние 30 дней оценок пока нет. Можно отметить настроение в любое время.') }}</p>@endforelse
<x-mood-request :couple="$couple" />
<a class="button button--quiet" href="{{ route('mood.index',$couple) }}">{{ __('Настроение и история') }}</a>
</x-card>
