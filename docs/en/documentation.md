[Українська](../uk/documentation.md) · [Contents](../README.md)

# API documentation

Every REST entry point publishes two documents per API version. They contain only the methods of its API group and version and use the URL of the entry point.

| URL | Document |
| --- | --- |
| `GET <prefix>` | OpenAPI 3.1 |
| `GET <prefix>/postman` | Postman collection v2.1 |
| `GET <prefix>/v2`, `GET <prefix>/v2/postman` | the same for version `v2` |

## OpenAPI

| Element | Source |
| --- | --- |
| `info.title`, `info.description` | `ufo_json_rpc.docs.project_name`, `project_description` |
| `info.version` | the requested API version |
| `info.x-versions` | all versions of the group |
| `servers[0].url` | the document URL of the current request |
| `servers[0].x-ufo` | envelope (`REST (RFC 9110, 9457)/UFO-REST-<version>`), package versions, protocol links |
| `paths.<path>.<method>` | one operation per REST route |
| `operationId` | RPC method name (`catalog.item`) |
| `summary` | translated title; without a title the description; without both the method name |
| `description` | translated description, when there is a title |
| `tags` | RPC procedure alias |
| `deprecated` | `@deprecated` or `#[\Deprecated]` on the method |
| `parameters` | path variables (`in: path`, required) with the schema of the method parameter |
| `requestBody` | the other method parameters as a JSON object schema: required parameters, defaults, descriptions, types, enums and DTO references |
| `responses.200` | return type schema |
| `responses.<status>` | error statuses from `@throws`, see [Errors](errors.md) |
| `components.schemas` | DTOs and enums used by the operations |

## Postman

- The collection is named `REST: <project name>`, with ` [group]` for a non-default group.
- Variables `base_url` (the host of the current request) and `apiToken`.
- Items are grouped in folders by RPC procedure; an item is named by its path.
- Each request has the HTTP method of the route, the URL with Postman path variables (`/items/:id`), the headers `Content-Type` and the token header, and a raw JSON body with example values of the parameters except path variables.

## Translations

Method titles and descriptions come from the RPC translations of JsonRpcBundle 10.4:

```
translations/rpc.<locale>.yaml          # base, all groups
translations/rpc/<group>.<locale>.yaml  # override for one API group
```

```yaml
# translations/rpc.uk.yaml
catalog.item:
    title: Товар
    description: Повертає товар за ідентифікатором
```

Extension bundles add directories with `ufo_json_rpc.translations.paths`. A field is looked up in this order: group + client locale, group + default locale, base + client locale, base + default locale, `#[RPC\Info]` / PHPDoc.

`RestDocLocaleListener` picks the locale from `Accept-Language` among the locales that have translations (`uk-UA` matches `uk`; without a match — the first one, the default locale if it has translations) and adds `Content-Language` and `Vary: Accept-Language` to the document response.

## Access

With `ufo_json_rpc.security.protected_doc: true` the documents require a valid token of the group, see [Security](security.md).

## Limitations

- The documents are generated on every request; there is no `ETag` cache as for the RPC documentation.
- Postman requests of `GET` methods also contain a JSON body with the parameters.
