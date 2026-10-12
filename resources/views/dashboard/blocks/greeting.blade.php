    <section class="dashboard-greeting" aria-labelledby="greeting-title">
        <p class="eyebrow">{{ __('Для вас двоих') }}</p>
        <h1 id="greeting-title">{!! __('Привет, :name', ['name'=>sprintf('<em>%s</em>', e($dashboard->actor->name))]) !!}</h1>
        <div class="greeting-compliment" data-compliment data-compliments="{{ json_encode($dashboard->partner?->compliments ?? []) }}"><p>{{ $dashboard->compliment }}</p></div>
        <div class="partner-summary">
            @if($dashboard->partner)<a class="partner-profile-link" href="{{ route('profiles.show',$dashboard->partner) }}" aria-label="{{ __('Открыть профиль партнёра') }}">@endif
            <x-avatar :name="$dashboard->partner?->name ?? $dashboard->actor->name" :src="$dashboard->partner?->avatar_path ? route('profiles.avatar',[$dashboard->partner, 'v'=>$dashboard->partner->updated_at?->timestamp]) : (!$dashboard->partner && $dashboard->actor->avatar_path ? route('profile.avatar') : null)" />
            <span class="partner-summary__body"><strong>{{ $dashboard->partner?->name ?? __('Ваша история впереди') }}</strong>
            @if($dashboard->partner)
                <span>{{ __('Вместе :days дн.', ['days'=>$dashboard->togetherDays]) }} ({{ $dashboard->togetherDuration }})</span>
                @if($dashboard->couple->married_on)<br><span>{{ __('Свадьба: :date', ['date'=>$dashboard->couple->married_on->format('d.m.Y')]) }}</span>@endif
            @else
                <span>{{ __('Пригласите партнёра в ваше пространство') }}</span>
            @endif
            </span>
            @if($dashboard->partner)</a><span class="partner-summary__days" aria-hidden="true"><strong>{{ $dashboard->togetherDays }}</strong><span>{{ __('дней') }}</span></span>@endif
        </div>
        @if($dashboard->nextDate)<a class="row" href="{{ route('memorable_dates.index',$dashboard->couple) }}"><x-icon name="date" /><span class="row__body"><strong class="row__title">{{ $dashboard->nextDate->title }}</strong><span class="row__meta">{{ $dashboard->nextDate->date->isoFormat('D MMMM') }}@if($dashboard->nextDate->years) · {{ __(':count-я годовщина', ['count'=>$dashboard->nextDate->years]) }}@endif</span></span><x-status :tone="$dashboard->nextDate->daysLeft <= 3 ? 'warning' : 'neutral'">{{ $dashboard->nextDate->getCountdown() }}</x-status></a>@endif
    </section>
