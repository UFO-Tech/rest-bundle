[Українська](README-UA.md)

# Release notes

What was added in every version and how to use it. Each minor version has its own page; the major has an overview with requirements and upgrade notes: [1.x](1.x/README.md).

| Version | Released | Highlights |
| --- | --- | --- |
| [1.3](1.x/1.3/README.md) | 2026-10-19 | JsonRpcBundle 10.4.3: calls through the core `RpcExecutor` (`rpc.post_response`, `#[CacheRelation]` for REST), routes registered with `IApiRouteImport` (works next to mcp-bundle), path/query/form values cast to parameter types, translated OpenAPI/Postman via `Accept-Language`; error mapping fixes |
| [1.2](1.x/1.2/README.md) | 2026-10-16 | Symfony 8 support, Postman collection built on the core `AbstractPostmanAdapter` (`REST: <project> [group]` name), `composer test` |
| [1.1](1.x/1.1/README.md) | 2026-10-15 | Several REST entry points per API group with own routes, versions, documents and tokens; strict request body parsing, `HEAD`, `405` for a wrong method |
| [1.0](1.x/1.0/README.md) | 2026-02-17 | REST API from RPC methods with Symfony `#[Route]`, OpenAPI 3.1 and Postman documents, RFC 9457 errors, API versions |
