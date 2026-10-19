[Українська](../uk/security.md) · [Contents](../README.md)

# Security

The bundle uses the security of JsonRpcBundle (`ufo_json_rpc.security`); there is no separate REST configuration.

```yaml
ufo_json_rpc:
    security:
        protected_api: true        # default: calls need a token
        protected_doc: false       # default: documents are public
        token_name: 'Ufo-RPC-Token'
        clients_tokens:
            - '%env(API_TOKEN)%'
        providers:
            partners:
                api_groups: [partners]
                clients_tokens: ['%env(PARTNERS_TOKEN)%']
            internal:
                api_groups: [internal]
                service: App\Security\InternalTokenValidator   # implements ITokenValidator
```

| Request | Check | Error |
| --- | --- | --- |
| REST call | `protected_api`: the token header `token_name` is validated by the provider of the call's API group, or by `clients_tokens` for groups without a provider | `401` without a token or with a wrong one |
| OpenAPI / Postman | `protected_doc`: the same token check for the document's group | `401` |

- The group is taken from the route (`defaults.api_group` of the import), so a token of one group does not open another group's entry point.
- A provider has either `clients_tokens` or a `service` implementing `Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator`.
- RPC returns `403` for an invalid token; REST returns `401` for both a missing and an invalid token.
- Symfony Security firewalls apply to the REST paths as to any other route.
