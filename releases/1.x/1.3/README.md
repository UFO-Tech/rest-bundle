[Українська](README-UA.md)

# 1.3 — JsonRpcBundle 10.4: core executor, route imports, translated documentation

Tags: 1.3.0. First released: 2026-10-19.

> **Note:** a minor release with one removed class (`RestYamlFileLoader`) and a behaviour change for `rpc.post_response` listeners. Read the [Upgrade notes](#upgrade-notes).

## Highlights

- **REST calls go through the core `RpcExecutor`**: errors pass `rpc.error` like RPC calls, and `rpc.post_response` fires after a successful REST call, so `#[RPC\CacheRelation]` invalidation and post-response listeners work for REST.
- **Routes registered with `IApiRouteImport`**: REST works side by side with other adapters (mcp-bundle) whichever route loader wins.
- **Typed path, query and form values**: strings are cast to `int`, `float`, `bool` or `null` by the method signature, so `GET /items/{id}` works with `int $id`.
- **Translated documentation**: OpenAPI and Postman use RPC method translations picked by `Accept-Language`.
- **Error mapping fixed**: `404` for an unknown RPC method, `422` for constraint errors, HTTP codes of application errors kept, `RestApplicationException` loadable again.

## What's new

### `rpc.post_response` for REST

The REST controller executes the request with `Ufo\JsonRpcBundle\Server\RpcExecutor`. A successful call is queued and `rpc.post_response` fires on `kernel.terminate`, once per call; an error response gets none. The carrier `RpcFromRest` implements `IRpcHttpCarrier`, so it is tied to its HTTP request.

### Route import for adapters

`Ufo\RestBundle\Routing\RestRouteImport` registers the bundle's `config/router.yaml` with the core RPC YAML loader. Every import gets `<group>_<name>` route names and the `<group>_<name_prefix>` value in the `_rest_name_prefix` route default. Route names and aliases are the same as in 1.1–1.2.

### Type casting of path, query and form values

These values are always strings, and the RPC core rejected them for typed parameters (`must be of type int, string given`). `RpcFromRest` now casts a string to the declared scalar type of the parameter with the same name: an integer literal to `int`, a numeric string to `float`, `true`/`1`/`false`/`0` to `bool`, `""` to `null` for a nullable type. Types that accept strings (`string`, `mixed`, `int|string`) keep the value; a value that cannot be cast is left as is and reported by the core. The JSON body is not cast. Enums, dates, UUIDs and Doctrine entities are converted from strings by the RPC core again since JsonRpcBundle 10.4.3, which is why that version is required. Path variables are documented in OpenAPI with the schema of their parameter instead of `string`.

```php
#[Route('/items/{id}', name: 'item', methods: ['GET'])]
public function item(int $id, bool $full = false): array {}   // GET /rest/catalog/items/42?full=true
```

### Translated documentation

```yaml
# translations/rpc.uk.yaml
catalog.list:
    title: Каталог
    description: Список товарів
```

Group overrides go to `translations/rpc/<group>.<locale>.yaml`, extension directories to `ufo_json_rpc.translations.paths` (JsonRpcBundle 10.4). The REST documentation picks the locale from `Accept-Language` with the core `RpcLocaleNegotiator` and answers with `Content-Language` and `Vary: Accept-Language`.

In OpenAPI the title is the operation `summary` and the description its `description`. A method without a title keeps the description as `summary`, as before. Postman items get the translated description.

## Changes and fixes

- **`RestApplicationException`** extended `Ufo\RpcError\RpcCustomApplicationException`, which does not exist in `ufo-tech/rpc-exceptions` 2.x, so the class could not be loaded. It now extends `CustomApplicationException`; its constructor arguments are optional.
- **Error mapping order**: `RpcMethodNotFoundExceptionRpc` and `ConstraintsImposedException` are subclasses of `RpcBadRequestException` and were mapped to `400`; they now map to `404` and `422`.
- **HTTP status of application errors**: an error with an HTTP code (400–511), e.g. `CustomApplicationException('Order is closed', 409)` thrown by a method, returns that status instead of `400`. The check used `in_array()` on status texts and never matched.
- **`RestValidationException`** implements `RestErrorInterface` and can be created from a `ConstraintsImposedException` (it used to fail with `ArgumentCountError`).
- **`PostmanAdapter`** passes `RequestStack` to the core adapter; with JsonRpcBundle 10.3+ the redeclared property was a fatal error.
- `Service::getName()` (deprecated in JsonRpcBundle 10.3) is replaced with `$service->name`.

## Upgrade notes

- `ufo-tech/json-rpc-bundle` ^10.4.3 is required.
- **Removed** `Ufo\RestBundle\Routing\RestYamlFileLoader`. Applications that only import `config/router.yaml` need no changes. Code that extended or referenced the loader should register an `IApiRouteImport` instead.
- **`rpc.post_response` listeners now run after REST calls** — check listeners that assumed RPC-only calls.
- Responses of application errors with an HTTP code change from `400` to that code; title `RestApplicationException`.
- Path, query and form values of `int`, `float`, `bool` and nullable parameters are cast; requests that failed with `400` because of the string type now succeed.
- Constructors (only if you create them manually): `RestEntryPointController::rest()` takes `RpcExecutor` instead of `RpcRequestHandler`; `PostmanAdapter`'s `RequestStack` is no longer a redeclared property; `OpenApiSpecBuilder::buildOperation()` has a new optional trailing `?string $description`.

## Known issues

- REST documentation is not cached with `ETag` like the RPC documentation.
- Postman items of `GET` methods contain a raw JSON body with the method params.
- An unknown enum value returns `500`: the converter of JsonRpcBundle throws a plain `RuntimeException` (the same in JSON-RPC).
- Validation errors coming from the RPC core keep status `400` (`RpcBadParamException`); `422` applies only to a `ConstraintsImposedException` that reaches the REST layer directly.
