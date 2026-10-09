[К README](../README.md) · [Интерфейс →](interface.md)

# Разработка и проверки

## Проверка изменений

PHP 8.4 и зависимости из `composer.lock` обязательны. Node.js 22+ используется для сборки.

```sh
composer install
npm ci
composer check
npm run build
```

`composer check` запускает Pint в режиме проверки, PHPStan/Larastan уровня 8 и Pest. Правила находятся в `pint.json`, `phpstan.neon` и [CodeStyle](../CodeStyle.md). Обычные PHP-файлы требуют strict_types; Blade и автоматически созданный кеш исключены. Pint использует `no_useless_else`; предпочтение ранних выходов дополнительно проверяется при саморевью. Для исправления форматирования: `vendor/bin/pint`.

GitHub Actions запускает те же проверки и сборку без деплоя. Перед push проверяется весь отправляемый диапазон; ошибка любой обязательной проверки запрещает отправку.

## Изолированное тестирование

`phpunit.xml` принудительно задаёт testing, SQLite `:memory:`, тестовый ключ шифрования, сессии/кеш в памяти и синхронную очередь. Этот ключ служит только тестам. Кеш конфигурации, постоянная база или URL подключения приводят к отказу до подготовки данных. В тестовом приложении остаётся только SQLite-подключение; неожиданные HTTP-запросы блокируются.

```sh
php artisan test --compact
php artisan test --compact --filter=TestEnvironment
```

Сценарии используют Pest `it()`. Тесты изоляции проверяют отказ для постоянной базы, нетестового окружения и URL подключения. Сценарии интерфейса проверяют русский язык, экранирование личных данных и доступные поля. Рабочая MariaDB не используется. Для пар и приглашений добавлена отдельная MariaDB-среда: SQLite не подтверждает корректность блокировок и гонок.

## Конкурентные операции в MariaDB

Используйте отдельный одноразовый контейнер, без рабочих томов:

```sh
docker run --rm -d --name homeassist-couples-test --tmpfs /var/lib/mysql:rw,size=512m -p 127.0.0.1:33071:3306 -e MARIADB_DATABASE=homeassist_couples_testing -e MARIADB_USER=homeassist_test -e MARIADB_PASSWORD=test_only_password -e MARIADB_RANDOM_ROOT_PASSWORD=yes mariadb:11.4
docker exec homeassist-couples-test healthcheck.sh --connect --innodb_initialized
vendor/bin/pest --configuration=phpunit.mariadb.xml
docker stop homeassist-couples-test
```

Ключ и пароль здесь предназначены только для одноразовой тестовой базы. Проверка окружения до миграций требует testing, отсутствия кеша конфигурации и DB_URL, хост 127.0.0.1, порт 33071, имя homeassist_couples_testing и пользователя homeassist_test. Другие подключения удаляются из тестового приложения. Эти тесты пересоздают только выделенную тестовую схему; не подключайте к этому контейнеру рабочие данные. PHP-процессы синхронизируются стартовым барьером; адаптация вывода Pao отключена только в дочернем протоколе, проверки остаются включены.

## Laravel Boost

Boost установлен как dev-зависимость; инструкции обновляются командой `APP_ENV=local php artisan boost:install --guidelines --no-interaction`. После обновления перечитайте AGENTS.md и CodeStyle.md. CodeStyle имеет приоритет над общими указаниями Boost, включая обязательный Pint --test. Пользовательские правила Git и проверки перед push сохранены.

Для денежных вычислений явно подключён `brick/math`; денежные операции пока не реализованы. Логи регулируются `LOG_LEVEL`, содержат технический этап и причину отказа без личного текста и секретов.

## Смотрите также

- [Интерфейс](interface.md)
- [CodeStyle](../CodeStyle.md)
- [Запуск проекта](../README.md)
