@extends('layouts.app')
@section('title', __('Напоминания'))
@section('content')
<h1 class="screen-title">{{ __('Напоминания') }}</h1><p>{{ __('Разовые напоминания для вашей пары. Здесь видно состояние записи; запланированное ещё не означает доставленное.') }}</p><x-form-feedback />
@if($couple->status === \App\Enums\CoupleStatus::Active)<a class="button button--primary" href="{{ route('reminders.create',$couple) }}">{{ __('Напомнить партнёру') }}</a>@endif
<div class="product-grid section-space">@forelse($reminders as $reminder)<x-card class="form-stack"><x-status>{{ $reminder->status->label() }}</x-status><p>{{ __(':author → :recipient',['author'=>$reminder->author->name,'recipient'=>$reminder->recipient->name]) }}</p><p class="request-description">{{ $reminder->text }}</p><p>{{ $reminder->due_at->setTimezone($couple->timezone)->format('d.m.Y H:i') }} · {{ $couple->timezone }}</p>@if($reminder->status->canCancel()) @can('cancel',$reminder)<form method="post" action="{{ route('reminders.destroy',[$couple,$reminder]) }}" class="form-stack" data-submit-lock>@csrf @method('delete')<input type="hidden" name="expected_version" value="{{ $reminder->version }}"><label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю отмену напоминания') }}</label><x-button type="submit" variant="quiet">{{ __('Отменить') }}</x-button></form>@endcan @endif</x-card>@empty<x-card><p>{{ __('Напоминаний пока нет.') }}</p></x-card>@endforelse</div>{{ $reminders->links() }}<a class="button button--quiet section-space" href="{{ route('couples.show',$couple) }}">{{ __('К нашей паре') }}</a>
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
