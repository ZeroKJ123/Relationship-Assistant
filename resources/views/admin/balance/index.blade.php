@extends('layouts.admin')
@section('title', __('Баланс пользователей'))
@section('admin-content')
<h1 class="screen-title">{{ __('Баланс пользователей') }}</h1>
<p>{{ __('Начисление и списание записываются в историю с причиной. Для тестов начисление может превышать обычный лимит наград; резерв принятых услуг защищён.') }}</p>
<form method="get" class="form-stack"><x-field name="search" :label="__('Имя или логин')" :value="$search" maxlength="80" /><x-button type="submit" variant="quiet">{{ __('Найти') }}</x-button></form>
@foreach($users as $account)
<x-card class="section-space"><h2>{{ $account->name }} · {{ $account->login }}</h2><p>{{ __('Баланс') }}: <strong>{{ $wallets->get($account->id)?->balance ?? __('Ещё не открыт') }}</strong> ♥</p>
<details><summary>{{ __('Изменить баланс') }}</summary><form method="post" action="{{ route('admin.panel.balance.update') }}" class="form-stack" data-submit-lock>@csrf
<input type="hidden" name="user_id" value="{{ $account->id }}"><input type="hidden" name="operation_key" value="{{ $operationKey }}">
<x-select-field :name="'direction'" :label="__('Операция')"><option value="add">{{ __('Добавить') }}</option><option value="subtract">{{ __('Отнять') }}</option></x-select-field>
<x-field :id="'amount-'.$account->id" name="amount" type="number" :label="__('Количество сердечек')" min="1" :max="\App\Services\Hearts\EconomyRules::MAXIMUM_SETTING" required />
<x-field :id="'reason-'.$account->id" name="reason" :label="__('Причина')" maxlength="500" required />
<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю изменение баланса этого пользователя') }}</label>
<x-button type="submit">{{ __('Изменить баланс') }}</x-button></form></details></x-card>
@endforeach
{{ $users->links() }}
@endsection
