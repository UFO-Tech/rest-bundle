[Українська](../uk/architecture.md) · [Contents](../README.md)

# Principles and architecture

## Principles

1. **The RPC method is the only implementation.** REST is another transport for the same method. There is no REST controller per method, no second set of DTOs and no separate validation.
2. **Publication is explicit.** A method appears in REST only with a Symfony `#[Route]` attribute. Everything else stays RPC-only. `#[RPC\IgnoreApi]` and `#[RPC\APIGroups]` work as in RPC.
3. **Execution belongs to the RPC core.** The bundle converts an HTTP request into an `RpcRequest` and runs it with the core `RpcExecutor`. Parameter conversion, `#[RPC\Assertions]`, `#[RPC\Cache]`, locks and `rpc.*` events behave exactly as for a JSON-RPC call.
4. **Groups and versions come from the RPC service map.** Each API group and API version has its own REST routes and documents; inherited and removed methods follow `RPC\Info(version:, removedMethods:)`.
5. **HTTP semantics at the edge.** The REST layer owns only what JSON-RPC lacks: paths, HTTP methods, status codes (`404`, `405`, `401`, …) and the RFC 9457 error format.
6. **Documentation from the same metadata.** OpenAPI and Postman are generated from the service map (types, PHPDoc, `@throws`, translations), so they cannot drift from the code.

## Components

| Component | File | Role |
| --- | --- | --- |
| `RestServiceMap` | `src/Services/RestServiceMap.php` | Reads the RPC service map of the current API group and derives REST routes from `#[Route]` attributes (class + method), per version |
| `RpcRestRouteLoader` | `src/Services/RpcRestRouteLoader.php` | Symfony route loader of type `ufo_rest_api`: one route per method and version plus a `_method_not_allowed` fallback |
| `RestRouteImport` | `src/Routing/RestRouteImport.php` | Registers `config/router.yaml` with the core RPC YAML loader, which handles API groups and route names |
| `RestControllerListener` | `src/EventDispatcher/RestControllerListener.php` | After routing: sets the API group and sends REST routes to the entry point controller |
| `RestDocLocaleListener` | `src/EventDispatcher/RestDocLocaleListener.php` | Locale of the documentation from `Accept-Language` |
| `RestRequestParser` | `src/Services/RestRequestParser.php` | Resolves the REST route and RPC service, checks the HTTP method and the token, creates the carrier |
| `RpcFromRest` | `src/Services/RpcFromRest.php` | Carrier (`IRpcHttpCarrier`): builds the `RpcRequest` from route variables, query, form and JSON body; casts string values to the parameter types |
| `RestEntryPointController` | `src/Controller/RestEntryPointController.php` | Runs the call with `RpcExecutor`, returns the result or a problem; serves OpenAPI and Postman |
| `RestErrorNormalizer` and `Rest*Exception` | `src/Exceptions/` | Map RPC errors to REST exceptions with HTTP status codes |
| `OpenApiAdapter`, `PostmanAdapter` | `src/DocAdapters/Outputs/` | Build the documents from `RestServiceMap` |

## Two phases

**Container and route compilation**

```
RPC procedures ──► JsonRpcBundle ServiceMap (per group, per version)
                        │
config/routes/*.yaml ──► core RPC YAML loader ── RestRouteImport recognizes config/router.yaml
                        │   sets the API group for the import, prefixes names: <group>_<name>
                        ▼
              config/router.yaml
               ├─ controller routes: docs (/, /postman, /{ver}, /{ver}/postman), catch-all entry point
               └─ ufo_rest_api resource ──► RpcRestRouteLoader ──► RestServiceMap ──► routes from #[Route]
```

**Request**

```
HTTP request ─► RouterListener (32) ─► RestControllerListener (30): group, controller, rpcRoute, ver
            ─► RestDocLocaleListener (8, documents only)
            ─► RestEntryPointController::rest
                 ├─ RestRequestParser: route → service, HTTP method, token, RpcFromRest carrier
                 ├─ RpcExecutor: rpc.request → rpc.pre_execute → method → rpc.post_execute → rpc.pre_response
                 │               (errors: rpc.error)
                 └─ 200 + JSON result | RFC 9457 problem
            ─► kernel.terminate: rpc.post_response for a successful call
```

Details: [Routing](routing.md), [Request lifecycle](request-lifecycle.md).

## What the bundle does not do

- It does not define its own configuration tree: everything is configured in `ufo_json_rpc` (security, docs, translations, cache).
- It does not support JSON-RPC batches, notifications or `$rpc.callback` over REST: one HTTP request is one method call.
- It does not cache the documents with `ETag` (the RPC documentation does).
