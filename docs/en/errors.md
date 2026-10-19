[Українська](../uk/errors.md) · [Contents](../README.md)

# Errors

## Format

Every error of a REST call or document is an RFC 9457 problem document with the HTTP status of the error:

```json
{
    "_error_object_standard": "RFC 9457",
    "status": 400,
    "type": "about:blank",
    "instance": "/rest/catalog/items/42",
    "title": "Ufo\\RestBundle\\Exceptions\\RestBadRequestException",
    "detail": "Invalid Data for call method: catalog.update",
    "detail-data": {
        "title": ["This value is too short. It should have 3 characters or more."]
    }
}
```

| Field | Value |
| --- | --- |
| `status` | HTTP status, the same as the response status |
| `instance` | the request path |
| `title` | class of the REST exception |
| `detail` | error message |
| `detail-data` | only when the RPC error carries an array: per-parameter messages of validation and type errors |

## How the status is chosen

The RPC core turns any exception of a method into an RPC error (`rpc.error`): RPC exceptions keep their class and code, a `TypeError` of an argument becomes a parameter error, any other exception is wrapped by its code. The REST layer recreates the exception from the RPC error and maps it with `RestErrorNormalizer`:

| RPC exception | REST exception | Status |
| --- | --- | --- |
| `RpcJsonParseException` | `RestBadRequestException` | 400 |
| `RpcMethodNotFoundExceptionRpc` | `RestResourceNotFoundException` | 404 |
| `ConstraintsImposedException` | `RestValidationException` | 422 |
| `RpcBadParamException` (validation and type errors of parameters) | `RestBadRequestException` | 400 |
| `RpcBadRequestException` | `RestBadRequestException` | 400 |
| `RpcInternalException`, `RpcRuntimeException` | `RestInternalServerErrorException` | 500 |
| `RpcLogicException` | `RestBadRequestException` | 400 |
| `RpcTokenNotSentException`, `RpcInvalidTokenException` | `RestUnauthorizedException` | 401 |
| `RpcDataNotFoundException` | `RestResourceNotFoundException` | 404 |
| `CustomApplicationException` | `RestApplicationException` | its code if 400–511, else 400 |
| anything else | `RestBadRequestException` | its code if 400–511, else 400 |

The table is checked top-down with `instanceof`, so subclasses come before their parents.

Errors raised by the REST layer itself:

| Situation | Exception | Status |
| --- | --- | --- |
| no REST route for the path, unknown version, method of another group | `RestResourceNotFoundException` | 404 |
| HTTP method not allowed for the route | `RestResourceNotAllowedException` | 405 |
| invalid JSON body, JSON scalar body | `RestBadRequestException` | 400 |

## Returning a specific status from a method

Throw an RPC exception from the table, or any exception with an HTTP code:

```php
use Ufo\RpcError\CustomApplicationException;
use Ufo\RpcError\RpcDataNotFoundException;

public function item(string $id): array
{
    $item = $this->repository->find($id) ?? throw new RpcDataNotFoundException('Item is not found'); // 404
    if ($item->isClosed()) {
        throw new CustomApplicationException('Item is closed', 409);                              // 409
    }
    return $item->toArray();
}
```

In JSON-RPC the same exceptions give the usual RPC errors, so one method serves both protocols.

Notes:

- An exception with code `0` becomes an internal RPC error and returns `500`.
- Validation errors of the RPC core return `400` with `detail-data`. `422` is used only for a `ConstraintsImposedException` that reaches the REST layer directly.
- The response contains only the message; traces are not exposed in the problem document.

## In the documentation

`@throws` of a method adds error responses to its OpenAPI operation: the exception is mapped as above and one response is added per status. See [API documentation](documentation.md#openapi).
