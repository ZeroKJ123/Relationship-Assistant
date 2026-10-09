@extends('layouts.app')
@section('title', __('Экономика сердечек'))
@section('content')
<x-card class="start-card form-stack"><h1>{{ __('Экономика сердечек') }}</h1><p>{{ __('Версия :version', ['version'=>$current->version]) }}</p><p>{{ __('Новые тарифы не изменяют принятую цену, прошлые награды и штрафы. Лимиты применяются к новым операциям. Снижение потолка не списывает уже имеющийся баланс.') }}</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p class="field-error">{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ route('admin.economy.update') }}" class="form-stack" data-submit-lock>@csrf @method('put')
<input type="hidden" name="expected_version" value="{{ old('expected_version',$current->version) }}">
@foreach($labels as $key=>$label)<x-field :name="$key" :label="__($label)" type="number" :value="old($key,$current->settings[$key])" min="0" max="1000" step="1" required />@endforeach
<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю новые правила экономики') }}</label><x-button type="submit">{{ __('Сохранить правила') }}</x-button></form></x-card>
<x-card class="section-space form-stack"><h2>{{ __('История правил') }}</h2>@foreach($history as $entry)<details><summary>{{ __('Версия :version', ['version'=>$entry->version]) }} · {{ $entry->created_at->format('d.m.Y H:i') }} UTC · {{ $entry->created_by_id === null ? __('Начальные правила') : __('Администратор №:id', ['id'=>$entry->created_by_id]) }}</summary>@foreach($labels as $key=>$label)<p>{{ __($label) }}: {{ $entry->settings[$key] }}</p>@endforeach</details>@endforeach</x-card>{{ $history->links() }}
@endsection
