[English](README.md)

# 1.0 — REST API з RPC-методів

Теги: немає (версію задано в `composer.json`). Перший реліз: 2026-02-17.

## Головне

- **REST без другої реалізації**: атрибут Symfony `#[Route]` на класі та методі RPC-процедури публікує метод під власним шляхом і HTTP-методом.
- **Один вхід**, що імпортується з `config/router.yaml` бандла, за замовчуванням під `/rest`.
- **OpenAPI 3.1** на `GET /rest` і **Postman** на `GET /rest/postman`, побудовані з RPC service map.
- **Помилки як RFC 9457 problem details** з HTTP-статусом, отриманим з RPC-помилки.
- **Версії API**: методи з `RPC\Info(version: 2)` доступні під `/rest/v2/...`, документація — на `/rest/v2` і `/rest/v2/postman`.

## Що нового

### Публікація методу

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

`GET /rest/catalog/items/42` викликає `catalog.item` з `id = "42"`. Методи без `#[Route]` лишаються лише в RPC.

### Маршрути

```yaml
# config/routes/ufo_rest_api.yaml
ufo_rest_api:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /rest
    trailing_slash_on_root: false
```

У 1.0 ресурс можна було імпортувати лише один раз.

### Параметри

Змінні маршруту, query string і JSON-тіло об'єднуються в параметри RPC.

## Відомі проблеми

- Некоректне JSON-тіло мовчки ігнорувалось.
- Запит із неправильним HTTP-методом потрапляв у загальний вхід і повертав `404`.
