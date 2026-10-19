[English](../en/errors.md) · [Зміст](../README-UA.md)

# Помилки

## Формат

Кожна помилка REST-виклику чи документа — це RFC 9457 problem-документ із HTTP-статусом помилки:

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

| Поле | Значення |
| --- | --- |
| `status` | HTTP-статус, такий самий, як статус відповіді |
| `instance` | шлях запиту |
| `title` | клас REST-винятку |
| `detail` | повідомлення помилки |
| `detail-data` | лише коли RPC-помилка містить масив: повідомлення по параметрах для помилок валідації й типів |

## Як вибирається статус

Ядро RPC перетворює будь-який виняток методу на RPC-помилку (`rpc.error`): RPC-винятки зберігають клас і код, `TypeError` аргументу стає помилкою параметра, будь-який інший виняток обгортається за його кодом. REST-шар відтворює виняток з RPC-помилки і мапить його через `RestErrorNormalizer`:

| RPC-виняток | REST-виняток | Статус |
| --- | --- | --- |
| `RpcJsonParseException` | `RestBadRequestException` | 400 |
| `RpcMethodNotFoundExceptionRpc` | `RestResourceNotFoundException` | 404 |
| `ConstraintsImposedException` | `RestValidationException` | 422 |
| `RpcBadParamException` (помилки валідації й типів параметрів) | `RestBadRequestException` | 400 |
| `RpcBadRequestException` | `RestBadRequestException` | 400 |
| `RpcInternalException`, `RpcRuntimeException` | `RestInternalServerErrorException` | 500 |
| `RpcLogicException` | `RestBadRequestException` | 400 |
| `RpcTokenNotSentException`, `RpcInvalidTokenException` | `RestUnauthorizedException` | 401 |
| `RpcDataNotFoundException` | `RestResourceNotFoundException` | 404 |
| `CustomApplicationException` | `RestApplicationException` | його код, якщо 400–511, інакше 400 |
| будь-що інше | `RestBadRequestException` | його код, якщо 400–511, інакше 400 |

Таблиця перевіряється згори донизу через `instanceof`, тому підкласи стоять перед батьківськими класами.

Помилки самого REST-шару:

| Ситуація | Виняток | Статус |
| --- | --- | --- |
| немає REST-маршруту для шляху, невідома версія, метод іншої групи | `RestResourceNotFoundException` | 404 |
| HTTP-метод не дозволений для маршруту | `RestResourceNotAllowedException` | 405 |
| некоректне JSON-тіло, скалярне JSON-тіло | `RestBadRequestException` | 400 |

## Як повернути конкретний статус із методу

Киньте RPC-виняток з таблиці або будь-який виняток з HTTP-кодом:

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

У JSON-RPC ті самі винятки дають звичайні RPC-помилки, тож один метод обслуговує обидва протоколи.

Примітки:

- Виняток із кодом `0` стає внутрішньою RPC-помилкою і повертає `500`.
- Помилки валідації ядра RPC повертають `400` з `detail-data`. `422` використовується лише для `ConstraintsImposedException`, що напряму доходить до REST-шару.
- Відповідь містить лише повідомлення; трейси в problem-документ не потрапляють.

## У документації

`@throws` методу додає відповіді з помилками до його операції OpenAPI: виняток мапиться, як описано вище, і на кожен статус додається одна відповідь. Див. [Документація API](documentation.md#openapi).
