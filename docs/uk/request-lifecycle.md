[English](../en/request-lifecycle.md) · [Зміст](../README-UA.md)

# Життєвий цикл запиту

## Кроки

1. **Маршрутизація** (`RouterListener`, пріоритет 32) знаходить згенерований REST-маршрут. Його defaults містять `_rest_route` (ім'я REST-маршруту), `_ver` для версій, відмінних від `v1`, `api_group` і `_rest_name_prefix`.
2. **`RestControllerListener`** (пріоритет 30) встановлює API-групу в `ApiGroupContext` і для REST-виклику спрямовує `_controller` на `RestEntryPointController::rest` з атрибутами `rpcRoute` і `ver`.
3. **`RestRequestParser::parse()`**
   - знаходить REST-маршрут і RPC `Service` для групи та версії (`404`, якщо немає);
   - перевіряє HTTP-метод (`HEAD` вважається `GET`; `405`, якщо метод не дозволено);
   - скидає пул RPC-подій, встановлює HTTP token holder і carrier, перевіряє токен (`protected_api`).
4. **`RpcFromRest`** будує `RpcRequest`: згенерований id, ім'я RPC-методу, об'єднані параметри (значення зі шляху, query і форми приведені до типів параметрів) і `apiVersion`.
5. **`RpcExecutor::execute()`** виконує запит так само, як JSON-RPC-маршрут: `rpc.request` → `rpc.pre_execute` (конвертація і валідація параметрів) → метод → `rpc.post_execute` → `rpc.pre_response`. Будь-яка помилка проходить через `rpc.error` і стає відповіддю з помилкою.
6. **Контролер** повертає `200` з результатом методу в JSON або перетворює помилку на [RFC 9457 problem](errors.md).
7. **`kernel.terminate`**: `rpc.post_response` спрацьовує для успішного виклику (зв'язки кешу, async і notification лістенери). Для помилки не спрацьовує.

## Параметри

Джерела об'єднуються в такому порядку; пізніший рядковий ключ перевизначає попередній:

1. змінні шляху маршруту;
2. query string;
3. поля форми (`application/x-www-form-urlencoded`, `multipart/form-data`);
4. JSON-тіло.

```
PUT /rest/catalog/items/42?title=query
Content-Type: application/json

{"title": "Book"}
```

дає `{"id": "42", "title": "Book"}`.

Правила:

- Порожнє тіло означає відсутність параметрів із тіла.
- З content type форми тіло не розбирається як JSON.
- Тіло, що не є коректним JSON, повертає `400 Invalid JSON request body`; JSON, що не є об'єктом чи масивом (`null`, `42`, `"text"`), повертає `400 JSON request body must be an object or array`.
- Defaults маршруту, які використовує бандл, не є параметрами: `api_group`, `ver`, `rpcRoute`, `_rest_route`, `_rest_name_prefix`, `_rest_doc`, `_ver`, `_controller`, `_format`, `_locale`, `_stateless`.

## Приведення типів

Значення зі шляху, query і форми надходять рядками. Перед об'єднанням `RpcFromRest::castString()` приводить кожне з них до типу параметра методу з тим самим ім'ям. Значення з JSON-тіла зберігають свої JSON-типи й не приводяться.

| Тип параметра | Приводиться | Не приводиться (лишається рядком) |
| --- | --- | --- |
| `string`, `mixed`, об'єднання зі `string` (`int\|string`) | ніколи: рядок валідний | |
| nullable (`?int`, `?bool`, `int\|null`) | `""` → `null` | |
| `int` | цілочисловий літерал: `42`, `-7`, `0` | `007`, `+1`, ` 1`, `1.5`, поза межами int |
| `float` | числовий рядок: `1.5`, `2`, `1e3` | `1,5` |
| `int\|float` | `2` → `2`, `2.5` → `2.5` | |
| `bool` | `true`, `1` → `true`; `false`, `0` → `false` (будь-який регістр) | `yes`, `on`, `""` |
| enum, `DateTimeInterface`, `Uuid`, Doctrine-сутність | не тут: рядок конвертує ядро RPC (JsonRpcBundle 10.4.3) | |
| `array`, інші класи | ніколи | |

Значення, яке не вдалося привести, лишається рядком, і ядро RPC відповідає `400` з `must be of type int, string given` у `detail-data`. Параметри, яких немає в сигнатурі методу, не змінюються.

Невідоме значення enum зараз повертає `500` (`Invalid value 'xml' for enum …`): конвертер ядра кидає звичайний `RuntimeException`, і в JSON-RPC так само.

## Відповідь

- Успіх: статус `200`, `Content-Type: application/json`, тіло — результат методу (без JSON-RPC-обгортки).
- Помилка: статус помилки і problem-документ, див. [Помилки](errors.md).

## Що діє з ядра RPC

Оскільки виклик проходить через core executor, ці можливості працюють для REST так само, як для JSON-RPC:

- конвертація параметрів, значення за замовчуванням, `#[RPC\Assertions]` і валідація DTO;
- кеш відповідей `#[RPC\Cache]` та інвалідація `#[RPC\CacheRelation]` (на `rpc.post_response`);
- `#[RPC\Lock]`;
- лістенери подій `rpc.*` застосунку;
- `#[Autowire]` в аргументах методу;
- логування помилок ядром.

Через REST недоступні: batch, notifications і `$rpc.callback`.
