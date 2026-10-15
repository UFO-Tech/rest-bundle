# Repository guidance

## Project overview

- Composer package: `ufo-tech/rest-bundle`, a reusable Symfony bundle, not a standalone application.
- Requires PHP `^8.4`, Symfony components `^7.2`, and `ufo-tech/json-rpc-bundle` `^10.2`. Development uses PHPUnit `^11.0`; Docker uses PHP 8.4.
- Production namespace: `Ufo\RestBundle\` in `src/`. Test namespace: `Ufo\RestBundleTest\` in `tests/`. Both mappings are declared in Composer's `autoload` section.
- Some README headings, package names, and badges refer to older projects. Use `composer.json` and current source as the authority for package identity and behavior.

## Code map

- `src/RestBundleBundle.php`: bundle entry class.
- `src/DependencyInjection/RestBundleExtension.php`: loads service configuration and reads the consuming application's JSON-RPC route prefix.
- `config/services.yaml`: autowiring, autoconfiguration, route-loader tagging, and explicit service-map dependencies.
- `config/router.yaml`: controller route import and the custom `ufo_rest_api` route loader.
- `src/Routing/RestYamlFileLoader.php`: imports routes per API group, prefixes names, and preserves legacy general aliases; delegates RPC imports to its upstream loader.
- `install/routes/ufo_rest_api.yaml`: route-import template for consuming applications; supplies the `/rest` prefix.
- `src/Services/RestServiceMap.php`: derives REST routes from RPC services with Symfony `#[Route]` attributes, combines class/method metadata, and handles version inheritance and removed methods.
- `src/Services/RpcRestRouteLoader.php`: creates Symfony routes, including version prefixes and route-name suffixes.
- `src/EventDispatcher/RestControllerListener.php`: directs matched REST routes to the controller and sets RPC route/version attributes.
- `src/Services/RestRequestParser.php`: resolves routes/services, checks HTTP methods, and initializes RPC security and request state.
- `src/Services/RpcFromRest.php`: builds the RPC request; parameters merge in order from route parameters, query, form data, then JSON body, with later string keys overriding earlier ones.
- `src/Controller/RestEntryPointController.php`: dispatches RPC calls, returns REST responses/errors, and exposes documentation endpoints.
- `src/Exceptions/`: REST exception types and RPC-to-HTTP error normalization.
- `src/DocAdapters/Outputs/`: OpenAPI and Postman adapters and specification builders.

## Development and validation

Run commands from the repository root:

```sh
composer install
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpunit -c phpunit.xml.dist --filter NameOfTest
php -l src/path/to/ChangedFile.php
git diff --check
```

- `phpunit.xml.dist` bootstraps `vendor/autoload.php` and discovers tests under `tests/`.
- `tests/Functional/RestApiTest.php` exercises real HTTP requests through a kernel with FrameworkBundle, SecurityBundle, JsonRpcBundle, and this bundle. Fixtures cover general/internal/public groups, repeated imports, version inheritance, documentation, and token providers.
- Add focused PHPUnit coverage for behavior changes. The test kernel stores its application/cache/log paths in the system temporary directory; it does not require a consuming application or a JSON-RPC routes file.
- No Composer scripts, formatter, static-analysis configuration, or CI workflow are provided. Do not invent required lint/build commands.
- Native PHP is unavailable on the host; the existing `php_rest` container provides PHP 8.4 and Composer. Run `docker exec php_rest vendor/bin/phpunit -c phpunit.xml.dist` when using that container. `cebe/php-openapi` currently emits PHP 8.4 nullable-parameter deprecations.

Docker workflow:

- `make setup` copies `.env.dist` to `.env` if absent. Review local environment values, including `PROJECT_NAME` and `WORKDIR`, before starting containers.
- `make up-d` starts the PHP service; the Makefile calls the legacy `docker-compose` executable. `make composer-install` installs dependencies in the running container.
- Container name: `php_${PROJECT_NAME}`. With the environment value substituted, run `docker exec php_<project-name> vendor/bin/phpunit -c phpunit.xml.dist` for tests.
- Compose mounts several sibling UFO-Tech repositories. Check those mounts and local configuration when diagnosing Docker setup problems.
- This repository has no standalone `bin/console`; the Makefile's `console` target assumes an application context.

## Change conventions

- Match adjacent PHP code: four-space indentation, PSR-4 class/file names, typed properties and signatures, constructor promotion, and Symfony attributes where already used. Avoid unrelated formatting or line-ending changes.
- Preserve the REST/RPC contract when changing routes: route names, HTTP methods, version attributes, inheritance/removal behavior, parameter precedence, authentication, and error status/payloads all affect consumers.
- Keep generated OpenAPI/Postman descriptions consistent with runtime behavior when changing request, routing, or response semantics.
- Update both `README.md` and `README-UA.md` for user-facing documentation changes.
- Do not edit generated dependencies in `vendor/`. `.env`, `vendor/`, `var/`, and `composer.lock` are ignored; do not force-add them as part of routine changes.
- Avoid `make commit-a` unless explicitly requested: it stages everything, amends the last commit, force-pushes, and can delete/recreate remote tags.
