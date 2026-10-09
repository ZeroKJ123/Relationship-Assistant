<?php

declare(strict_types=1);

return [
    'required' => 'Заполните поле «:attribute».',
    'string' => 'Поле «:attribute» должно содержать текст.',
    'confirmed' => 'Подтверждение поля «:attribute» не совпадает.',
    'unique' => 'Этот :attribute уже занят.',
    'enum' => 'Выберите допустимое значение поля «:attribute».',
    'regex' => 'Поле «:attribute» содержит недопустимые символы.',
    'min' => ['string' => 'Поле «:attribute» должно содержать не менее :min символов.'],
    'max' => ['string' => 'Поле «:attribute» должно содержать не более :max символов.'],
    'size' => ['string' => 'Поле «:attribute» должно содержать :size символа.'],
    'password' => [
        'letters' => 'Пароль должен содержать буквы.',
        'numbers' => 'Пароль должен содержать цифры.',
    ],
    'attributes' => [
        'name' => 'имя',
        'login' => 'логин',
        'password' => 'пароль',
        'password_confirmation' => 'повтор пароля',
        'gender' => 'обращение',
        'recovery_code' => 'резервный код',
        'payment_details' => 'реквизиты',
    ],
];
