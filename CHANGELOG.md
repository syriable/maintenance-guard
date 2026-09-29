# Changelog

All notable changes to `syriable/maintenance-guard` will be documented in this file.

## Unreleased

### Added

- `PreventRequestsDuringMaintenance` middleware that extends Laravel's native middleware and is swapped in automatically through the container.
- URI pattern exceptions with optional per-pattern HTTP method constraints.
- Named route exceptions with wildcard support and optional HTTP method constraints.
- Conditional rules via closures, `Rule` instances or container-resolved `Rule` classes (fail closed on exceptions).
- `forget()`, `forgetRoutes()`, `flush()`, `enable()` and `disable()` for managing registrations.
- Optional Livewire integration that only allows component updates originating from allowed pages.
- `config/maintenance-guard.php` with `enabled`, `except`, `routes`, `rules` and `livewire` options.
