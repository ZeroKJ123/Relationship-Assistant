@props(['value' => null, 'minimum', 'maximum', 'error' => null, 'id'])
<x-field :id="$id" name="weekly_limit" type="number" :label="__('Сколько раз в неделю?')" :value="$value" :error="$error" :min="$minimum" :max="$maximum" step="1" required inputmode="numeric" />
