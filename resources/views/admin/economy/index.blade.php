@extends('layouts.admin')
@section('title', __('Экономика сердечек'))
@section('admin-content')
<x-card class="settings-card">
    <x-status>{{ __('Только для администратора · Версия :version', ['version' => $current->version]) }}</x-status>
    <h1 class="form-title">{{ __('Экономика сердечек') }}</h1>
    <p>{{ __('Новые тарифы не изменяют принятую цену, прошлые награды и штрафы. Лимиты применяются к новым операциям. Снижение потолка не списывает уже имеющийся баланс.') }}</p>
    <x-form-feedback />
    <form method="post" action="{{ route('admin.economy.update') }}" class="form-stack" data-submit-lock>
        @csrf @method('put')
        <input type="hidden" name="expected_version" value="{{ old('expected_version', $current->version) }}">
        @foreach($groups as $heading => $keys)
            <fieldset class="settings-group">
                <legend>{{ __($heading) }}</legend>
                @foreach($keys as $key)<x-field :name="$key" :label="__($labels[$key])" :error="$errors->first($key)" type="number" :value="old($key, $current->settings[$key])" min="0" max="1000" step="1" required />@endforeach
            </fieldset>
        @endforeach
        <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю новые правила экономики') }}</label>
        <x-button type="submit">{{ __('Сохранить правила') }}</x-button>
    </form>
</x-card>
<x-card class="settings-card form-stack">
    <h2>{{ __('История правил') }}</h2>
    @foreach($history as $entry)
        <details class="settings-history">
            <summary>{{ __('Версия :version', ['version' => $entry->version]) }} · {{ $entry->created_at->format('d.m.Y H:i') }} UTC · {{ $entry->created_by_id === null ? __('Начальные правила') : __('Администратор №:id', ['id' => $entry->created_by_id]) }}</summary>
            <dl class="settings-values">@foreach($labels as $key => $label)<div><dt>{{ __($label) }}</dt><dd>{{ $entry->settings[$key] }}</dd></div>@endforeach</dl>
        </details>
    @endforeach
    {{ $history->links() }}
</x-card>
<a class="button button--quiet section-space" href="{{ route('profile.show') }}">{{ __('К профилю') }}</a>
@endsection
@section('navigation')<x-bottom-navigation />@endsection
