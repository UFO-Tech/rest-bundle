[English](../en/extending.md) · [Зміст](../README-UA.md)

# Розширення

## RPC-події

REST-виклик запускає ті самі події, що й JSON-RPC-виклик, тож лістенери застосунку працюють для обох:

| Подія | Константа | Коли |
| --- | --- | --- |
| `rpc.request` | `RpcEvent::REQUEST` | запит прийнято |
| `rpc.pre_execute` | `RpcEvent::PRE_EXECUTE` | перед методом, після конвертації параметрів |
| `rpc.post_execute` | `RpcEvent::POST_EXECUTE` | після методу |
| `rpc.pre_response` | `RpcEvent::PRE_RESPONSE` | відповідь сформовано |
| `rpc.error` | `RpcEvent::ERROR` | будь-яка помилка |
| `rpc.post_response` | `RpcEvent::POST_RESPONSE` | на `kernel.terminate`, після успішного виклику |

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostResponseEvent;

#[AsEventListener(event: RpcEvent::POST_RESPONSE)]
final class AuditListener
{
    public function __invoke(RpcPostResponseEvent $event): void
    {
        // $event->rpcRequest->getMethod(), $event->response, $event->service
    }
}
```

Щоб відрізнити в лістенері REST-виклик від RPC-виклику, перевірте carrier: `RequestCarrier::getCarrier() instanceof RpcFromRest`.

## Заміна парсера запитів

Контролер залежить від `Ufo\RestBundle\Interfaces\RestRequestParserInterface`. Успадкуйте `RestRequestParser` і задайте аліас інтерфейсу:

```yaml
# config/services.yaml
Ufo\RestBundle\Interfaces\RestRequestParserInterface: '@App\Rest\TenantAwareParser'
```

```php
final class TenantAwareParser extends RestRequestParser
{
    public function parse(string $routeName, Request $request): Service
    {
        // наприклад, відхилити заголовок tenant до виклику
        return parent::parse($routeName, $request);
    }
}
```

Парсер має залишити carrier у `RequestCarrier`: контролер виконує `RequestCarrier::getCarrier()`. Винятки, що реалізують `RestErrorInterface`, повертаються зі статусом, рівним їхньому коду.

## Читання REST-мапи

`Ufo\RestBundle\Services\RestServiceMap` повертає REST-маршрути й RPC-сервіси поточної API-групи:

```php
foreach ($restServiceMap->getVersions() as $version) {
    foreach ($restServiceMap->getRoutes($version) as $name => $route) {
        $service = $restServiceMap->getService($name, $version); // RPC Service
        // $route->getPath(), $route->getMethods()
    }
}
```

## Переклади для бандла

Бандл, що додає RPC-методи, може постачати їхні назви й описи; REST-документи їх підхоплять:

```php
public function prepend(ContainerBuilder $container): void
{
    $container->prependExtensionConfig('ufo_json_rpc', [
        'translations' => ['paths' => [__DIR__.'/../../translations']],
    ]);
}
```

## Інший адаптер поруч із REST

Адаптер, що імпортує власний файл маршрутів для кожної API-групи, реалізує `Ufo\JsonRpcBundle\Routing\IApiRouteImport` (autoconfigure, тег `ufo.rpc.route_import`), як це робить `RestRouteImport`. Не успадковуйте для цього RPC YAML loader.
