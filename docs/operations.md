# Использование с ноутбука

[К README](../README.md) · [Проверки](development.md) · [Telegram](telegram-and-scheduling.md)

Приложение можно использовать через браузер телефона в той же доверенной локальной сети. Ноутбук должен быть включён, Docker Desktop — запущен. Сон ноутбука останавливает доступ и обработку расписания; после пробуждения обрабатываются ещё действующие события, без пачки устаревших вопросов.

## Адрес и запуск

В локальном `.env` задайте `APP_BIND_ADDRESS=0.0.0.0`, свободный `APP_PORT` и `APP_URL=http://IP-НОУТБУКА:ПОРТ`. Для обычного использования задайте `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=info`, `SESSION_DOMAIN=null` и `SESSION_SECURE_COOKIE=false` при HTTP. Значение IP можно узнать в настройках Wi-Fi; на macOS — `ipconfig getifaddr en0`. При изменении адреса обновите `APP_URL` и перезапустите процессы. Закрепление адреса ноутбука в настройках роутера избавляет от смены ссылок.

Порт базы и порт разработки интерфейса остаются доступны только на ноутбуке. Не включайте переадресацию порта приложения в интернете. HTTP передаёт вход и личные данные без шифрования: такой вариант рассчитан на доверенную домашнюю сеть. Для доступа вне неё нужен отдельно настроенный HTTPS.

После первой установки и миграций из README:

Команды ниже включают получатель Telegram для `TELEGRAM_MODE=polling`. Если бот не используется, уберите `--profile telegram-polling` из команд `up`; очередь и расписание можно оставить работающими с отключённым транспортом.

```sh
npm run build
docker compose -f compose.yaml -f compose.workers.yaml --profile telegram-polling up -d
docker compose -f compose.yaml -f compose.workers.yaml ps
```

Откройте `APP_URL` с телефона, зарегистрируйте собственный аккаунт и сохраните резервные коды из профиля. Второй участник регистрирует свой аккаунт и принимает приглашение. Демонстрационные аккаунты при установке не нужны; используйте только `EconomySettingsSeeder` и `CatalogSeeder`, как в README.

Расписание работает отдельным процессом `scheduler`; очередь — `telegram-worker`. Оба перезапускаются вместе с Docker. Проверка настроенного расписания и журнала сердечек:

```sh
docker compose exec -T laravel.test php artisan migrate:status --no-interaction
docker compose exec -T laravel.test php artisan schedule:list --no-interaction
docker compose exec -T laravel.test php artisan hearts:check-balances --no-interaction
```

Не запускайте второй scheduler. Для остановки с сохранением базы:

```sh
docker compose -f compose.yaml -f compose.workers.yaml --profile telegram-polling stop
```

## Telegram и HTTPS

Через локальный IP и HTTP доступны страницы и обычные формы приложения. Установка PWA с service worker и автономный экран требуют доверенного HTTPS; localhost на самом ноутбуке является отдельным исключением. Проверка в настольном браузере не подтверждает установку в Safari iOS или Chrome Android.

Для Telegram в локальной сети используйте `TELEGRAM_ENABLED=true`, `TELEGRAM_MODE=polling`, действительный токен и имя бота. Получение сообщений включается отдельным процессом `telegram-poller`; порядок запуска описан в [инструкции Telegram](telegram-and-scheduling.md). Публичный HTTPS для этого режима не нужен. Привязка: `/start` в боте → UUID → поле кода в профиле. Уведомления требуют работающей очереди, расписания и интернета на ноутбуке. В режиме `webhook` серверы Telegram должны иметь доступ к публичному HTTPS-адресу; не включайте одновременно webhook и polling.

## Обновление и резервная копия

Перед обновлением сохраните согласованный комплект: дамп MariaDB, `.env` с неизменным `APP_KEY`, весь каталог `storage/app/private` и версию кода. Ключ нужен для расшифровки сохранённых данных; его смена не является обычным обновлением. Копии содержат личные данные: храните их вне Git и переносите на отдельный защищённый носитель. Для согласованной копии сначала остановите приложение, очередь и расписание; база остаётся запущенной.

```sh
docker compose -f compose.yaml -f compose.workers.yaml stop laravel.test telegram-worker scheduler telegram-poller
umask 077
mkdir -p work/backups
docker compose exec -T mariadb sh -c 'exec mariadb-dump --single-transaction --user="$MYSQL_USER" --password="$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > work/backups/homeassist.sql
cp .env work/backups/environment
tar -czf work/backups/private-media.tar.gz storage/app/private
git rev-parse HEAD > work/backups/code-version.txt
```

Проверяйте успешное завершение каждой команды и ненулевой размер дампа. Эти имена перезаписывают прежнюю копию: переносите готовый комплект в отдельную датированную папку до следующего резервирования. Проверяйте восстановление на отдельной одноразовой базе, не поверх единственной рабочей копии.

Обновите код и зависимости по зафиксированным файлам, соберите интерфейс, выполните `migrate --force` в подтверждённом окружении и запустите процессы:

```sh
composer install --no-interaction
npm ci
npm run build
docker compose run --rm --no-deps laravel.test php artisan migrate --force --no-interaction
docker compose -f compose.yaml -f compose.workers.yaml --profile telegram-polling up -d
```

Не используйте `migrate:fresh`, `db:wipe` или `down -v` на рабочем окружении. Откат версии кода может требовать прежней схемы и сохранённого комплекта: восстановление сначала проверяется отдельно, затем выполняется с остановленными обработчиками. Новые миграции не гарантируют восстановление утраченных данных при rollback.

### Проверка восстановления

Подготовьте отдельную копию проекта с версией кода из `code-version.txt`. Скопируйте сохранённый `environment` в её `.env`, сохраните прежний `APP_KEY`, но задайте новое `COMPOSE_PROJECT_NAME`, свободные порты и `APP_BIND_ADDRESS=127.0.0.1`. Бот оставьте отключённым. Новое имя проекта должно создавать отдельный пустой том; прежде чем импортировать, проверьте имя контейнера и его том. Не направляйте импорт на существующую рабочую базу.

Запустите только MariaDB этой копии и дождитесь `healthy`. Импортируйте сохранённый дамп в её пустую базу, распакуйте приватные файлы из сохранённого архива в корень копии проекта, затем запускайте только приложение:

```sh
docker compose up -d mariadb
docker compose ps
docker compose exec -T mariadb sh -c 'exec mariadb --user="$MYSQL_USER" --password="$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < work/backups/homeassist.sql
tar -xzf work/backups/private-media.tar.gz
docker compose up -d laravel.test
docker compose exec -T laravel.test php artisan migrate:status --no-interaction
docker compose exec -T laravel.test php artisan hearts:check-balances --no-interaction
```

Проверьте вход сохранённого пользователя, историю и выдачу его фотографий. Сопоставьте количество записей и версии правил с исходной копией. Очередь и расписание включайте только после решения использовать восстановленное окружение: проверочная копия не должна отправлять сообщения или изменять сроки рабочей пары. Восстановление начальной пустой установки проверено в одноразовой MariaDB; это не заменяет периодическую проверку копии с вашими будущими данными.

## Диагностика

При недоступности проверьте питание/сон ноутбука, Docker, одинаковую сеть устройств, текущий IP, порт и разрешение входящих соединений в системе. Затем проверьте `docker compose ... --profile telegram-polling ps`, где `...` означает оба файла выше. Если страницы работают, а сроки не обрабатываются, проверьте `scheduler` и `schedule:list`. При проблемах доставки следуйте [инструкции Telegram](telegram-and-scheduling.md).

`DEBUG` предназначен для локальной разработки; `INFO` — для этапов обработки, `WARN` — для отказов и повторов, `ERROR` — для неожиданных ошибок. Рабочий журнал — `storage/logs/laravel.log`. При разборе используйте коды `schedule.*`, `notification.*`, `telegram.transport_failed` и `failed_jobs`; не публикуйте `.env`, резервные коды, URL с токеном, реквизиты, содержимое сообщений или целые личные страницы. Сверка сердечек сообщает расхождения и не исправляет их молча.
