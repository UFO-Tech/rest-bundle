[Українська](README-UA.md)

# 1.1 — Several REST entry points per API group

Tags: 1.1.0. First released: 2026-10-15.

## Highlights

- **API groups**: import the REST routes once per group; every entry point has its own methods, versions, OpenAPI and Postman documents and token provider.
- **Group-prefixed route names** (`internal_ufo_rest_doc`) with the old names kept as aliases of `general`.
- **Correct HTTP semantics**: `405` for a wrong method, `HEAD` served by `GET` routes, `400` for an invalid JSON body.

## What's new

### Entry points per group

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
```

Methods are assigned to groups with `#[RPC\APIGroups]` (JsonRpcBundle 10.2). A method also needs `#[Route]` to appear in REST. The URL prefix is independent of the group name; an import without `api_group` is `general`. The same group can be imported twice with distinct `name_prefix` values.

### Security per group

REST uses the RPC security configuration: `protected_api` for calls, `protected_doc` for documents, and `security.providers` to validate tokens per group.

### Requests

- Parameters are merged in this order: route variables, query, form fields, JSON body; later keys win.
- With `Content-Type: application/x-www-form-urlencoded` the body is read as form fields only.
- A JSON body that is not an object or array returns `400`.
- Service route attributes (`api_group`, `ver`, `_rest_route`, …) are not passed as params.

## Changes and fixes

- `x-ufo.environment` of the OpenAPI server no longer contains `fromCache`; `RestServiceMap::isFromCache()` is removed.
- Every REST route gets a `<route>_method_not_allowed` fallback, so a wrong method returns `405` with problem details.
- Requests are isolated in long-running workers: the RPC event pool is reset per request.

## Upgrade notes

- PHP 8.4 and `ufo-tech/json-rpc-bundle` ^10.2 are required.
- Route names have a group prefix; generate URLs by the new names or rely on the `general` aliases.
- Clients that sent an invalid JSON body now get `400`; clients that used a wrong HTTP method get `405` instead of `404`.
