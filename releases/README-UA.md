[English](README.md)

# Історія релізів

Що додано в кожній версії і як цим користуватися. Кожна мінорна версія має власну сторінку; мажорна — огляд із вимогами та нотатками з оновлення: [1.x](1.x/README-UA.md).

| Версія | Випущено | Головне |
| --- | --- | --- |
| [1.3](1.x/1.3/README-UA.md) | 2026-10-19 | JsonRpcBundle 10.4.3: виклики через core `RpcExecutor` (`rpc.post_response`, `#[CacheRelation]` для REST), маршрути через `IApiRouteImport` (працює поруч із mcp-bundle), приведення типів значень шляху/query/форми, переклад OpenAPI/Postman за `Accept-Language`; виправлення мапінгу помилок |
| [1.2](1.x/1.2/README-UA.md) | 2026-10-16 | Підтримка Symfony 8, Postman-колекція на базі core `AbstractPostmanAdapter` (назва `REST: <project> [group]`), `composer test` |
| [1.1](1.x/1.1/README-UA.md) | 2026-10-15 | Кілька REST-входів для API-груп із власними маршрутами, версіями, документацією і токенами; суворий розбір тіла запиту, `HEAD`, `405` для неправильного методу |
| [1.0](1.x/1.0/README-UA.md) | 2026-02-17 | REST API з RPC-методів через Symfony `#[Route]`, документи OpenAPI 3.1 і Postman, помилки RFC 9457, версії API |
