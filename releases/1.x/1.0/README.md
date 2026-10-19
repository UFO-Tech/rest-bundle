[Українська](README-UA.md)

# 1.0 — REST API from RPC methods

Tags: none (the version was set in `composer.json`). First released: 2026-02-17.

## Highlights

- **REST without a second implementation**: a Symfony `#[Route]` attribute on an RPC procedure class and method publishes the method under its own path and HTTP method.
- **One entry point** imported from the bundle's `config/router.yaml`, by default under `/rest`.
- **OpenAPI 3.1** at `GET /rest` and **Postman** at `GET /rest/postman`, built from the RPC service map.
- **Errors as RFC 9457 problem details** with an HTTP status derived from the RPC error.
- **API versions**: methods of `RPC\Info(version: 2)` are served under `/rest/v2/...` with documents at `/rest/v2` and `/rest/v2/postman`.

## What's new

### Publishing a method

```php
use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('catalog')]
#[Route('/catalog', name: 'catalog')]
class CatalogApi implements IRpcService
{
    #[Route('/items/{id}', name: 'item', methods: ['GET'])]
    public function item(string $id): array { /* ... */ }
}
```

`GET /rest/catalog/items/42` calls `catalog.item` with `id = "42"`. Methods without `#[Route]` stay RPC-only.

### Routes

```yaml
# config/routes/ufo_rest_api.yaml
ufo_rest_api:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /rest
    trailing_slash_on_root: false
```

The resource could be imported only once in 1.0.

### Parameters

Route variables, query string and the JSON body are merged into the RPC params.

## Known issues

- An invalid JSON body was silently ignored.
- A request with a wrong HTTP method reached the catch-all entry point and returned `404`.
