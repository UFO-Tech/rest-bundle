[English](../en/architecture.md) · [Зміст](../README-UA.md)

# Принципи й архітектура

## Принципи

1. **RPC-метод — єдина реалізація.** REST — ще один транспорт для того самого методу. Немає REST-контролера на кожен метод, другого набору DTO чи окремої валідації.
2. **Публікація явна.** Метод з'являється в REST лише з атрибутом Symfony `#[Route]`. Решта лишається тільки в RPC. `#[RPC\IgnoreApi]` і `#[RPC\APIGroups]` працюють так само, як у RPC.
3. **Виконання належить ядру RPC.** Бандл перетворює HTTP-запит на `RpcRequest` і виконує його через core `RpcExecutor`. Конвертація параметрів, `#[RPC\Assertions]`, `#[RPC\Cache]`, блокування і події `rpc.*` поводяться точно як для JSON-RPC-виклику.
4. **Групи й версії беруться з RPC service map.** Кожна API-група і версія API мають власні REST-маршрути та документи; успадковані й видалені методи визначає `RPC\Info(version:, removedMethods:)`.
5. **HTTP-семантика на межі.** REST-шар відповідає лише за те, чого бракує JSON-RPC: шляхи, HTTP-методи, статус-коди (`404`, `405`, `401`, …) і формат помилок RFC 9457.
6. **Документація з тих самих метаданих.** OpenAPI і Postman генеруються з service map (типи, PHPDoc, `@throws`, переклади), тож не можуть розійтися з кодом.

## Компоненти

| Компонент | Файл | Роль |
| --- | --- | --- |
| `RestServiceMap` | `src/Services/RestServiceMap.php` | Читає RPC service map поточної API-групи і будує REST-маршрути з атрибутів `#[Route]` (клас + метод) для кожної версії |
| `RpcRestRouteLoader` | `src/Services/RpcRestRouteLoader.php` | Route loader Symfony типу `ufo_rest_api`: маршрут на кожен метод і версію плюс запасний `_method_not_allowed` |
| `RestRouteImport` | `src/Routing/RestRouteImport.php` | Реєструє `config/router.yaml` у core RPC YAML loader, який обробляє API-групи та імена маршрутів |
| `RestControllerListener` | `src/EventDispatcher/RestControllerListener.php` | Після маршрутизації: встановлює API-групу і спрямовує REST-маршрути в контролер входу |
| `RestDocLocaleListener` | `src/EventDispatcher/RestDocLocaleListener.php` | Мова документації з `Accept-Language` |
| `RestRequestParser` | `src/Services/RestRequestParser.php` | Знаходить REST-маршрут і RPC-сервіс, перевіряє HTTP-метод і токен, створює carrier |
| `RpcFromRest` | `src/Services/RpcFromRest.php` | Carrier (`IRpcHttpCarrier`): будує `RpcRequest` зі змінних маршруту, query, форми та JSON-тіла; приводить рядкові значення до типів параметрів |
| `RestEntryPointController` | `src/Controller/RestEntryPointController.php` | Виконує виклик через `RpcExecutor`, повертає результат або problem; віддає OpenAPI і Postman |
| `RestErrorNormalizer` і `Rest*Exception` | `src/Exceptions/` | Мапінг RPC-помилок на REST-винятки з HTTP-статусами |
| `OpenApiAdapter`, `PostmanAdapter` | `src/DocAdapters/Outputs/` | Будують документи з `RestServiceMap` |

## Дві фази

**Компіляція контейнера й маршрутів**

```
RPC-процедури ──► JsonRpcBundle ServiceMap (для кожної групи й версії)
                        │
config/routes/*.yaml ──► core RPC YAML loader ── RestRouteImport розпізнає config/router.yaml
                        │   встановлює API-групу імпорту, додає префікс до імен: <group>_<name>
                        ▼
              config/router.yaml
               ├─ маршрути контролера: документація (/, /postman, /{ver}, /{ver}/postman), загальний вхід
               └─ ресурс ufo_rest_api ──► RpcRestRouteLoader ──► RestServiceMap ──► маршрути з #[Route]
```

**Запит**

```
HTTP-запит ─► RouterListener (32) ─► RestControllerListener (30): група, контролер, rpcRoute, ver
           ─► RestDocLocaleListener (8, лише документація)
           ─► RestEntryPointController::rest
                ├─ RestRequestParser: маршрут → сервіс, HTTP-метод, токен, carrier RpcFromRest
                ├─ RpcExecutor: rpc.request → rpc.pre_execute → метод → rpc.post_execute → rpc.pre_response
                │               (помилки: rpc.error)
                └─ 200 + JSON-результат | RFC 9457 problem
           ─► kernel.terminate: rpc.post_response для успішного виклику
```

Подробиці: [Маршрутизація](routing.md), [Життєвий цикл запиту](request-lifecycle.md).

## Чого бандл не робить

- Не має власного дерева конфігурації: усе налаштовується в `ufo_json_rpc` (безпека, документація, переклади, кеш).
- Не підтримує через REST JSON-RPC batch, notifications і `$rpc.callback`: один HTTP-запит — один виклик методу.
- Не кешує документи з `ETag` (RPC-документація кешує).
