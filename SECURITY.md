# Security Policy

HookAssetLoader executes administrator-approved JavaScript and CSS from the MediaWiki namespace. Treat access to `MediaWiki:HookAsset-*.js`, `MediaWiki:HookAsset-*.css`, and `MediaWiki:HookAssetLoader.json` as equivalent to site interface administration.

## Supported Versions

Security updates are provided for the current tagged release unless otherwise stated.

## Reporting Security Issues

Report security issues privately to the project maintainer before public disclosure.

Repository: https://github.com/andylehti/HookAssetLoader

## Security Model

- Asset names are restricted to lowercase letters, numbers, and hyphens.
- Assets must be approved in `MediaWiki:HookAssetLoader.json` before loading.
- Assets can only load from matching pages in the `MediaWiki:` namespace.
- The extension does not load external resources.
- The extension does not provide its own write API or write form.
- Page editing uses MediaWiki's normal edit permission and token systems.