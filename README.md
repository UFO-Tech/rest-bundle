# 🚀 RpcMercureTransport
![Ukraine](https://img.shields.io/badge/Glory-Ukraine-yellow?labelColor=blue)

**REST bundle for building RESTful services in the UFO-Tech ecosystem**

Provides infrastructure for building REST APIs with a unified routing model, access control, and integration across UFO-Tech services.

---

## 🧬 Idea

This is an extension for [JSON-RPC-BUNDLE](https://github.com/ufo-tech/json-rpc-bundle) that allows exposing a REST API based on the existing RPC infrastructure without additional configuration.

It enables fast deployment of an API layer only for the methods that must be available in the public API.

---
![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185)
![Size](https://img.shields.io/github/repo-size/ufo-tech/rpc-rest-adapter?label=Size%20of%20the%20repository)
![package_version](https://img.shields.io/github/v/tag/ufo-tech/rpc-rest-adapter?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185)
![fork](https://img.shields.io/github/forks/ufo-tech/rpc-rest-adapter?color=green&logo=github&style=flat)

### Environment Requirements
![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/rpc-rest-adapter/php?logo=PHP&logoColor=white)
![ufo-tech/rpc-bundle](https://img.shields.io/packagist/dependency-v/ufo-tech/rpc-rest-adapter/ufo-tech/json-rpc-bundle?label=JsonRpcBundle&logo=ufo&logoColor=white)

## 📚 Documentation

- [Documentation](docs/README.md): principles, routing, request lifecycle, errors, OpenAPI/Postman, security, extending, testing.
- [Release notes](releases/README.md).

---

## ⚙️ Installation

```bash
composer require ufo-tech/rest-bundle
```

---

## 🚦 Quick Start

After installation, the adapter automatically registers a single REST entry point that proxies requests to RPC methods.

By default, the following endpoint is used:

```
/rest/{path}
```

where `{path}` is the RPC route (service/method).

---

## 📡 Request Example

RPC method:

```
user.getList
```

REST request:

```
GET /rest/users/
```

POST request with parameters:

```json
POST /rest/user/getList
{
  "page": 1,
  "limit": 20
}
```

---

## ⚙️ How it works

API-group handling of route imports belongs to JsonRpcBundle: context is set
before route registration, restored afterwards, and route names, priorities and
legacy aliases are handled centrally. `RestRouteImport` (an
`Ufo\JsonRpcBundle\Routing\IApiRouteImport`) only registers the REST routing
resource with the core loader and selects `_rest_name_prefix`, so REST works side
by side with other adapters such as mcp-bundle. Requires
`ufo-tech/json-rpc-bundle` 10.4.3+.

REST calls are executed by the core `RpcExecutor`: errors go through `rpc.error`
like RPC calls, and a successful call fires `rpc.post_response` on
`kernel.terminate`, so `#[CacheRelation]` invalidation and post-response
listeners work for REST too.

The adapter:

- receives an HTTP REST request
- transforms it into a JSON-RPC call
- forwards it to JsonRpcBundle
- returns a standard JSON response

No additional configuration is required. It is enough to add the `#[Route]` attribute to RPC services that must be available via the REST endpoint.

```php
use Ufo\RpcObject\RPC;
use Symfony\Component\Routing\Attribute\Route;

#[RPC\Info(alias: 'User')]
#[Route('/users', name: 'users')]
class UserProcedure implements IRpcService
{
    #[Route('/', name: 'create', methods: ['POST'])]
    public function create(
        #[RPC\Assertions([
            new Assert\NotBlank(),
        ])]
        string $role,
        #[RPC\Assertions([
            new Assert\NotBlank(),
            new Assert\Regex(
                pattern: '/^\+380\d{9}$/', message: 'The phone number is not a valid UA mobile number'
            ),
        ])]
        string $phone,
        #[RPC\Assertions([
            new Assert\NotBlank(),
            new Assert\Length(min: 3),
        ])]
        string $firstName,
        #[RPC\Assertions([
            new Assert\NotBlank(),
            new Assert\Length(min: 3),
        ])]
        string $lastName,
    ): string
    {
        // create user
    }

    #[Route('/{userId}', name: 'update', methods: ['PUT'])]
    public function update(
        #[RPC\Assertions([
            new Assert\NotBlank(),
            new Assert\Uuid(),
        ])]
        string $userId,
        #[RPC\Assertions([
            new Assert\NotBlank(),
            new Assert\Length(min: 3),
        ])]
        string $firstName,
        #[RPC\Assertions([
            new Assert\NotBlank(),
            new Assert\Length(min: 3),
        ])]
        string $lastName,
    ): string
    {
        // update user
    }
}
```

---

## 🔐 Public API

To expose methods in the public REST layer, the standard JsonRpcBundle access configuration is used, therefore the access control fully mirrors the RPC layer.


## 🦠 License

MIT © [UFO-Tech](https://github.com/ufo-tech)

## Multiple REST API entry points (JsonRpcBundle 10.2+)

Requires PHP 8.4+ and `ufo-tech/json-rpc-bundle` 10.4.3+. Import the REST
routes once per entry point in a YAML routing file:

```yaml
rest:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /rest
    trailing_slash_on_root: false

rest_internal:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /internal
    defaults: { api_group: internal }
    trailing_slash_on_root: false

rest_public:
    resource: ../../vendor/ufo-tech/rest-bundle/config/router.yaml
    prefix: /public
    defaults: { api_group: public }
    trailing_slash_on_root: false
```

The URL prefix is independent of the group name. An import without `api_group`
uses `general`; existing single-entry imports continue to work. RPC and REST
entry points can coexist and use the same group definitions.

Use RPC `APIGroups` attributes to choose which methods are exposed. A method
also needs a Symfony `Route` attribute to appear in REST:

```php
use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('catalog')]
#[RPC\APIGroups(['internal', 'public'], false)]
#[Route('/catalog', name: 'catalog')]
class CatalogApi implements IRpcService
{
    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(): array { return []; }

    #[RPC\APIGroups(['internal'], false)]
    #[Route('/price', name: 'price', methods: ['POST'])]
    public function price(int $price): int { return $price; }
}
```

Method groups replace class groups; `withDefault: true` adds `general`.
Without group attributes, methods belong to `general`. `IgnoreApi` excludes a
method entirely. Requests cannot select another group through query/body fields.

Each entry point has its own routes, versions, OpenAPI and Postman documents:
`GET /internal`, `/internal/postman`, `/internal/v2`, and
`/internal/v2/postman`. Documents include only methods and versions available to
that group and use the current entry point's URL. Version inheritance and
removed methods follow the RPC service map.

Route names receive a group prefix, for example `internal_ufo_rest_doc`.
Legacy names such as `ufo_rest_doc` remain aliases for `general`. To import the
same group twice, supply distinct `name_prefix` values; for `internal` with
`name_prefix: second_`, the documentation route is
`internal_second_ufo_rest_doc`.

REST uses the RPC security configuration and group token providers, including
`protected_api` and `protected_doc`. Shared RPC cache behavior and the RPC
restrictions on identically named methods across groups also apply to REST.

## Translated documentation (JsonRpcBundle 10.4+)

OpenAPI and Postman documents use the RPC method translations
(`translations/rpc.<locale>.yaml`, group overrides in
`translations/rpc/<group>.<locale>.yaml`, extension directories from
`ufo_json_rpc.translations.paths`). The language is picked from
`Accept-Language` the same way as for RPC documentation; responses get
`Content-Language` and `Vary: Accept-Language`.

```yaml
# translations/rpc.uk.yaml
catalog.list:
    title: Каталог
    description: Список товарів
```

In OpenAPI the title becomes the operation `summary` and the description its
`description`. A method without a title keeps the description as `summary`, as
before.

Run the regression tests from the repository root:

```sh
composer install
vendor/bin/phpunit -c phpunit.xml.dist
```
