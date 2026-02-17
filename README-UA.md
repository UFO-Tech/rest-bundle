# 🚀 REST Bundle
![Ukraine](https://img.shields.io/badge/Glory-Ukraine-yellow?labelColor=blue)

**REST bundle для побудови RESTful сервісів в екосистемі UFO-Tech**

Надає інфраструктуру для побудови REST API сервісів з єдиною моделлю маршрутів, доступів та інтеграції з сервісами екосистеми UFO-Tech.

---

## 🧬 Ідея

Це розширення для [JSON-RPC-BUNDLE](https://github.com/ufo-tech/json-rpc-bundle), яке дозволяє відкривати REST API на основі RPC інфраструктури без додаткової конфігурації.

Це дає можливість швидкого розгортання API контура лише на тих методах, які мають бути доступними в публічному API.

---
![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185)
![Size](https://img.shields.io/github/repo-size/ufo-tech/rpc-rest-adapter?label=Size%20of%20the%20repository)
![package_version](https://img.shields.io/github/v/tag/ufo-tech/rpc-rest-adapter?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185)
![fork](https://img.shields.io/github/forks/ufo-tech/rpc-rest-adapter?color=green&logo=github&style=flat)

### Environment Requirements
![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/rpc-rest-adapter/php?logo=PHP&logoColor=white)
![ufo-tech/rpc-bundle](https://img.shields.io/packagist/dependency-v/ufo-tech/rpc-rest-adapter/ufo-tech/json-rpc-bundle?label=JsonRpcBundle&logo=ufo&logoColor=white)

## ⚙️ Встановлення

```bash
composer require ufo-tech/rpc-rest-adapter
```

---

## 🚦 Швидкий старт

Після встановлення адаптер автоматично реєструє єдину REST точку входу, яка проксуює запити до RPC‑методів.

За замовчуванням використовується endpoint:

```
/rest/{path}
```

де `{path}` — RPC маршрут (service/method).

---

## 📡 Приклад виклику

RPC метод:

```
user.getList
```

REST виклик:

```
GET /rest/users/
```

POST виклик з параметрами:

```json
POST /rest/user/getList
{
  "page": 1,
  "limit": 20
}
```

---

## ⚙️ Принцип роботи

Адаптер:

- приймає HTTP REST запит
- трансформує його у JSON‑RPC виклик
- передає у JsonRpcBundle
- повертає стандартну JSON відповідь

Ніякої додаткової конфігурації не потрібно. Достатньо додати аттрибут `#[Route]` до RPC сервісів, який має стати доступними через REST endpoint.

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

## 🔐 Публічний API

Для відкриття методів у публічний REST контур використовується стандартна конфігурація доступів JsonRpcBundle, тому контроль доступу повністю повторює RPC рівень.


## 🦠 Ліцензія

MIT © [UFO-Tech](https://github.com/ufo-tech)
