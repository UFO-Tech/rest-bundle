[Українська](../uk/testing.md) · [Contents](../README.md)

# Testing

## Running

```bash
composer install
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpunit -c phpunit.xml.dist --filter ErrorResponseTest
```

With Docker (`make up-d`, container `php_<PROJECT_NAME>`):

```bash
docker exec php_rest vendor/bin/phpunit -c phpunit.xml.dist
make test
```

Coverage (Xdebug is installed in the container):

```bash
docker exec -e XDEBUG_MODE=coverage php_rest vendor/bin/phpunit -c phpunit.xml.dist --coverage-filter src --coverage-text
```

`cebe/php-openapi` emits five PHP 8.4 deprecations (implicitly nullable parameters); they are expected.

## Layout

| Directory | Kind | What it checks |
| --- | --- | --- |
| `tests/Unit` | unit, no kernel | `RestServiceMap` route rules, `RpcRestRouteLoader`, `RpcFromRest` parameters, type casting and body errors, `RestErrorNormalizer` and REST exceptions, `RestControllerListener`, `RestDocLocaleListener`, `RestRouteImport`, OpenAPI and Postman builders, `RestBundleExtension`, `Package` |
| `tests/Integration` | container services | `RestRequestParser` (groups, versions, HTTP methods, tokens), listener priorities |
| `tests/Functional` | HTTP through the kernel | routes and aliases of several imports, execution, versions, parameters and type casting, errors (RFC 9457), documents and translations, security, `rpc.post_response`, coexistence with another `IApiRouteImport` adapter |
| `tests/Fixtures` | | RPC procedures, routing file, translations, a second adapter, a `rpc.post_response` recorder |

`tests/TestKernel.php` boots FrameworkBundle, SecurityBundle, JsonRpcBundle and this bundle. Its cache lives in `<tmp>/ufo-rest-tests/<hash of the security config>`; a test sets `TestKernel::$security` before `bootKernel()` to get another container.

## Notes

- If a test sees an old container after fixtures were edited within the same second, remove `<tmp>/ufo-rest-tests` (`docker exec php_rest rm -rf /tmp/ufo-rest-tests`).
- Functional tests extend `Ufo\RestBundleTest\Functional\FunctionalTestCase` (`send()`, `json()`), which restores the security config and the exception handler after each test.
- Add a functional test for any change of routes, statuses, problem documents or documents: they are the REST contract.
