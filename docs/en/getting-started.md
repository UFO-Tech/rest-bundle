[Українська](../uk/getting-started.md) · [Contents](../README.md)

# Getting started

## Requirements

| | Version |
| --- | --- |
| PHP | ^8.4 |
| Symfony | ^7.2 \| ^8.0 |
| ufo-tech/json-rpc-bundle | ^10.4.3 |
| cebe/php-openapi | ^1.8 |

The application must already serve JsonRpcBundle: rest-bundle reuses its service map, configuration (`ufo_json_rpc`), security and translations.

## Installation

```bash
composer require ufo-tech/rest-bundle
```

Register the bundle after JsonRpcBundle if Symfony Flex did not do it:

```php
// config/bundles.php
return [
    // ...
    Ufo\JsonRpcBundle\UfoJsonRpcBundle::class => ['all' => true],
    Ufo\RestBundle\RestBundleBundle::class => ['all' => true],
];
```

Import the REST routes. The template is in `install/routes/ufo_rest_api.yaml`:

```yaml
# config/routes/ufo_rest_api.yaml
ufo_rest_api:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /rest
    trailing_slash_on_root: false
```

`prefix` is the URL of the REST entry point. More entry points (one per API group) are described in [Routing](routing.md#api-groups-and-several-entry-points).

## The first REST method

An RPC procedure becomes available over REST when its method has a Symfony `#[Route]` attribute. The class attribute is optional and gives a common path and name.

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

| Request | RPC call |
| --- | --- |
| `GET /rest/catalog/items?page=2&inStock=true` | `catalog.list` with `{"page": 2, "inStock": true}` |
| `PUT /rest/catalog/items/42` with `{"title": "Book"}` | `catalog.update` with `{"id": "42", "title": "Book"}` |

Values from the path, the query string and form fields are cast to the declared scalar type of the parameter (`int`, `float`, `bool`, nullable). Enums, dates, UUIDs and Doctrine entities are converted from strings by the RPC core. Arrays and objects are sent in the JSON body. See [Request lifecycle](request-lifecycle.md#type-casting).

The response body is the method result as JSON with status `200`. Errors are [RFC 9457 problem details](errors.md).

The same methods are still available through JSON-RPC (`POST /rpc` with `"method": "catalog.list"`).

## Documentation endpoints

| URL | Document |
| --- | --- |
| `GET /rest` | OpenAPI 3.1 |
| `GET /rest/postman` | Postman collection |
| `GET /rest/v2`, `GET /rest/v2/postman` | the same for API version `v2` |

See [API documentation](documentation.md).

## Next steps

- [Principles and architecture](architecture.md) — what the bundle does and what it delegates to the RPC core.
- [Routing](routing.md) — naming rules, versions, API groups.
- [Security](security.md) — tokens.
