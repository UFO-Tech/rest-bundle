[English](../en/security.md) · [Зміст](../README-UA.md)

# Безпека

Бандл використовує безпеку JsonRpcBundle (`ufo_json_rpc.security`); окремої REST-конфігурації немає.

```yaml
ufo_json_rpc:
    security:
        protected_api: true        # типово: виклики потребують токена
        protected_doc: false       # типово: документи публічні
        token_name: 'Ufo-RPC-Token'
        clients_tokens:
            - '%env(API_TOKEN)%'
        providers:
            partners:
                api_groups: [partners]
                clients_tokens: ['%env(PARTNERS_TOKEN)%']
            internal:
                api_groups: [internal]
                service: App\Security\InternalTokenValidator   # реалізує ITokenValidator
```

| Запит | Перевірка | Помилка |
| --- | --- | --- |
| REST-виклик | `protected_api`: заголовок токена `token_name` перевіряє провайдер API-групи виклику або `clients_tokens` для груп без провайдера | `401` без токена або з неправильним |
| OpenAPI / Postman | `protected_doc`: та сама перевірка токена для групи документа | `401` |

- Група береться з маршруту (`defaults.api_group` імпорту), тож токен однієї групи не відкриває вхід іншої.
- Провайдер має або `clients_tokens`, або `service`, що реалізує `Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator`.
- RPC повертає `403` для неправильного токена; REST повертає `401` і для відсутнього, і для неправильного токена.
- Firewall-и Symfony Security діють на REST-шляхи так само, як на будь-які інші маршрути.
