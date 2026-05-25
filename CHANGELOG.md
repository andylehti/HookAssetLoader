# Changelog

## 1.0.1

- Switched permission protection from `getUserPermissionsErrorsExpensive` to `getUserPermissionsErrors`.
- Added strict `Title` instance checking before inspecting controlled MediaWiki namespace pages.
- Added structured logging for missing or invalid registry pages.
- Added `i18n/qqq.json` message documentation.
- Added mediawiki.org extension-page and help-page drafts under `docs/`.

## 1.0.0

- Initial release.
- Added `<hook-load module="name">` and `<hookasset name="name">` parser tags.
- Added JSON registry approval through `MediaWiki:HookAssetLoader.json`.
- Added ResourceLoader-backed CSS and JavaScript loading from `MediaWiki:HookAsset-name.css` and `MediaWiki:HookAsset-name.js`.
- Added `hookassetloader-manage` right for controlled asset-page management.
