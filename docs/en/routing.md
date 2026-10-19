[Українська](../uk/routing.md) · [Contents](../README.md)

# Routing

## From `#[Route]` to a REST route

`RestServiceMap` reads every RPC method of the current API group and version. A method with a Symfony `Symfony\Component\Routing\Attribute\Route` attribute becomes a REST route; the attribute of the procedure class (optional) adds a common path and name.

| Part | Rule |
| --- | --- |
| Path | class path + `/` + method path; an empty method path gives the class path, both empty give `/` |
| Name | `ufo_rest_api_` + class name + `_` + method name |
| Class name | the class attribute `name`; without it the RPC procedure alias in snake_case (`shopItems` → `shop_items`) |
| Method name | the method attribute `name`; without it the PHP method name in snake_case (`defaultPost` → `default_post`) |
| HTTP methods | the method attribute `methods`; without them `POST` |
| Requirements, defaults, host, etc. | not used: only `path`, `name` and `methods` are read |

The attribute in the RPC service map is not changed: the bundle works on a copy.

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

Path variables (`{id}`) are passed to the method as parameters with the same name.

## Generated Symfony routes

For every REST route and API version the loader adds two Symfony routes:

| Route | Path | Methods | Purpose |
| --- | --- | --- | --- |
| `<name>_<version>` | `<path>`, or `/<version><path>` for versions other than `v1` | from the attribute | the call |
| `<name>_<version>_method_not_allowed` | the same | any | priority `-10`: a wrong HTTP method gets `405` instead of `404` |

The bundle's `config/router.yaml` also has controller routes:

| Route | Path | Purpose |
| --- | --- | --- |
| `ufo_rest_doc` | `` (the import prefix) | OpenAPI, `GET` |
| `ufo_rest_doc_ver` | `/{ver}` (`v\d+`) | OpenAPI of a version |
| `ufo_rest_doc_postman` | `/postman` | Postman |
| `ufo_rest_doc_postman_ver` | `/{ver}/postman` | Postman of a version |
| `ufo_rest_api_entrypoint` | `/{path}` | catch-all, priority `-100`: an unknown path returns a `404` problem |
| `ufo_rest_api_entrypoint_ver` | `/{ver}/{path}` | catch-all for versions, priority `-90` |

`HEAD` is accepted for `GET` routes.

## Versions

API versions come from `RPC\Info(version: N, removedMethods: [...])` of JsonRpcBundle. A procedure class of version 2 inherits the methods of version 1 that it does not override or remove.

| Version | Call | Documents | Route name |
| --- | --- | --- | --- |
| `v1` (default) | `/rest/catalog/items/1` | `/rest`, `/rest/postman` | `..._v1` |
| `v2` | `/rest/v2/catalog/items/1` | `/rest/v2`, `/rest/v2/postman` | `..._v2` |

An unknown version returns `404`.

## API groups and several entry points

Import `config/router.yaml` once per entry point. `defaults.api_group` selects the RPC API group; without it the group is `general`. The URL prefix is independent of the group.

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

- Methods are assigned to groups with `#[RPC\APIGroups]` on the class or method (a method attribute replaces the class one; `withDefault: true` adds `general`). A method still needs `#[Route]` to appear in REST.
- Every route gets the group prefix: `internal_ufo_rest_doc`, `internal_ufo_rest_api_catalog_item_v1`. An explicit `name_prefix` goes after it: `internal_partners_ufo_rest_doc`.
- Routes of `general` also keep the old names as aliases (`ufo_rest_doc` → `general_ufo_rest_doc`).
- Every route carries `api_group` and `_rest_name_prefix` (`<group>_<name_prefix>`) defaults; the documents use them to generate their own URLs.
- A request cannot switch groups through a query or body field: the group is a route default.

## Next to RPC and other adapters

`RestRouteImport` implements `Ufo\JsonRpcBundle\Routing\IApiRouteImport`. The core RPC YAML loader recognizes the import of `config/router.yaml` and applies the group handling above; other adapters (for example mcp-bundle) register their router files the same way. The RPC routes, REST routes and adapter routes can be imported for the same groups in one routing file without name conflicts.

## Troubleshooting

- **A method is not in REST**: check `#[Route]` on the method, the API group of the import, `#[RPC\IgnoreApi]`, and the version (`removedMethods`).
- **`405` for a method that exists**: the HTTP method is not in `methods` of the attribute (the default is `POST`).
- **`debug:router`** lists the generated names: `bin/console debug:router | grep ufo_rest`.
