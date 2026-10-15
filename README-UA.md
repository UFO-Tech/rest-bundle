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
composer require ufo-tech/rest-bundle
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

YAML loader успадковує обробку API-груп із JsonRpcBundle: контекст встановлюється
до реєстрації маршрутів і відновлюється після неї; назви, пріоритети та старі
аліаси обробляються централізовано. `RestYamlFileLoader` лише розпізнає REST
routing-ресурс і задає `_rest_name_prefix`. Аргументи конструктора успадковуються
через autowiring; окремий блок аргументів loader у services.yaml не потрібен.
Потрібна версія JsonRpcBundle з `Routing\ApiYamlFileLoader`; під час розробки
використовуйте відповідний локальний checkout.

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

## Кілька REST API входів (JsonRpcBundle 10.2+)

Потрібні PHP 8.4+ та `ufo-tech/json-rpc-bundle` 10.2+. Імпортуйте REST-маршрути
окремо для кожного входу в YAML-файлі маршрутизації:

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

URL-префікс незалежний від назви групи. Без `api_group` використовується
`general`: наявний імпорт одного входу продовжує працювати. RPC та REST входи
можуть працювати разом і використовують однакові визначення груп.

Належність методів до груп визначається RPC-атрибутом `APIGroups`. Для
публікації через REST метод також повинен мати Symfony-атрибут `Route`:

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

Групи методу замінюють групи класу; `withDefault: true` додає `general`.
Без атрибутів груп метод належить до `general`. `IgnoreApi` повністю виключає
метод. Параметри query/body не дозволяють змінити групу запиту.

Кожен вхід має власні маршрути, версії та документи OpenAPI/Postman:
`GET /internal`, `/internal/postman`, `/internal/v2` та
`/internal/v2/postman`. Документація містить лише методи й версії відповідної
групи та URL поточного входу. Успадкування версій і вилучення методів
визначає RPC-мапа сервісів.

Імена маршрутів отримують префікс групи, наприклад `internal_ufo_rest_doc`.
Старі імена, зокрема `ufo_rest_doc`, залишаються псевдонімами для `general`.
Для повторного імпорту однієї групи задайте різні `name_prefix`: для
`internal` з `name_prefix: second_` ім'я маршруту документації буде
`internal_second_ufo_rest_doc`.

REST використовує конфігурацію безпеки RPC і провайдери токенів груп, включно
з `protected_api` та `protected_doc`. Спільний RPC-кеш і обмеження RPC щодо
однаково названих методів різних груп також стосуються REST.

Запуск регресійних тестів із кореня репозиторію:

```sh
composer install
vendor/bin/phpunit -c phpunit.xml.dist
```
