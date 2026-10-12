@extends('layouts.app')
@section('title', __('Просьба партнёру'))
@section('back', route('requests.index',$couple))
@section('back-label', __('Просьбы'))
@section('content')
<div class="page-head"><p class="eyebrow">{{ __($partnerRequest->type->label()) }}</p><x-status :tone="$progress->getTone($partnerRequest)">{{ $progress->getLabel($partnerRequest) }}</x-status></div>
<h1 class="screen-title request-description request-description--primary">{{ $partnerRequest->description }}</h1>
@if($isAuthor)<p class="lead">{{ __('Это ваша просьба. Отвечает :name.', ['name'=>$partnerRequest->recipient->name]) }}</p>@endif
@if($isRecipient)<p class="lead">{{ __('Вас просит :name. Ответ за вами.', ['name'=>$partnerRequest->author->name]) }}</p>@endif
<ol class="steps" aria-label="{{ __('Этапы просьбы') }}">
    @foreach([__('Создано'), __('Ответ'), __('Выполнение')] as $index => $step)
    <li @if($progress->getStep($partnerRequest) > $index + 1) data-state="done" @elseif($progress->getStep($partnerRequest) === $index + 1) data-state="current" aria-current="step" @endif>{{ $step }}</li>
    @endforeach
</ol>
<dl class="facts">
    <div><dt>{{ __('Кто и кого просит') }}</dt><dd><strong>{{ $partnerRequest->author->name }}</strong> → {{ $partnerRequest->recipient->name }}</dd></div>
    <div><dt>{{ __('Срок') }}</dt><dd>{{ __('Выполнить до :date', ['date'=>$partnerRequest->due_at->setTimezone($couple->timezone)->format('d.m.Y H:i')]) }}</dd></div>
    @if($partnerRequest->heart_price > 0)<div><dt>{{ __('Стоимость') }}</dt><dd>{{ __('Цена: :amount сердечек', ['amount'=>$partnerRequest->heart_price]) }}</dd></div>@endif
    @if($partnerRequest->moneyRequest)<div><dt>{{ __('Сумма') }}</dt><dd>{{ $partnerRequest->moneyRequest->amount }} {{ $partnerRequest->moneyRequest->currency->value }}</dd></div>@endif
    @if($partnerRequest->is_very_wanted)<div><dt>{{ __('Важность') }}</dt><dd>{{ __('Очень хочу') }}</dd></div>@endif
    @if($partnerRequest->decline_reason)<div><dt>{{ __('Причина отказа') }}</dt><dd>{{ __($partnerRequest->decline_reason->label()) }}</dd></div>@endif
    @if($partnerRequest->decline_reason && $partnerRequest->decline_penalty === 0)<div><dt>{{ __('Штраф') }}</dt><dd>{{ __('Штраф за отказ: 0 сердечек.') }}</dd></div>@endif
</dl>
<div class="form-stack">
@if($partnerRequest->created_by_admin_id)<p class="hint"><x-icon name="info" /><span><strong>{{ __('Системная тестовая просьба') }}</strong> · {{ __('Проверяется по обычным правилам пары. Уведомления и изменения сердечек настоящие.') }}</span></p>@endif
@if($partnerRequest->rating)<x-card tone="pink"><h2>{{ __('Оценка поступка: :score из 5', ['score'=>$partnerRequest->rating]) }}</h2>@if($partnerRequest->rating_comment)<p class="request-description">{{ $partnerRequest->rating_comment }}</p>@endif</x-card>@endif
<x-social-invitation :request="$partnerRequest" :timezone="$couple->timezone" />
@if($partnerRequest->dish_snapshot)<x-product-photo :src="!empty($partnerRequest->dish_snapshot['photo_path']) ? route('photos.requests',$partnerRequest) : null" :alt="$partnerRequest->dish_snapshot['title'] ?? ''" symbol="♨" /><p>{{ $partnerRequest->dish_snapshot['title'] }} · {{ $partnerRequest->dish_kind->label() }}</p><p>{{ $partnerRequest->dish_snapshot['ingredients'] }}</p><p>{{ __('Продукты ≈ :amount :currency · порций: :portions',['amount'=>$partnerRequest->dish_snapshot['estimate'],'currency'=>$partnerRequest->dish_snapshot['currency'],'portions'=>$partnerRequest->dish_snapshot['portions']]) }}</p>@endif
@if($partnerRequest->moneyRequest)<p class="hint"><x-icon name="coin" /><span>{{ __('Оплата отмечается плательщиком; поступление отдельно подтверждает получатель. Денежные переводы не дают награду.') }}</span></p>@if($partnerRequest->status === \App\Enums\RequestStatus::Accepted)<h2 class="group-label"><span>{{ __('Реквизиты получателя') }}</span></h2><p class="row request-description">{{ $partnerRequest->moneyRequest->payment_details_snapshot }}</p>@endif @endif
@if(session('status'))<p role="status">{{ session('status') }}</p>
@endif
@if($errors->any())<div role="alert">
@foreach($errors->all() as $error)<p class="field-error">{{ $error }}</p>
@endforeach</div>
@endif
@if(in_array($partnerRequest->status,[\App\Enums\RequestStatus::Pending,\App\Enums\RequestStatus::Conditional],true) && !$partnerRequest->due_at->isFuture())<p class="hint"><x-icon name="info" /><span>{{ __('Срок просьбы истёк. Отправьте новую просьбу с актуальным сроком.') }}</span></p>@endif

@foreach($partnerRequest->duties as $duty)
<x-request-duty :duty="$duty" :couple="$couple" :partner-request="$partnerRequest" />
@endforeach
</div>
<x-request-cancellation :couple="$couple" :partner-request="$partnerRequest" />
@if($currentCondition)<x-card class="section-space form-stack">
<h2>{{ __('Условие партнёра') }}</h2>
<p class="request-description">{{ $currentCondition->description }}</p>
@if($currentCondition->gift_snapshot)<x-product-photo :src="!empty($currentCondition->gift_snapshot['photo_path']) ? route('photos.conditions',$currentCondition) : null" :alt="$currentCondition->gift_snapshot['title'] ?? ''" /><p>{{ $currentCondition->gift_snapshot['title'] }}</p><p class="price-chip">{{ $currentCondition->gift_snapshot['price'] }} {{ $currentCondition->gift_snapshot['currency'] }}</p><p>{{ __('Получатель подтвердит подарок отдельно. После получения желание уйдёт в архив.') }}</p>@endif
@if($currentCondition->amount)<p class="price-chip">{{ $currentCondition->amount }} {{ $currentCondition->currency->value }}</p>@if($currentCondition->accepted_at && $partnerRequest->status === \App\Enums\RequestStatus::Accepted)<h3>{{ __('Реквизиты получателя') }}</h3><p class="request-description">{{ $currentCondition->payment_details_snapshot }}</p>@endif @endif
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
@if($partnerRequest->status === \App\Enums\RequestStatus::Pending && $partnerRequest->due_at->isFuture())
<x-card class="section-space">
<h2>{{ __('Ваш ответ') }}</h2>
@if($partnerRequest->type === \App\Enums\RequestType::Rating)
<form method="post" action="{{ route('requests.rating',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}"><x-field name="rating" type="number" :label="__('Оценка поступка от 1 до 5')" min="1" max="5" step="1" required /><div class="field"><label for="comment">{{ __('Комментарий — по желанию') }}</label><textarea id="comment" name="comment" maxlength="1000">{{ old('comment') }}</textarea></div><label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю оценку') }}</label><x-button type="submit">{{ __('Отправить оценку') }}</x-button></form>
@endif
@if($partnerRequest->type !== \App\Enums\RequestType::Rating)
<form method="post" action="{{ route('requests.responses.store',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>
    @csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}"><input type="hidden" name="response" value="agree">
    <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю выбранный ответ') }}</label>
    <x-button type="submit">{{ __('Согласиться') }}</x-button>
</form>
@endif
<details class="section-space" @if(old('response') === 'decline' && $errors->any()) open @endif>
    <summary>{{ __('Отказать') }}</summary>
    <form method="post" action="{{ route('requests.responses.store',[$couple,$partnerRequest]) }}" class="form-stack section-space" data-submit-lock>
        @csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}"><input type="hidden" name="response" value="decline">
        @if($partnerRequest->type === \App\Enums\RequestType::Food)
            <fieldset class="response-reasons"><legend>{{ __('Причина отказа') }}</legend><label class="choice-chip"><input type="radio" name="reason" value="ordinary" @checked(old('reason','ordinary') === 'ordinary')>{{ __('Не могу согласиться') }}</label><label class="choice-chip"><input type="radio" name="reason" value="no_food_money" @checked(old('reason') === 'no_food_money')>{{ __('Нет денег на продукты — без списания') }}</label></fieldset>
        @else
            <input type="hidden" name="reason" value="ordinary">
        @endif
        @if($partnerRequest->is_very_wanted)<p>{{ __('За обычный отказ спишется :amount сердечек.', ['amount'=>$partnerRequest->refusal_tariff]) }}</p>@endif
        <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю отказ') }}</label>
        <x-button type="submit" variant="quiet">{{ __('Отправить отказ') }}</x-button>
    </form>
</details>
</x-card>
@endif
@if($partnerRequest->type !== \App\Enums\RequestType::Rating && !$partnerRequest->moneyRequest && $partnerRequest->due_at->isFuture() && in_array($partnerRequest->status,[\App\Enums\RequestStatus::Pending,\App\Enums\RequestStatus::Conditional],true))
<details class="section-space condition-section" @if($errors->any() && old('response') === 'conditional') open @endif>
    <summary class="button button--quiet">{{ $partnerRequest->status === \App\Enums\RequestStatus::Conditional ? __('Изменить условие') : __('Согласиться с условием') }}</summary>
    <div class="card form-stack section-space">
        <h2>{{ __('Моё условие') }}</h2>
        <x-condition-form :couple="$couple" :partner-request="$partnerRequest" :methods="$methods" :wishes="$wishes" :currencies="$currencies" :condition-types="$conditionTypes" />
    </div>
</details>
@endif
@endcan
@if($conditions->total() > 1)<div class="section-space">
<details class="settings-section"><summary><span class="row__body"><span class="row__title">{{ __('История условий') }}</span></span></summary>
@foreach($conditions as $condition)<p>{{ __('Вариант :version', ['version'=>$condition->version]) }}: {{ $condition->description }}</p>
@endforeach</details></div>
@endif

@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
