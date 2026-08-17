## [1.1.0] - 2026-08-17

- Record a completed connection from the client, confirmed against the vault, so a connection no longer depends on the vault's webhook reaching the host
- Report vault failures in language the user can act on, and say so plainly when a provider was never set up in the vault
- Honour the provider key configured for a provider instead of whatever the caller sent
- Guard the table, the model and the declared schema against drift in the test suite

## [1.0.0] - 2026-05-08
- Initial release
