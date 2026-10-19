[Українська](README-UA.md)

# ufo-tech/rest-bundle 1.x

Major 1 exposes JsonRpcBundle methods as a REST API: a Symfony `#[Route]` attribute on an RPC procedure makes the method available over HTTP with its own path and HTTP method, while validation, security, caching and events stay in the RPC core. 1.0 introduced the adapter, 1.1 added several entry points per API group, 1.2 Symfony 8, 1.3 follows JsonRpcBundle 10.4 (core executor, route imports for adapters, translated documentation).

## Requirements

| | 1.0 | 1.1 | 1.2 | 1.3 |
| --- | --- | --- | --- | --- |
| PHP | ^8.3 | ^8.4 | ^8.4 | ^8.4 |
| Symfony | ^7.2 | ^7.2 | ^7.2 \| ^8.0 | ^7.2 \| ^8.0 |
| ufo-tech/json-rpc-bundle | ^10.0 | ^10.2 | ^10.2.3 | ^10.4.3 |
| cebe/php-openapi | ^1.8 | ^1.8 | ^1.8 | ^1.8 |

## Versions

| Version | Highlights |
| --- | --- |
| [1.0](1.0/README.md) | REST routes from `#[Route]` on RPC procedures; one entry point `/rest`; OpenAPI 3.1 (`GET /rest`) and Postman (`GET /rest/postman`); RFC 9457 problem details; API versions under `/v2/...` |
| [1.1](1.1/README.md) | One REST entry point per API group (`defaults: { api_group: ... }`), group-prefixed route names with legacy aliases, per-group documents and token providers, `405` instead of `404` for a wrong HTTP method, `HEAD` for `GET` routes, strict JSON body |
| [1.2](1.2/README.md) | Symfony 8; Postman collection from the core `AbstractPostmanAdapter`; `composer test` / `make test` |
| [1.3](1.3/README.md) | JsonRpcBundle 10.4.3: `RpcExecutor`, `rpc.post_response` for REST, `IApiRouteImport`, type casting of path/query/form values, translations by `Accept-Language`; fixed error mapping (`404`/`422`, application HTTP codes) |

## Upgrade notes

- **1.0 → 1.1**: PHP 8.4 and JsonRpcBundle 10.2 are required. Route names get a group prefix (`general_ufo_rest_doc`); the old names stay as aliases of the `general` group. A wrong HTTP method returns `405` instead of `404`. An invalid JSON body returns `400` instead of being ignored. See [1.1](1.1/README.md#upgrade-notes).
- **1.1 → 1.2**: no code changes for applications. The Postman collection name changed to `REST: <project>`, with ` [group]` for non-default groups.
- **1.2 → 1.3**: JsonRpcBundle 10.4.3 is required; `RestYamlFileLoader` is removed; `rpc.post_response` listeners now run after REST calls. See [1.3](1.3/README.md#upgrade-notes).
