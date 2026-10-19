[Українська](README-UA.md)

# 1.2 — Symfony 8 and the core Postman adapter

Tags: 1.2.0. First released: 2026-10-16.

## Highlights

- **Symfony 8**: `symfony/event-dispatcher`, `framework-bundle` and `http-kernel` accept `^7.2|^8.0`.
- **Postman collection** is built on JsonRpcBundle's `AbstractPostmanAdapter`: the collection is named `REST: <project name>`, with ` [group]` for a non-default API group, the same way as the RPC collection.
- **`composer test`** and **`make test`** run PHPUnit.

## Upgrade notes

- `ufo-tech/json-rpc-bundle` ^10.2.3 is required.
- Tools that look up the Postman collection by its name see the new name.
