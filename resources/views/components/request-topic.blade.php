@props(['couple', 'selected' => 'service'])
<fieldset><legend>{{ __('О чём просьба') }}</legend>
<div class="confirmation-actions" data-request-topic>
@foreach(\App\Enums\RequestType::cases() as $type)
<label class="choice-chip choice-chip--radio"><input type="radio" name="{{ in_array($selected, ['money','guests','event'], true) ? 'request_topic' : 'type' }}" value="{{ $type->value }}" data-url="{{ match($type) { \App\Enums\RequestType::Money => route('money.create',$couple), \App\Enums\RequestType::Guests => route('guests.create',$couple), \App\Enums\RequestType::Event => route('dates.create',$couple), default => route('requests.create',[$couple,'type'=>$type->value]) } }}" @checked(old('type',$selected) === $type->value)><x-icon :name="$type->icon()" />{{ __($type->label()) }}</label>
@endforeach
</div></fieldset>
<noscript><p>{{ __('Виды просьб') }}:</p><div class="confirmation-actions">@foreach(['money.create'=>__('Деньги'),'guests.create'=>__('Гости'),'dates.create'=>__('Свидание'),'requests.create'=>__('Другие виды')] as $route=>$label)<a href="{{ route($route,$couple) }}">{{ $label }}</a>@endforeach</div></noscript>
