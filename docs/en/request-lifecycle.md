[Українська](../uk/request-lifecycle.md) · [Contents](../README.md)

# Request lifecycle

## Steps

1. **Routing** (`RouterListener`, priority 32) matches a generated REST route. Its defaults carry `_rest_route` (the REST route name), `_ver` for versions other than `v1`, `api_group` and `_rest_name_prefix`.
2. **`RestControllerListener`** (priority 30) sets the API group in `ApiGroupContext` and, for a REST call, points `_controller` to `RestEntryPointController::rest` with `rpcRoute` and `ver` attributes.
3. **`RestRequestParser::parse()`**
   - resolves the REST route and the RPC `Service` of the group and version (`404` if missing);
   - checks the HTTP method (`HEAD` counts as `GET`; `405` if not allowed);
   - resets the RPC event pool, sets the HTTP token holder and the carrier, and checks the token (`protected_api`).
4. **`RpcFromRest`** builds the `RpcRequest`: a generated id, the RPC method name, the merged parameters (path, query and form values cast to the parameter types) and `apiVersion`.
5. **`RpcExecutor::execute()`** runs the request the way the JSON-RPC route does: `rpc.request` → `rpc.pre_execute` (parameter conversion and validation) → the method → `rpc.post_execute` → `rpc.pre_response`. Any error goes through `rpc.error` and becomes an error response.
6. **The controller** returns `200` with the method result as JSON, or converts the error into an [RFC 9457 problem](errors.md).
7. **`kernel.terminate`**: `rpc.post_response` fires for a successful call (cache relations, async and notification listeners). It does not fire for an error.

## Parameters

Sources are merged in this order; a later string key overrides an earlier one:

1. path variables of the route;
2. the query string;
3. form fields (`application/x-www-form-urlencoded`, `multipart/form-data`);
4. the JSON body.

```
PUT /rest/catalog/items/42?title=query
Content-Type: application/json

{"title": "Book"}
```

gives `{"id": "42", "title": "Book"}`.

Rules:

- An empty body means no body parameters.
- With a form content type the body is not parsed as JSON.
- A body that is not valid JSON returns `400 Invalid JSON request body`; JSON that is not an object or array (`null`, `42`, `"text"`) returns `400 JSON request body must be an object or array`.
- Route defaults used by the bundle are not parameters: `api_group`, `ver`, `rpcRoute`, `_rest_route`, `_rest_name_prefix`, `_rest_doc`, `_ver`, `_controller`, `_format`, `_locale`, `_stateless`.

## Type casting

Path, query and form values arrive as strings. Before the merge, `RpcFromRest::castString()` casts each of them to the type of the method parameter with the same name. JSON body values keep their JSON types and are not cast.

| Parameter type | Cast | Not cast (left as a string) |
| --- | --- | --- |
| `string`, `mixed`, a union with `string` (`int\|string`) | never: the string is valid | |
| nullable (`?int`, `?bool`, `int\|null`) | `""` → `null` | |
| `int` | an integer literal: `42`, `-7`, `0` | `007`, `+1`, ` 1`, `1.5`, out of the int range |
| `float` | a numeric string: `1.5`, `2`, `1e3` | `1,5` |
| `int\|float` | `2` → `2`, `2.5` → `2.5` | |
| `bool` | `true`, `1` → `true`; `false`, `0` → `false` (any case) | `yes`, `on`, `""` |
| enum, `DateTimeInterface`, `Uuid`, Doctrine entity | not here: the RPC core converts the string (JsonRpcBundle 10.4.3) | |
| `array`, other classes | never | |

A value that cannot be cast stays a string, and the RPC core answers `400` with `must be of type int, string given` in `detail-data`. Parameters that are not in the method signature are not touched.

An unknown enum value currently returns `500` (`Invalid value 'xml' for enum …`): the core converter throws a plain `RuntimeException`, in JSON-RPC as well.

## Response

- Success: status `200`, `Content-Type: application/json`, the method result as the body (no JSON-RPC envelope).
- Error: the status of the error and a problem document, see [Errors](errors.md).

## What applies from the RPC core

Because the call goes through the core executor, these features work for REST as for JSON-RPC:

- parameter conversion, default values, `#[RPC\Assertions]` and DTO validation;
- `#[RPC\Cache]` response cache and `#[RPC\CacheRelation]` invalidation (on `rpc.post_response`);
- `#[RPC\Lock]`;
- `rpc.*` event listeners of the application;
- `#[Autowire]` on method arguments;
- logging of errors by the core.

Not available over REST: batches, notifications and `$rpc.callback`.
