[Українська](../uk/extending.md) · [Contents](../README.md)

# Extending

## RPC events

A REST call fires the same events as a JSON-RPC call, so application listeners work for both:

| Event | Constant | When |
| --- | --- | --- |
| `rpc.request` | `RpcEvent::REQUEST` | the request is accepted |
| `rpc.pre_execute` | `RpcEvent::PRE_EXECUTE` | before the method, after parameter conversion |
| `rpc.post_execute` | `RpcEvent::POST_EXECUTE` | after the method |
| `rpc.pre_response` | `RpcEvent::PRE_RESPONSE` | the response is built |
| `rpc.error` | `RpcEvent::ERROR` | any error |
| `rpc.post_response` | `RpcEvent::POST_RESPONSE` | on `kernel.terminate`, after a successful call |

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

To tell REST calls from RPC calls in a listener, check the carrier: `RequestCarrier::getCarrier() instanceof RpcFromRest`.

## Replacing the request parser

The controller depends on `Ufo\RestBundle\Interfaces\RestRequestParserInterface`. Extend `RestRequestParser` and alias the interface:

```yaml
# config/services.yaml
Ufo\RestBundle\Interfaces\RestRequestParserInterface: '@App\Rest\TenantAwareParser'
```

```php
final class TenantAwareParser extends RestRequestParser
{
    public function parse(string $routeName, Request $request): Service
    {
        // e.g. reject a tenant header before the call
        return parent::parse($routeName, $request);
    }
}
```

The parser must leave the carrier in `RequestCarrier`: the controller executes `RequestCarrier::getCarrier()`. Exceptions implementing `RestErrorInterface` are returned with their own code as the status.

## Reading the REST map

`Ufo\RestBundle\Services\RestServiceMap` gives the REST routes and RPC services of the current API group:

```php
foreach ($restServiceMap->getVersions() as $version) {
    foreach ($restServiceMap->getRoutes($version) as $name => $route) {
        $service = $restServiceMap->getService($name, $version); // RPC Service
        // $route->getPath(), $route->getMethods()
    }
}
```

## Translations for a bundle

A bundle that adds RPC methods can ship their titles and descriptions; REST documents pick them up:

```php
public function prepend(ContainerBuilder $container): void
{
    $container->prependExtensionConfig('ufo_json_rpc', [
        'translations' => ['paths' => [__DIR__.'/../../translations']],
    ]);
}
```

## Another adapter next to REST

An adapter that imports its own router file per API group implements `Ufo\JsonRpcBundle\Routing\IApiRouteImport` (autoconfigured, tag `ufo.rpc.route_import`), like `RestRouteImport` does. Do not extend the RPC YAML loader for this.
