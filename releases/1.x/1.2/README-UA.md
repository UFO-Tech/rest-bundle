[English](README.md)

# 1.2 — Symfony 8 і core Postman-адаптер

Теги: 1.2.0. Перший реліз: 2026-10-16.

## Головне

- **Symfony 8**: `symfony/event-dispatcher`, `framework-bundle` і `http-kernel` приймають `^7.2|^8.0`.
- **Postman-колекція** будується на `AbstractPostmanAdapter` з JsonRpcBundle: колекція називається `REST: <назва проєкту>`, з ` [group]` для нетипової API-групи — так само, як RPC-колекція.
- **`composer test`** і **`make test`** запускають PHPUnit.

## Нотатки з оновлення

- Потрібен `ufo-tech/json-rpc-bundle` ^10.2.3.
- Інструменти, що шукають Postman-колекцію за назвою, побачать нову назву.
