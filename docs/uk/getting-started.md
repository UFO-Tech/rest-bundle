[English](../en/getting-started.md) · [Зміст](../README-UA.md)

# Початок роботи

## Вимоги

| | Версія |
| --- | --- |
| PHP | ^8.4 |
| Symfony | ^7.2 \| ^8.0 |
| ufo-tech/json-rpc-bundle | ^10.4.3 |
| cebe/php-openapi | ^1.8 |

Застосунок уже має обслуговувати JsonRpcBundle: rest-bundle використовує його service map, конфігурацію (`ufo_json_rpc`), безпеку та переклади.

## Встановлення

```bash
composer require ufo-tech/rest-bundle
```

Зареєструйте бандл після JsonRpcBundle, якщо Symfony Flex цього не зробив:

```php
// config/bundles.php
return [
    // ...
    Ufo\JsonRpcBundle\UfoJsonRpcBundle::class => ['all' => true],
    Ufo\RestBundle\RestBundleBundle::class => ['all' => true],
];
```

Імпортуйте REST-маршрути. Шаблон лежить у `install/routes/ufo_rest_api.yaml`:

```yaml
# config/routes/ufo_rest_api.yaml
ufo_rest_api:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /rest
    trailing_slash_on_root: false
```

`prefix` — це URL REST-входу. Більше входів (по одному на API-групу) описано в розділі [Маршрутизація](routing.md#api-групи-та-кілька-входів).

## Перший REST-метод

RPC-процедура стає доступною через REST, коли її метод має атрибут Symfony `#[Route]`. Атрибут на класі необов'язковий і задає спільний шлях та ім'я.

```php
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('catalog')]
#[Route('/catalog', name: 'catalog')]
class CatalogApi implements IRpcService
{
    #[Route('/items', name: 'list', methods: ['GET'])]
    public function list(int $page = 1, bool $inStock = false): array
    {
        return ['page' => $page, 'inStock' => $inStock, 'items' => []];
    }

    #[Route('/items/{id}', name: 'update', methods: ['PUT'])]
    public function update(string $id, #[RPC\Assertions([new Assert\Length(min: 3)])] string $title): array
    {
        return ['id' => $id, 'title' => $title];
    }
}
```

| Запит | RPC-виклик |
| --- | --- |
| `GET /rest/catalog/items?page=2&inStock=true` | `catalog.list` з `{"page": 2, "inStock": true}` |
| `PUT /rest/catalog/items/42` з `{"title": "Book"}` | `catalog.update` з `{"id": "42", "title": "Book"}` |

Значення зі шляху, query string і полів форми приводяться до оголошеного скалярного типу параметра (`int`, `float`, `bool`, nullable). Enum, дати, UUID і Doctrine-сутності конвертує зі рядка ядро RPC. Масиви й об'єкти передаються в JSON-тілі. Див. [Життєвий цикл запиту](request-lifecycle.md#приведення-типів).

Тіло відповіді — результат методу в JSON зі статусом `200`. Помилки — це [RFC 9457 problem details](errors.md).

Ті самі методи лишаються доступними через JSON-RPC (`POST /rpc` з `"method": "catalog.list"`).

## Документація

| URL | Документ |
| --- | --- |
| `GET /rest` | OpenAPI 3.1 |
| `GET /rest/postman` | Postman-колекція |
| `GET /rest/v2`, `GET /rest/v2/postman` | те саме для версії API `v2` |

Див. [Документація API](documentation.md).

## Що далі

- [Принципи й архітектура](architecture.md) — що робить бандл і що він делегує ядру RPC.
- [Маршрутизація](routing.md) — правила імен, версії, API-групи.
- [Безпека](security.md) — токени.
