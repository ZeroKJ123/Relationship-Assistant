@props(['couple'])
@if($couple->status === \App\Enums\CoupleStatus::Active)
@can('update',$couple)
<form method="post" action="{{ route('mood.prompts.store',$couple) }}" data-submit-lock>
    @csrf
    <x-button type="submit" variant="quiet">{{ __('Узнать настроение партнёра') }}</x-button>
</form>
@endcan
@endif
