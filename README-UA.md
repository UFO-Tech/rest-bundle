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

## 📚 Документація

- [Документація](docs/README-UA.md): принципи, маршрутизація, життєвий цикл запиту, помилки, OpenAPI/Postman, безпека, розширення, тестування.
- [Історія релізів](releases/README-UA.md).

---

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

Обробка API-груп в імпортах маршрутів належить JsonRpcBundle: контекст
встановлюється до реєстрації маршрутів і відновлюється після неї; назви,
пріоритети та старі аліаси обробляються централізовано. `RestRouteImport`
(`Ufo\JsonRpcBundle\Routing\IApiRouteImport`) лише реєструє REST
routing-ресурс у core loader і задає `_rest_name_prefix`, тож REST працює поруч з
іншими адаптерами, наприклад mcp-bundle. Потрібен `ufo-tech/json-rpc-bundle` 10.4.3+.

REST-виклики виконує core `RpcExecutor`: помилки проходять через `rpc.error`, як і
в RPC, а успішний виклик запускає `rpc.post_response` на `kernel.terminate`, тож
інвалідація `#[CacheRelation]` і post-response лістенери працюють і для REST.

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

Потрібні PHP 8.4+ та `ufo-tech/json-rpc-bundle` 10.4.3+. Імпортуйте REST-маршрути
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

## Переклад документації (JsonRpcBundle 10.4+)

Документи OpenAPI і Postman використовують переклади RPC-методів
(`translations/rpc.<locale>.yaml`, перевизначення для групи в
`translations/rpc/<group>.<locale>.yaml`, директорії розширень з
`ufo_json_rpc.translations.paths`). Мова вибирається із заголовка
`Accept-Language` так само, як для RPC-документації; відповідь отримує
`Content-Language` і `Vary: Accept-Language`.

```yaml
# translations/rpc.uk.yaml
catalog.list:
    title: Каталог
    description: Список товарів
```

В OpenAPI назва стає `summary` операції, а опис — її `description`. Для методу без
назви опис, як і раніше, лишається в `summary`.

Запуск регресійних тестів із кореня репозиторію:

```sh
composer install
vendor/bin/phpunit -c phpunit.xml.dist
```
