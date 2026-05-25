<?php

namespace MediaWiki\Extension\HookAssetLoader;

use ContentHandler;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\ResourceLoader;
use Parser;
use PPFrame;
use Title;

class Hooks {
	private const assetPattern = '/^[a-z][a-z0-9-]{0,63}$/';
	private const modulePattern = '/^[a-zA-Z0-9_.-]+$/';
	private const controlledActionMap = [
		'edit' => true,
		'create' => true,
		'delete' => true,
		'move' => true,
		'protect' => true,
		'submit' => true
	];

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'hook-load', [ self::class, 'renderHookAsset' ] );
		$parser->setHook( 'hookasset', [ self::class, 'renderHookAsset' ] );
		return true;
	}

	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		$assetList = self::getAssetList();
		$assetPrefix = self::getAssetPrefix();
		$moduleList = [];

		foreach ( $assetList as $assetName => $assetData ) {
			if ( empty( $assetData['enabled'] ) ) {
				continue;
			}

			if ( self::shouldLoadStyles( $assetData ) ) {
				$moduleList[self::makeModuleName( $assetName, 'styles' )] = [
					'class' => HookAssetModule::class,
					'assetName' => $assetName,
					'assetKind' => 'styles',
					'assetPrefix' => $assetPrefix,
					'position' => 'top'
				];
			}

			if ( self::shouldLoadScripts( $assetData ) ) {
				$moduleList[self::makeModuleName( $assetName, 'scripts' )] = [
					'class' => HookAssetModule::class,
					'assetName' => $assetName,
					'assetKind' => 'scripts',
					'assetPrefix' => $assetPrefix,
					'dependencies' => self::getDependencies( $assetData )
				];
			}
		}

		if ( $moduleList ) {
			$resourceLoader->register( $moduleList );
		}

		return true;
	}

	public static function renderHookAsset( $input, array $args, Parser $parser, PPFrame $frame ) {
		$assetName = self::normalizeAssetName( $args['module'] ?? $args['name'] ?? '' );
		$output = $parser->getOutput();

		if ( $assetName === '' ) {
			return self::errorNode( 'hookassetloader-error-missing' );
		}

		$assetList = self::getAssetList();
		$assetData = $assetList[$assetName] ?? null;

		self::addPageDependency( $output, self::makeRegistryTitle() );

		if ( !$assetData || empty( $assetData['enabled'] ) ) {
			return self::errorNode( 'hookassetloader-error-unknown', [ $assetName ] );
		}

		$hasAsset = false;
		$styleTitle = self::makeAssetTitle( $assetName, 'css' );
		$scriptTitle = self::makeAssetTitle( $assetName, 'js' );

		if ( self::shouldLoadStyles( $assetData ) && $styleTitle && $styleTitle->exists() ) {
			$output->addModuleStyles( [ self::makeModuleName( $assetName, 'styles' ) ] );
			self::addPageDependency( $output, $styleTitle );
			$hasAsset = true;
		}

		if ( self::shouldLoadScripts( $assetData ) && $scriptTitle && $scriptTitle->exists() ) {
			$output->addModules( [ self::makeModuleName( $assetName, 'scripts' ) ] );
			self::addPageDependency( $output, $scriptTitle );
			$hasAsset = true;
		}

		if ( !$hasAsset ) {
			return self::errorNode( 'hookassetloader-error-empty', [ $assetName ] );
		}

		$className = 'hookasset-container hookasset-' . htmlspecialchars( $assetName, ENT_QUOTES );
		$dataName = htmlspecialchars( $assetName, ENT_QUOTES );
		$content = $input !== null && $input !== '' ? $parser->recursiveTagParse( $input, $frame ) : '';

		return '<div class="' . $className . '" data-hook-asset="' . $dataName . '">' . $content . '</div>';
	}

	public static function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( !isset( self::controlledActionMap[$action] ) ) {
			return true;
		}

		if ( !self::isControlledTitle( $title ) ) {
			return true;
		}

		if ( method_exists( $user, 'isAllowed' ) && $user->isAllowed( 'hookassetloader-manage' ) ) {
			return true;
		}

		$result = [ 'hookassetloader-permission-denied' ];
		return false;
	}

	public static function isValidAssetName( string $assetName ): bool {
		return (bool)preg_match( self::assetPattern, $assetName );
	}

	private static function getAssetList(): array {
		$title = self::makeRegistryTitle();

		if ( !$title || !$title->exists() ) {
			self::debugLog( 'Registry page is missing', [ 'page' => self::getRegistryPage() ] );
			return [];
		}

		$services = MediaWikiServices::getInstance();
		$page = $services->getWikiPageFactory()->newFromTitle( $title );
		$content = $page ? $page->getContent() : null;
		$text = $content ? ContentHandler::getContentText( $content ) : '';
		$data = json_decode( $text, true );

		if ( !is_array( $data ) ) {
			self::warningLog( 'Registry page contains invalid JSON', [ 'page' => self::getRegistryPage() ] );
			return [];
		}

		$rawAssets = is_array( $data['assets'] ?? null ) ? $data['assets'] : $data;
		$assetList = [];

		foreach ( $rawAssets as $assetName => $assetData ) {
			$assetName = self::normalizeAssetName( $assetName );

			if ( $assetName === '' || !is_array( $assetData ) ) {
				continue;
			}

			$assetList[$assetName] = [
				'enabled' => (bool)( $assetData['enabled'] ?? true ),
				'styles' => (bool)( $assetData['styles'] ?? true ),
				'scripts' => (bool)( $assetData['scripts'] ?? true ),
				'dependencies' => is_array( $assetData['dependencies'] ?? null ) ? $assetData['dependencies'] : null
			];
		}

		return $assetList;
	}

	private static function shouldLoadStyles( array $assetData ): bool {
		return !empty( $assetData['styles'] );
	}

	private static function shouldLoadScripts( array $assetData ): bool {
		return !empty( $assetData['scripts'] );
	}

	private static function getDependencies( array $assetData ): array {
		$dependencies = $assetData['dependencies'];

		if ( !is_array( $dependencies ) ) {
			$dependencies = self::getConfigArray( 'HookAssetLoaderDefaultDependencies' );
		}

		$cleanList = [];

		foreach ( $dependencies as $moduleName ) {
			if ( is_string( $moduleName ) && preg_match( self::modulePattern, $moduleName ) ) {
				$cleanList[] = $moduleName;
			}
		}

		return array_values( array_unique( $cleanList ) );
	}

	private static function makeModuleName( string $assetName, string $assetKind ): string {
		return 'ext.hookAssetLoader.' . $assetName . '.' . $assetKind;
	}

	private static function normalizeAssetName( $assetName ): string {
		if ( !is_string( $assetName ) ) {
			return '';
		}

		$assetName = strtolower( trim( $assetName ) );

		return self::isValidAssetName( $assetName ) ? $assetName : '';
	}

	private static function makeAssetTitle( string $assetName, string $extension ) {
		if ( !self::isValidAssetName( $assetName ) || !in_array( $extension, [ 'css', 'js' ], true ) ) {
			return null;
		}

		return Title::makeTitleSafe( NS_MEDIAWIKI, self::getAssetPrefix() . $assetName . '.' . $extension );
	}

	private static function makeRegistryTitle() {
		return Title::makeTitleSafe( NS_MEDIAWIKI, self::getRegistryPage() );
	}

	private static function getRegistryPage(): string {
		$value = self::getConfigValue( 'HookAssetLoaderRegistryPage', 'HookAssetLoader.json' );
		$value = is_string( $value ) ? trim( $value ) : 'HookAssetLoader.json';

		if ( !preg_match( '/^HookAssetLoader(?:-[a-z0-9-]+)?\.json$/', $value ) ) {
			return 'HookAssetLoader.json';
		}

		return $value;
	}

	private static function getAssetPrefix(): string {
		$value = self::getConfigValue( 'HookAssetLoaderAssetPrefix', 'HookAsset-' );
		$value = is_string( $value ) ? trim( $value ) : 'HookAsset-';

		if ( !preg_match( '/^[A-Za-z][A-Za-z0-9-]{0,31}$/', rtrim( $value, '-' ) ) && $value !== 'HookAsset-' ) {
			return 'HookAsset-';
		}

		return $value;
	}

	private static function getConfigValue( string $name, $fallback ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		return $config->has( $name ) ? $config->get( $name ) : $fallback;
	}

	private static function getConfigArray( string $name ): array {
		$value = self::getConfigValue( $name, [] );
		return is_array( $value ) ? $value : [];
	}

	private static function isControlledTitle( $title ): bool {
		if ( !( $title instanceof Title ) || $title->getNamespace() !== NS_MEDIAWIKI ) {
			return false;
		}

		$text = $title->getText();
		$prefix = preg_quote( self::getAssetPrefix(), '/' );
		$registry = preg_quote( self::getRegistryPage(), '/' );

		return (bool)preg_match( '/^(?:' . $registry . '|' . $prefix . '[a-z][a-z0-9-]{0,63}\.(?:css|js))$/', $text );
	}

	private static function addPageDependency( $output, $title ): void {
		if ( !$output || !$title || !$title->exists() || !method_exists( $output, 'addTemplate' ) ) {
			return;
		}

		$pageId = method_exists( $title, 'getArticleID' ) ? (int)$title->getArticleID() : 0;
		$revId = method_exists( $title, 'getLatestRevID' ) ? (int)$title->getLatestRevID() : 0;

		if ( $pageId > 0 ) {
			$output->addTemplate( $title, $pageId, $revId );
		}
	}

	private static function errorNode( string $messageKey, array $params = [] ): string {
		if ( !self::getConfigValue( 'HookAssetLoaderDebugErrors', true ) ) {
			return '';
		}

		$message = wfMessage( $messageKey, $params )->inContentLanguage()->text();
		return '<span class="error hookasset-error">' . htmlspecialchars( $message, ENT_QUOTES ) . '</span>';
	}

	private static function debugLog( string $message, array $context = [] ): void {
		LoggerFactory::getInstance( 'HookAssetLoader' )->debug( $message, $context );
	}

	private static function warningLog( string $message, array $context = [] ): void {
		LoggerFactory::getInstance( 'HookAssetLoader' )->warning( $message, $context );
	}
}
