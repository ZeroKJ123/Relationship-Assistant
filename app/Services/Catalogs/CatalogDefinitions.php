<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Enums\RequestType;

final class CatalogDefinitions
{
    /** @return list<string> */
    public function expenses(): array
    {
        return ['Продукты и приготовление еды', 'Кафе и готовая еда', 'Дом и быт', 'Личные покупки', 'Свидания и развлечения', 'Транспорт и поездки', 'Подарки и желания', 'Другое / без назначения'];
    }

    /** @return list<string> */
    public function requests(): array
    {
        return ['Кухня', 'Уборка и порядок', 'Вещи и домашние дела', 'Покупки и поручения', 'Забота и внимание', 'Совместное время', 'Деньги и подарки', 'Другое'];
    }

    /** @return list<array{code:string,category:int,name:string,type:RequestType}> */
    public function templates(): array
    {
        return [
            ['code' => 'cook-dish', 'category' => 1, 'name' => 'Приготовить выбранное блюдо', 'type' => RequestType::Food],
            ['code' => 'cook-breakfast', 'category' => 1, 'name' => 'Приготовить завтрак', 'type' => RequestType::Food],
            ['code' => 'cook-lunch', 'category' => 1, 'name' => 'Приготовить обед', 'type' => RequestType::Food],
            ['code' => 'cook-dinner', 'category' => 1, 'name' => 'Приготовить ужин', 'type' => RequestType::Food],
            ['code' => 'wash-dishes', 'category' => 1, 'name' => 'Помыть посуду', 'type' => RequestType::Service],
            ['code' => 'clean-kitchen', 'category' => 1, 'name' => 'Убрать кухню', 'type' => RequestType::Service],
            ['code' => 'vacuum', 'category' => 2, 'name' => 'Пропылесосить', 'type' => RequestType::Service],
            ['code' => 'wash-floor', 'category' => 2, 'name' => 'Помыть пол', 'type' => RequestType::Service],
            ['code' => 'take-trash', 'category' => 2, 'name' => 'Вынести мусор', 'type' => RequestType::Service],
            ['code' => 'clean-bathroom', 'category' => 2, 'name' => 'Убрать ванную', 'type' => RequestType::Service],
            ['code' => 'laundry', 'category' => 3, 'name' => 'Постирать вещи', 'type' => RequestType::Service],
            ['code' => 'hang-laundry', 'category' => 3, 'name' => 'Развесить бельё', 'type' => RequestType::Service],
            ['code' => 'fold-clothes', 'category' => 3, 'name' => 'Сложить вещи', 'type' => RequestType::Service],
            ['code' => 'change-bedding', 'category' => 3, 'name' => 'Сменить постельное бельё', 'type' => RequestType::Service],
            ['code' => 'buy-groceries', 'category' => 4, 'name' => 'Купить продукты по списку', 'type' => RequestType::Service],
            ['code' => 'collect-order', 'category' => 4, 'name' => 'Забрать заказ', 'type' => RequestType::Service],
            ['code' => 'household-shopping', 'category' => 4, 'name' => 'Сходить за бытовыми покупками', 'type' => RequestType::Service],
            ['code' => 'massage', 'category' => 5, 'name' => 'Сделать массаж', 'type' => RequestType::Service],
            ['code' => 'kisses', 'category' => 5, 'name' => 'Поцеловать партнёра', 'type' => RequestType::Service],
            ['code' => 'hug', 'category' => 5, 'name' => 'Обнять', 'type' => RequestType::Service],
            ['code' => 'bring-tea', 'category' => 5, 'name' => 'Принести чай', 'type' => RequestType::Service],
            ['code' => 'help-rest', 'category' => 5, 'name' => 'Помочь отдохнуть после тяжёлого дня', 'type' => RequestType::Service],
            ['code' => 'watch-tiktok', 'category' => 6, 'name' => 'Посмотреть все присланные TikTok', 'type' => RequestType::Service],
            ['code' => 'invite-guests', 'category' => 6, 'name' => 'Позвать друзей домой', 'type' => RequestType::Guests],
            ['code' => 'car-event', 'category' => 6, 'name' => 'Пойти на просмотр автомобилей', 'type' => RequestType::Event],
            ['code' => 'walk', 'category' => 6, 'name' => 'Погулять вместе', 'type' => RequestType::Event],
            ['code' => 'watch-film', 'category' => 6, 'name' => 'Посмотреть фильм', 'type' => RequestType::Event],
            ['code' => 'date', 'category' => 6, 'name' => 'Организовать свидание', 'type' => RequestType::Event],
            ['code' => 'money-request', 'category' => 7, 'name' => 'Перевести согласованную сумму', 'type' => RequestType::Money],
            ['code' => 'wishlist-gift', 'category' => 7, 'name' => 'Купить подарок из виш-листа', 'type' => RequestType::Gift],
            ['code' => 'custom-request', 'category' => 8, 'name' => 'Своя просьба', 'type' => RequestType::Service],
        ];
    }

    /** @return list<array{code:string,name:string,details:string,options:list<string>}> */
    public function methods(): array
    {
        return [
            ['code' => 'money', 'name' => 'Деньги', 'details' => 'Сумма и валюта; перевод вне приложения.', 'options' => []],
            ['code' => 'kisses', 'name' => 'Поцелуи', 'details' => 'Количество и срок; реальные действия без кошелька.', 'options' => []],
            ['code' => 'hearts', 'name' => 'Сердечки', 'details' => 'Согласованная цена услуги резервируется и списывается после проверки.', 'options' => []],
            ['code' => 'action', 'name' => 'Действие', 'details' => 'Описание действия и обязательный срок.', 'options' => []],
            ['code' => 'wishlist', 'name' => 'Подарок из виш-листа', 'details' => 'Выбранное желание; покупка вне приложения.', 'options' => []],
            ['code' => 'sushi', 'name' => 'Заказать суши', 'details' => 'Выберите размер и срок; заказ выполняется вне приложения.', 'options' => ['small', 'medium', 'large']],
            ['code' => 'custom', 'name' => 'Свой способ', 'details' => 'Название и детали, сохранённые владельцем.', 'options' => []],
        ];
    }
}
