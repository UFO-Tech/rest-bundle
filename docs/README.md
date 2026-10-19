[Українська](README-UA.md)

# ufo-tech/rest-bundle documentation

rest-bundle publishes methods of [JsonRpcBundle](https://github.com/ufo-tech/json-rpc-bundle) as a REST API. The RPC method stays the single implementation: parameters, validation, security, caching, events and documentation come from the RPC core; the bundle adds HTTP routes, the REST request/response format and OpenAPI/Postman documents.

| Topic | What it covers |
| --- | --- |
| [Getting started](en/getting-started.md) | Installation, route import, the first REST method, requirements |
| [Principles and architecture](en/architecture.md) | How the adapter is built, its components and design rules |
| [Routing](en/routing.md) | `#[Route]` rules, route names, versions, API groups, several imports, other adapters |
| [Request lifecycle](en/request-lifecycle.md) | From the HTTP request to the RPC call and back; parameters; events |
| [Errors](en/errors.md) | RFC 9457 problem details, RPC → HTTP status mapping |
| [API documentation](en/documentation.md) | OpenAPI 3.1 and Postman documents, translations |
| [Security](en/security.md) | Tokens for calls and documents, per-group providers |
| [Extending](en/extending.md) | Replaceable services, RPC events, custom errors |
| [Testing](en/testing.md) | Running the test suite, its layout, coverage |

Release notes: [releases/](../releases/README.md).
