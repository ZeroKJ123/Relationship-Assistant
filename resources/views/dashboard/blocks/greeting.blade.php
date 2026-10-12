    <section class="dashboard-greeting" aria-labelledby="greeting-title">
        <p class="eyebrow">{{ __('Для вас двоих') }}</p>
        <h1 id="greeting-title">{!! __('Привет, :name', ['name'=>sprintf('<em>%s</em>', e($dashboard->actor->name))]) !!}</h1>
        <p>{{ __('Забота начинается с маленьких вещей') }}</p>
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
    </section>
