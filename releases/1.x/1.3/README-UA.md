[English](README.md)

# 1.3 — JsonRpcBundle 10.4: core executor, імпорт маршрутів, перекладена документація

Теги: 1.3.0. Перший реліз: 2026-10-19.

> **Увага:** мінорний реліз з одним видаленим класом (`RestYamlFileLoader`) і зміною поведінки для лістенерів `rpc.post_response`. Прочитайте [Нотатки з оновлення](#нотатки-з-оновлення).

## Головне

- **REST-виклики проходять через core `RpcExecutor`**: помилки йдуть через `rpc.error`, як і RPC-виклики, а `rpc.post_response` спрацьовує після успішного REST-виклику, тож інвалідація `#[RPC\CacheRelation]` і post-response лістенери працюють і для REST.
- **Маршрути реєструються через `IApiRouteImport`**: REST працює поруч з іншими адаптерами (mcp-bundle) незалежно від того, який route loader переміг.
- **Типізовані значення шляху, query і форми**: рядки приводяться до `int`, `float`, `bool` або `null` за сигнатурою методу, тож `GET /items/{id}` працює з `int $id`.
- **Перекладена документація**: OpenAPI і Postman беруть переклади RPC-методів за `Accept-Language`.
- **Виправлено мапінг помилок**: `404` для невідомого RPC-методу, `422` для помилок обмежень, HTTP-коди прикладних помилок зберігаються, `RestApplicationException` знову завантажується.

## Що нового

### `rpc.post_response` для REST

REST-контролер виконує запит через `Ufo\JsonRpcBundle\Server\RpcExecutor`. Успішний виклик ставиться в чергу, і `rpc.post_response` спрацьовує на `kernel.terminate`, один раз на виклик; відповідь із помилкою його не отримує. Carrier `RpcFromRest` реалізує `IRpcHttpCarrier`, тож прив'язаний до свого HTTP-запиту.

### Імпорт маршрутів для адаптерів

`Ufo\RestBundle\Routing\RestRouteImport` реєструє `config/router.yaml` бандла в core RPC YAML loader. Кожен імпорт отримує імена маршрутів `<group>_<name>` і значення `<group>_<name_prefix>` у default маршруту `_rest_name_prefix`. Імена маршрутів і аліаси ті самі, що в 1.1–1.2.

### Приведення типів значень шляху, query і форми

Ці значення завжди рядки, і ядро RPC відхиляло їх для типізованих параметрів (`must be of type int, string given`). Тепер `RpcFromRest` приводить рядок до оголошеного скалярного типу параметра з тим самим ім'ям: цілочисловий літерал — до `int`, числовий рядок — до `float`, `true`/`1`/`false`/`0` — до `bool`, `""` — до `null` для nullable-типу. Типи, що приймають рядки (`string`, `mixed`, `int|string`), зберігають значення; значення, яке не вдалося привести, лишається як є, і про помилку повідомляє ядро. JSON-тіло не приводиться. Enum, дати, UUID і Doctrine-сутності зі рядка знову конвертує ядро RPC починаючи з JsonRpcBundle 10.4.3, тому ця версія і потрібна. Змінні шляху документуються в OpenAPI схемою свого параметра замість `string`.

```php
#[Route('/items/{id}', name: 'item', methods: ['GET'])]
public function item(int $id, bool $full = false): array {}   // GET /rest/catalog/items/42?full=true
```

### Перекладена документація

```yaml
# translations/rpc.uk.yaml
catalog.list:
    title: Каталог
    description: Список товарів
```

Перевизначення для групи кладуться в `translations/rpc/<group>.<locale>.yaml`, директорії розширень — у `ufo_json_rpc.translations.paths` (JsonRpcBundle 10.4). REST-документація вибирає локаль із `Accept-Language` через core `RpcLocaleNegotiator` і відповідає з `Content-Language` та `Vary: Accept-Language`.

В OpenAPI назва стає `summary` операції, а опис — її `description`. Для методу без назви опис, як і раніше, лишається в `summary`. Елементи Postman отримують перекладений опис.

## Зміни та виправлення

- **`RestApplicationException`** успадковував `Ufo\RpcError\RpcCustomApplicationException`, якого немає в `ufo-tech/rpc-exceptions` 2.x, тож клас не завантажувався. Тепер він успадковує `CustomApplicationException`; аргументи конструктора необов'язкові.
- **Порядок мапінгу помилок**: `RpcMethodNotFoundExceptionRpc` і `ConstraintsImposedException` — підкласи `RpcBadRequestException` і мапились у `400`; тепер — у `404` і `422`.
- **HTTP-статус прикладних помилок**: помилка з HTTP-кодом (400–511), наприклад `CustomApplicationException('Order is closed', 409)` з методу, повертає цей статус замість `400`. Перевірка використовувала `in_array()` по текстах статусів і ніколи не спрацьовувала.
- **`RestValidationException`** реалізує `RestErrorInterface` і створюється з `ConstraintsImposedException` (раніше падав з `ArgumentCountError`).
- **`PostmanAdapter`** передає `RequestStack` у core-адаптер; з JsonRpcBundle 10.3+ перевизначена властивість давала фатальну помилку.
- `Service::getName()` (застарілий з JsonRpcBundle 10.3) замінено на `$service->name`.

## Нотатки з оновлення

- Потрібен `ufo-tech/json-rpc-bundle` ^10.4.3.
- **Видалено** `Ufo\RestBundle\Routing\RestYamlFileLoader`. Застосункам, що лише імпортують `config/router.yaml`, нічого змінювати не треба. Код, що успадковував loader або посилався на нього, має зареєструвати `IApiRouteImport`.
- **Лістенери `rpc.post_response` тепер виконуються після REST-викликів** — перевірте ті, що розраховували лише на RPC.
- Відповіді прикладних помилок з HTTP-кодом змінюються з `400` на цей код; title — `RestApplicationException`.
- Значення шляху, query і форми для параметрів `int`, `float`, `bool` і nullable приводяться до типу; запити, що падали з `400` через рядковий тип, тепер виконуються.
- Конструктори (лише якщо ви створюєте їх вручну): `RestEntryPointController::rest()` приймає `RpcExecutor` замість `RpcRequestHandler`; `RequestStack` у `PostmanAdapter` більше не перевизначена властивість; `OpenApiSpecBuilder::buildOperation()` має новий необов'язковий останній аргумент `?string $description`.

## Відомі проблеми

- REST-документація не кешується з `ETag`, як RPC-документація.
- Елементи Postman для `GET`-методів містять raw JSON-тіло з параметрами методу.
- Невідоме значення enum повертає `500`: конвертер JsonRpcBundle кидає звичайний `RuntimeException` (так само в JSON-RPC).
- Помилки валідації з ядра RPC лишаються зі статусом `400` (`RpcBadParamException`); `422` застосовується лише до `ConstraintsImposedException`, що напряму доходить до REST-шару.
