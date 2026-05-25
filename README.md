# HookAssetLoader

HookAssetLoader is a MediaWiki extension that loads administrator-approved JS/CSS assets from the `MediaWiki:` namespace only when a page explicitly requests them via a parser tag, keeping site styles clean and optimized.

Repository: https://github.com/andylehti/HookAssetLoader

## What it does

- Adds `<hook-load module="name">...</hook-load>`.
- Adds `<hookasset name="name">...</hookasset>` as an alias.
- Reads approved assets from `MediaWiki:HookAssetLoader.json`.
- Loads CSS from `MediaWiki:HookAsset-name.css` through ResourceLoader.
- Loads JS from `MediaWiki:HookAsset-name.js` through ResourceLoader.
- Allows only lowercase letters, numbers, and hyphens in asset names.
- Uses separate ResourceLoader modules for CSS and JS.
- Loads CSS with `addModuleStyles()` so CSS still applies without JavaScript.
- Loads JS with `addModules()` only when the matching JS page exists.
- Restricts controlled asset pages to users with `hookassetloader-manage`.

## Requirements

- MediaWiki 1.39 or later.
- No required extension dependencies.

## Installation

Copy the extension folder to:

```text
extensions/HookAssetLoader
```

Add this to `LocalSettings.php`:

```php
wfLoadExtension( 'HookAssetLoader' );
```

By default, the extension grants `hookassetloader-manage` to `interface-admin`.

If you want sysops to manage these assets too, add this manually:

```php
$wgGroupPermissions['sysop']['hookassetloader-manage'] = true;
```

Granting this right does not bypass MediaWiki's own CSS/JS page rights. Users still need the relevant MediaWiki rights for editing sitewide CSS, JavaScript, and JSON pages.

## Configuration

```php
$wgHookAssetLoaderRegistryPage = 'HookAssetLoader.json';
$wgHookAssetLoaderAssetPrefix = 'HookAsset-';
$wgHookAssetLoaderDefaultDependencies = [ 'mediawiki.util', 'jquery' ];
$wgHookAssetLoaderDebugErrors = true;
```

| Setting | Default | Purpose |
|---|---|---|
| `$wgHookAssetLoaderRegistryPage` | `HookAssetLoader.json` | MediaWiki namespace page used as the approved asset registry. |
| `$wgHookAssetLoaderAssetPrefix` | `HookAsset-` | Prefix used for controlled CSS and JavaScript pages. |
| `$wgHookAssetLoaderDefaultDependencies` | `[ 'mediawiki.util', 'jquery' ]` | Default ResourceLoader dependencies for JavaScript assets. |
| `$wgHookAssetLoaderDebugErrors` | `true` | Shows inline parser-tag errors when an asset request is invalid, unapproved, or empty. |

## Registry page

Create:

```text
MediaWiki:HookAssetLoader.json
```

Example:

```json
{
	"assets": {
		"clean-wide": {
			"enabled": true,
			"styles": true,
			"scripts": false
		},
		"floating-toc": {
			"enabled": true,
			"styles": true,
			"scripts": true,
			"dependencies": [
				"mediawiki.util",
				"jquery"
			]
		}
	}
}
```

Only assets listed here can be loaded.

## Asset pages

For `clean-wide`, create one or both of these pages:

```text
MediaWiki:HookAsset-clean-wide.css
MediaWiki:HookAsset-clean-wide.js
```

For `floating-toc`, create one or both of these pages:

```text
MediaWiki:HookAsset-floating-toc.css
MediaWiki:HookAsset-floating-toc.js
```

## Page usage

```html
<hook-load module="clean-wide"></hook-load>
```

With wrapped content:

```html
<hook-load module="sticky-note">
Please remember to sign posts using four tildes.
</hook-load>
```

Alias syntax:

```html
<hookasset name="clean-wide"></hookasset>
```

## Example CSS asset

Page:

```text
MediaWiki:HookAsset-clean-wide.css
```

```css
body:has(.hookasset-clean-wide) .mw-page-container {
	max-width: none;
}

body:has(.hookasset-clean-wide) .vector-column-start {
	display: none;
}

.hookasset-clean-wide {
	display: contents;
}
```

## Example JS asset

Page:

```text
MediaWiki:HookAsset-sticky-note.js
```

```javascript
( function () {
	'use strict';

	$( function () {
		$( '.hookasset-container[data-hook-asset="sticky-note"]' ).each( function () {
			const node = $( this );

			if ( node.data( 'hookLoaded' ) ) {
				return;
			}

			node.data( 'hookLoaded', true );

			const text = node.text().trim() || 'Default note text.';
			node.empty().append(
				$( '<div>' ).addClass( 'sticky-box' ).append(
					$( '<strong>' ).text( 'Notice: ' ),
					$( '<span>' ).text( text )
				)
			);
		} );
	} );
}() );
```

## Security model

Asset names are restricted to this pattern:

```text
^[a-z][a-z0-9-]{0,63}$
```

That means no slashes, dots, spaces, colons, URL fragments, query strings, traversal paths, or arbitrary title injection can be used from the parser tag.

The parser tag never accepts raw script or raw CSS. It only asks MediaWiki to load ResourceLoader modules generated from approved `MediaWiki:` namespace pages.

The extension controls these pages:

```text
MediaWiki:HookAssetLoader.json
MediaWiki:HookAsset-*.css
MediaWiki:HookAsset-*.js
```

Users need the `hookassetloader-manage` right to edit, create, delete, move, protect, or submit those pages.

## Similar extensions

- `Gadgets` loads user-selectable or default gadgets. HookAssetLoader is parser-tag driven and registry-controlled.
- `TemplateStyles` loads sanitized template CSS. HookAssetLoader supports both JavaScript and CSS through ResourceLoader.
- `CSS` allows page-level CSS. HookAssetLoader adds explicit registry approval and JavaScript support.

## Notes

Changing an asset page updates the ResourceLoader version for that asset. Pages using the parser tag also register parser-output dependencies on the registry and asset pages when they exist.

If `MediaWiki:HookAssetLoader.json` is missing or invalid JSON, no assets are approved.

If an approved asset has no matching `.css` or `.js` page, the tag returns an inline error unless `$wgHookAssetLoaderDebugErrors` is set to `false`.
