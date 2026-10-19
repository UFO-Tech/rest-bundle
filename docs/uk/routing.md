[English](../en/routing.md) · [Зміст](../README-UA.md)

# Маршрутизація

## Від `#[Route]` до REST-маршруту

`RestServiceMap` читає кожен RPC-метод поточної API-групи та версії. Метод з атрибутом Symfony `Symfony\Component\Routing\Attribute\Route` стає REST-маршрутом; атрибут класу процедури (необов'язковий) додає спільний шлях та ім'я.

| Частина | Правило |
| --- | --- |
| Шлях | шлях класу + `/` + шлях методу; порожній шлях методу дає шлях класу, обидва порожні — `/` |
| Ім'я | `ufo_rest_api_` + ім'я класу + `_` + ім'я методу |
| Ім'я класу | `name` з атрибута класу; без нього — аліас RPC-процедури в snake_case (`shopItems` → `shop_items`) |
| Ім'я методу | `name` з атрибута методу; без нього — ім'я PHP-методу в snake_case (`defaultPost` → `default_post`) |
| HTTP-методи | `methods` з атрибута методу; без них — `POST` |
| Requirements, defaults, host тощо | не використовуються: читаються лише `path`, `name` і `methods` |

Атрибут у RPC service map не змінюється: бандл працює з копією.

```php
#[RPC\Info('catalog')]
#[Route('/catalog', name: 'catalog')]
class CatalogApi implements IRpcService
{
    #[Route('/items/{id}', name: 'item', methods: ['GET'])]   // GET  /catalog/items/{id}  ufo_rest_api_catalog_item
    public function item(string $id): array {}

    #[Route('/items')]                                         // POST /catalog/items       ufo_rest_api_catalog_create_item
    public function createItem(string $title): array {}
}
```

Змінні шляху (`{id}`) передаються в метод як параметри з тим самим ім'ям.

## Згенеровані маршрути Symfony

Для кожного REST-маршруту й версії API loader додає два маршрути Symfony:

| Маршрут | Шлях | Методи | Призначення |
| --- | --- | --- | --- |
| `<name>_<version>` | `<path>` або `/<version><path>` для версій, відмінних від `v1` | з атрибута | виклик |
| `<name>_<version>_method_not_allowed` | той самий | будь-які | пріоритет `-10`: неправильний HTTP-метод отримує `405` замість `404` |

`config/router.yaml` бандла також містить маршрути контролера:

| Маршрут | Шлях | Призначення |
| --- | --- | --- |
| `ufo_rest_doc` | `` (префікс імпорту) | OpenAPI, `GET` |
| `ufo_rest_doc_ver` | `/{ver}` (`v\d+`) | OpenAPI версії |
| `ufo_rest_doc_postman` | `/postman` | Postman |
| `ufo_rest_doc_postman_ver` | `/{ver}/postman` | Postman версії |
| `ufo_rest_api_entrypoint` | `/{path}` | загальний вхід, пріоритет `-100`: невідомий шлях повертає `404` problem |
| `ufo_rest_api_entrypoint_ver` | `/{ver}/{path}` | загальний вхід для версій, пріоритет `-90` |

`HEAD` приймається для `GET`-маршрутів.

## Версії

Версії API беруться з `RPC\Info(version: N, removedMethods: [...])` JsonRpcBundle. Клас процедури версії 2 успадковує методи версії 1, які він не перевизначає і не видаляє.

| Версія | Виклик | Документи | Ім'я маршруту |
| --- | --- | --- | --- |
| `v1` (типова) | `/rest/catalog/items/1` | `/rest`, `/rest/postman` | `..._v1` |
| `v2` | `/rest/v2/catalog/items/1` | `/rest/v2`, `/rest/v2/postman` | `..._v2` |

Невідома версія повертає `404`.

## API-групи та кілька входів

Імпортуйте `config/router.yaml` окремо для кожного входу. `defaults.api_group` вибирає RPC API-групу; без нього група — `general`. Префікс URL не залежить від групи.

```yaml
rest:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /rest
    trailing_slash_on_root: false

rest_internal:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /internal
    defaults: { api_group: internal }
    trailing_slash_on_root: false

rest_partners:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /partners
    name_prefix: partners_
    defaults: { api_group: internal }
    trailing_slash_on_root: false
```

- Методи розподіляються між групами атрибутом `#[RPC\APIGroups]` на класі або методі (атрибут методу замінює атрибут класу; `withDefault: true` додає `general`). Щоб метод з'явився в REST, йому все одно потрібен `#[Route]`.
- Кожен маршрут отримує префікс групи: `internal_ufo_rest_doc`, `internal_ufo_rest_api_catalog_item_v1`. Явний `name_prefix` іде після нього: `internal_partners_ufo_rest_doc`.
- Маршрути `general` також зберігають старі імена як аліаси (`ufo_rest_doc` → `general_ufo_rest_doc`).
- Кожен маршрут має defaults `api_group` і `_rest_name_prefix` (`<group>_<name_prefix>`); документи використовують їх, щоб генерувати власні URL.
- Запит не може змінити групу через поле query чи тіла: група — це default маршруту.

## Поруч з RPC та іншими адаптерами

`RestRouteImport` реалізує `Ufo\JsonRpcBundle\Routing\IApiRouteImport`. Core RPC YAML loader розпізнає імпорт `config/router.yaml` і застосовує описану вище обробку груп; інші адаптери (наприклад, mcp-bundle) реєструють свої файли маршрутів так само. RPC-, REST- і адаптерні маршрути можна імпортувати для тих самих груп в одному файлі маршрутизації без конфліктів імен.

## Діагностика

- **Методу немає в REST**: перевірте `#[Route]` на методі, API-групу імпорту, `#[RPC\IgnoreApi]` і версію (`removedMethods`).
- **`405` для наявного методу**: HTTP-методу немає в `methods` атрибута (типово — `POST`).
- **`debug:router`** показує згенеровані імена: `bin/console debug:router | grep ufo_rest`.
