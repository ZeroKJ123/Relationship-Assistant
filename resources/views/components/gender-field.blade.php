@props(['value' => 'unspecified', 'error' => null])
<div class="field">
    <label for="gender">{{ __('Как к вам обращаться') }}</label>
    <select id="gender" name="gender" @if($error) aria-invalid="true" aria-describedby="gender-error" @endif>
        <option value="unspecified" @selected($value === 'unspecified')>{{ __('Нейтрально') }}</option>
        <option value="female" @selected($value === 'female')>{{ __('В женском роде') }}</option>
        <option value="male" @selected($value === 'male')>{{ __('В мужском роде') }}</option>
    </select>
    @if($error)<p id="gender-error" class="field-error">{{ $error }}</p>@endif
</div>
