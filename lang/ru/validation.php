<?php

declare(strict_types=1);

return [
    'integer' => 'Поле «:attribute» должно быть целым числом.',
    'boolean' => 'Выберите допустимый ответ в поле «:attribute».',
    'accepted' => 'Подтвердите поле «:attribute».',
    'date' => 'Укажите корректную дату в поле «:attribute».',
    'date_format' => 'Укажите дату и время в поле «:attribute».',
    'after' => 'Укажите будущий срок в поле «:attribute».',
    'uuid' => 'Обновите страницу перед повторной отправкой.',
    'in' => 'Выберите допустимое значение поля «:attribute».',
    'between' => ['numeric' => 'Поле «:attribute» должно быть от :min до :max.'],
    'required' => 'Заполните поле «:attribute».',
    'string' => 'Поле «:attribute» должно содержать текст.',
    'confirmed' => 'Подтверждение поля «:attribute» не совпадает.',
    'unique' => 'Этот :attribute уже занят.',
    'enum' => 'Выберите допустимое значение поля «:attribute».',
    'regex' => 'Поле «:attribute» содержит недопустимые символы.',
    'min' => ['numeric' => 'Поле «:attribute» должно быть не менее :min.', 'string' => 'Поле «:attribute» должно содержать не менее :min символов.'],
    'max' => ['string' => 'Поле «:attribute» должно содержать не более :max символов.'],
    'size' => ['string' => 'Поле «:attribute» должно содержать :size символа.'],
    'password' => [
        'letters' => 'Пароль должен содержать буквы.',
        'numbers' => 'Пароль должен содержать цифры.',
    ],
    'attributes' => [
        'due_at' => 'срок', 'description' => 'описание', 'confirmation' => 'подтверждение', 'expected_version' => 'версия', 'amount' => 'количество сердечек', 'reason' => 'причина', 'answer' => 'ответ', 'kind' => 'действие', 'weekly_limit' => 'недельный лимит', 'condition_type' => 'вид условия', 'type' => 'вид просьбы', 'operation_key' => 'ключ отправки',
        'name' => 'имя',
        'login' => 'логин',
        'password' => 'пароль',
        'password_confirmation' => 'повтор пароля',
        'gender' => 'обращение',
        'recovery_code' => 'резервный код',
        'payment_details' => 'реквизиты',
    ],
];
