@extends('layouts.app')
@section('title', __('Просьба партнёру'))
@section('content')
<x-card class="start-card form-stack">
<x-status>{{ __($partnerRequest->status->label()) }}</x-status>
<h1>{{ __($partnerRequest->type->label()) }}</h1>
<p>{{ $partnerRequest->author->name }} → {{ $partnerRequest->recipient->name }}</p>
<p class="request-description">{{ $partnerRequest->description }}</p>
<p>{{ __('Выполнить до :date', ['date'=>$partnerRequest->due_at->setTimezone($couple->timezone)->format('d.m.Y H:i')]) }}</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>
@endif
@if($errors->any())<div role="alert">
@foreach($errors->all() as $error)<p class="field-error">{{ $error }}</p>
@endforeach</div>
@endif
@if($partnerRequest->decline_reason)<p>{{ __($partnerRequest->decline_reason->label()) }}</p>
@if($partnerRequest->decline_penalty === 0)<p>{{ __('Штраф за отказ: 0 сердечек.') }}</p>
@endif
@endif
@if($partnerRequest->status === \App\Enums\RequestStatus::Accepted)<p>{{ __('Договорились. Выполнение просьбы и условия нужно будет подтвердить отдельно.') }}</p>
@endif
</x-card>
@if($currentCondition)<x-card class="section-space form-stack">
<h2>{{ __('Встречное условие · вариант :version', ['version'=>$currentCondition->version]) }}</h2>
<p>{{ __($currentCondition->type->label()) }}</p>
<p class="request-description">{{ $currentCondition->description }}</p>
<p>{{ __('Срок условия: :date', ['date'=>$currentCondition->due_at->setTimezone($couple->timezone)->format('d.m.Y H:i')]) }}</p>
@if($currentCondition->accepted_at)<p>{{ __('Условие принято автором просьбы.') }}</p>
@endif
@if($partnerRequest->status === \App\Enums\RequestStatus::Conditional)
@can('acceptCondition',$partnerRequest)<form method="post" action="{{ route('requests.condition.store',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>
@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}">
<label class="confirmation-check">
<input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю решение по этому варианту условия') }}</label>
<div class="confirmation-actions">
<x-button type="submit" name="decision" value="accept">{{ __('Принимаю условие') }}</x-button>
<x-button type="submit" name="decision" value="decline" variant="quiet">{{ __('Отклонить просьбу с условием') }}</x-button>
</div>
</form>
@endcan
@endif</x-card>
@endif
@can('respond',$partnerRequest)
@if($partnerRequest->status === \App\Enums\RequestStatus::Pending)
<x-card class="section-space">
<h2>{{ __('Ваш ответ') }}</h2>
<form method="post" action="{{ route('requests.responses.store',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>
@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}">
<div class="field">
<label for="reason">{{ __('Причина при отказе') }}</label>
<select name="reason" id="reason">
<option value="ordinary">{{ __('Не могу согласиться') }}</option>
@if($partnerRequest->type === \App\Enums\RequestType::Food)<option value="no_food_money">{{ __('Нет денег на продукты — без штрафа') }}</option>
@endif</select>
</div>
<label class="confirmation-check">
<input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю выбранный ответ') }}</label>
<div class="confirmation-actions">
<x-button type="submit" name="response" value="agree">{{ __('Согласиться') }}</x-button>
<x-button type="submit" name="response" value="decline" variant="quiet">{{ __('Отказать') }}</x-button>
</div>
</form>
</x-card>
@endif
@if(in_array($partnerRequest->status,[\App\Enums\RequestStatus::Pending,\App\Enums\RequestStatus::Conditional],true))
<x-card class="section-space">
<h2>{{ __('Предложить встречное условие') }}</h2>
<form method="post" action="{{ route('requests.responses.store',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>
@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}">
<input type="hidden" name="response" value="conditional">
<div class="field">
<label for="condition_type">{{ __('Вид условия') }}</label>
<select name="condition_type" id="condition_type">
@foreach(\App\Enums\ConditionType::cases() as $type)<option value="{{ $type->value }}" @selected(old('condition_type') === $type->value)>{{ __($type->label()) }}</option>
@endforeach</select>
</div>
<div class="field">
<label for="description">{{ __('Условие') }}</label>
<textarea id="description" name="description" rows="4" maxlength="2000" required>{{ old('description') }}</textarea>
</div>
<x-field name="due_at" :label="__('Выполнить условие до')" type="datetime-local" :value="old('due_at','')" required />
<p>{{ __('Часовой пояс: :zone. Автор просьбы примет этот вариант отдельно.', ['zone'=>$couple->timezone]) }}</p>
<label class="confirmation-check">
<input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю предложение условия') }}</label>
<x-button type="submit">{{ __('Отправить условие') }}</x-button>
</form>
</x-card>
@endif
@endcan
@if($conditions->total() > 1)<x-card class="section-space">
<h2>{{ __('История условий') }}</h2>
@foreach($conditions as $condition)<p>{{ __('Вариант :version', ['version'=>$condition->version]) }}: {{ $condition->description }}</p>
@endforeach</x-card>
@endif
{{ $conditions->links() }}<a class="button button--quiet section-space" href="{{ route('requests.index',$couple) }}">{{ __('Все просьбы') }}</a>
@endsection
