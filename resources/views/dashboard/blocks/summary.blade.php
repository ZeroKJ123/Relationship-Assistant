    <div class="dashboard-summary">
        <form class="mood-card" method="post" action="{{ $dashboard->couple ? route('mood.prompts.store',$dashboard->couple) : '#' }}" data-mood-request @if($dashboard->partner) data-mood-history="{{ route('mood.day',$dashboard->couple) }}" data-mood-self="{{ route('mood.self.store',$dashboard->couple) }}" @endif>
            @csrf
            <button type="submit" class="mood-card__button" @disabled(!$dashboard->partner) aria-label="{{ __('Узнать настроение партнёра') }}" aria-describedby="mood-card-help">
                <span class="mood-card__heading">{{ __('Настроение партнёра') }}</span>
                @forelse($dashboard->latestMoods as $entry)
                    <span class="partner-mood"><x-mood-decoration :entry="$entry" /><strong class="partner-mood__level">{{ $entry->level->label() }}</strong></span>
                    <time class="mood-card__time" datetime="{{ $entry->recorded_at->toIso8601String() }}">{{ $entry->recorded_at->setTimezone($dashboard->couple->timezone)->format('d.m.Y H:i') }}</time>
                @empty<strong class="partner-mood__level">{{ __('Пока не известно') }}</strong>@endforelse
            </button>
            <span class="sr-only" id="mood-card-help">{{ __('Одно нажатие — спросить партнёра, двойное — указать своё настроение, удержание — история по дням.') }}</span>
        </form>
        <section>
            <h2>{{ __('Мои сердечки') }}</h2><a class="wallet-total" href="{{ route('hearts.index') }}" aria-label="{{ __('Баланс :amount сердечек. Открыть историю', ['amount'=>$dashboard->wallet->balance]) }}"><x-icon name="heart" /> {{ $dashboard->wallet->balance }}</a>
            <p>{{ __('Свободно: :amount', ['amount'=>$dashboard->wallet->available]) }}<br>{{ __('В резерве: :amount', ['amount'=>$dashboard->wallet->reserved]) }}</p>
            @if($dashboard->wallet->balance < 0)<p>{{ __('Баланс в минусе. Обычная бесплатная еда и выполнение дел доступны.') }}</p>@endif
        </section>
    </div>
