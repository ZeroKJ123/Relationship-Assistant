
    <section class="dashboard-greeting" aria-labelledby="greeting-title">
        <div><p class="eyebrow">{{ __('Для вас двоих') }}</p><h1 id="greeting-title">{{ __('Привет, :name', ['name'=>$dashboard->actor->name]) }} <span aria-hidden="true">♡</span></h1><p>{{ __('Забота начинается с маленьких вещей') }}</p></div>
        <div class="partner-summary">
            @if($dashboard->partner)<a class="partner-profile-link" href="{{ route('profiles.show',$dashboard->partner) }}" aria-label="{{ __('Открыть профиль партнёра') }}">@endif
            <x-avatar :name="$dashboard->partner?->name ?? $dashboard->actor->name" :src="$dashboard->partner?->avatar_path ? route('profiles.avatar',[$dashboard->partner, 'v'=>$dashboard->partner->updated_at?->timestamp]) : (!$dashboard->partner && $dashboard->actor->avatar_path ? route('profile.avatar') : null)" />
            <strong>{{ $dashboard->partner?->name ?? __('Ваша история впереди') }}</strong>@if($dashboard->partner)</a>@endif
            @if($dashboard->partner)
                <p>{{ __('Вместе :days дн.', ['days'=>$dashboard->togetherDays]) }} ({{ $dashboard->togetherDuration }})</p>
                @if($dashboard->couple->married_on)
                    <p>{{ __('Свадьба: :date', ['date'=>$dashboard->couple->married_on->format('d.m.Y')]) }}</p>
                @endif
            @else
                <p>{{ __('Пригласите партнёра в ваше пространство') }}</p>
            @endif
        </div>
    </section>
