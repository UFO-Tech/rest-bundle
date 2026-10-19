[English](../en/documentation.md) · [Зміст](../README-UA.md)

# Документація API

Кожен REST-вхід публікує два документи для кожної версії API. Вони містять лише методи його API-групи та версії і використовують URL цього входу.

| URL | Документ |
| --- | --- |
| `GET <prefix>` | OpenAPI 3.1 |
| `GET <prefix>/postman` | Postman-колекція v2.1 |
| `GET <prefix>/v2`, `GET <prefix>/v2/postman` | те саме для версії `v2` |

## OpenAPI

| Елемент | Джерело |
| --- | --- |
| `info.title`, `info.description` | `ufo_json_rpc.docs.project_name`, `project_description` |
| `info.version` | запитана версія API |
| `info.x-versions` | усі версії групи |
| `servers[0].url` | URL документа з поточного запиту |
| `servers[0].x-ufo` | envelope (`REST (RFC 9110, 9457)/UFO-REST-<version>`), версії пакетів, посилання на протоколи |
| `paths.<path>.<method>` | одна операція на REST-маршрут |
| `operationId` | ім'я RPC-методу (`catalog.item`) |
| `summary` | перекладена назва; без назви — опис; без обох — ім'я методу |
| `description` | перекладений опис, коли є назва |
| `tags` | аліас RPC-процедури |
| `deprecated` | `@deprecated` або `#[\Deprecated]` на методі |
| `parameters` | змінні шляху (`in: path`, обов'язкові) зі схемою параметра методу |
| `requestBody` | решта параметрів методу як схема JSON-об'єкта: обов'язкові параметри, значення за замовчуванням, описи, типи, enum і посилання на DTO |
| `responses.200` | схема типу результату |
| `responses.<status>` | статуси помилок з `@throws`, див. [Помилки](errors.md) |
| `components.schemas` | DTO і enum, які використовують операції |

## Postman

- Колекція називається `REST: <назва проєкту>`, з ` [group]` для нетипової групи.
- Змінні `base_url` (хост поточного запиту) і `apiToken`.
- Елементи згруповані в папки за RPC-процедурою; елемент названо його шляхом.
- Кожен запит має HTTP-метод маршруту, URL зі змінними шляху Postman (`/items/:id`), заголовки `Content-Type` і заголовок токена, а також raw JSON-тіло з прикладами значень параметрів, крім змінних шляху.

## Переклади

Назви й описи методів беруться з RPC-перекладів JsonRpcBundle 10.4:

```
translations/rpc.<locale>.yaml          # базовий, для всіх груп
translations/rpc/<group>.<locale>.yaml  # перевизначення для однієї API-групи
```

```yaml
# translations/rpc.uk.yaml
catalog.item:
    title: Товар
    description: Повертає товар за ідентифікатором
```

Бандли-розширення додають директорії через `ufo_json_rpc.translations.paths`. Поле шукається в такому порядку: група + мова клієнта, група + типова мова, базовий + мова клієнта, базовий + типова мова, `#[RPC\Info]` / PHPDoc.

`RestDocLocaleListener` вибирає мову з `Accept-Language` серед мов, для яких є переклади (`uk-UA` відповідає `uk`; без збігу — перша, тобто типова, якщо для неї є переклади), і додає `Content-Language` та `Vary: Accept-Language` до відповіді з документом.

## Доступ

З `ufo_json_rpc.security.protected_doc: true` документи потребують валідного токена групи, див. [Безпека](security.md).

## Обмеження

- Документи генеруються на кожен запит; кешу з `ETag`, як для RPC-документації, немає.
- Postman-запити `GET`-методів також містять JSON-тіло з параметрами.
