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

## ⚙️ Installation

```bash
composer require ufo-tech/rpc-rest-adapter
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
