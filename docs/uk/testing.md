[English](../en/testing.md) · [Зміст](../README-UA.md)

# Тестування

## Запуск

```bash
composer install
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpunit -c phpunit.xml.dist --filter ErrorResponseTest
```

У Docker (`make up-d`, контейнер `php_<PROJECT_NAME>`):

```bash
docker exec php_rest vendor/bin/phpunit -c phpunit.xml.dist
make test
```

Покриття (у контейнері встановлено Xdebug):

```bash
docker exec -e XDEBUG_MODE=coverage php_rest vendor/bin/phpunit -c phpunit.xml.dist --coverage-filter src --coverage-text
```

`cebe/php-openapi` видає п'ять deprecation-попереджень PHP 8.4 (неявно nullable параметри); це очікувано.

## Структура

| Директорія | Тип | Що перевіряє |
| --- | --- | --- |
| `tests/Unit` | unit, без kernel | правила маршрутів `RestServiceMap`, `RpcRestRouteLoader`, параметри, приведення типів і помилки тіла `RpcFromRest`, `RestErrorNormalizer` і REST-винятки, `RestControllerListener`, `RestDocLocaleListener`, `RestRouteImport`, білдери OpenAPI і Postman, `RestBundleExtension`, `Package` |
| `tests/Integration` | сервіси контейнера | `RestRequestParser` (групи, версії, HTTP-методи, токени), пріоритети лістенерів |
| `tests/Functional` | HTTP через kernel | маршрути й аліаси кількох імпортів, виконання, версії, параметри й приведення типів, помилки (RFC 9457), документи й переклади, безпека, `rpc.post_response`, співіснування з іншим адаптером `IApiRouteImport` |
| `tests/Fixtures` | | RPC-процедури, файл маршрутів, переклади, другий адаптер, записувач `rpc.post_response` |

`tests/TestKernel.php` завантажує FrameworkBundle, SecurityBundle, JsonRpcBundle і цей бандл. Його кеш лежить у `<tmp>/ufo-rest-tests/<хеш конфігурації безпеки>`; тест задає `TestKernel::$security` перед `bootKernel()`, щоб отримати інший контейнер.

## Примітки

- Якщо після зміни fixtures у межах тієї самої секунди тест бачить старий контейнер, видаліть `<tmp>/ufo-rest-tests` (`docker exec php_rest rm -rf /tmp/ufo-rest-tests`).
- Функціональні тести успадковують `Ufo\RestBundleTest\Functional\FunctionalTestCase` (`send()`, `json()`), який після кожного тесту відновлює конфігурацію безпеки й обробник винятків.
- Додавайте функціональний тест на будь-яку зміну маршрутів, статусів, problem-документів чи документації: це REST-контракт.
