<?php

namespace MediaWiki\Extension\HookAssetLoader;

use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\WikiModule;

class HookAssetModule extends WikiModule {
	private $assetName = '';
	private $assetKind = 'styles';
	private $assetPrefix = 'HookAsset-';

	public function __construct( ?array $options = null ) {
		$options = $options ?? [];
		$this->assetName = is_string( $options['assetName'] ?? null ) ? $options['assetName'] : '';
		$this->assetKind = is_string( $options['assetKind'] ?? null ) ? $options['assetKind'] : 'styles';
		$this->assetPrefix = is_string( $options['assetPrefix'] ?? null ) ? $options['assetPrefix'] : 'HookAsset-';
		parent::__construct( $options );
	}

	protected function getPages( Context $context ) {
		if ( !Hooks::isValidAssetName( $this->assetName ) ) {
			return [];
		}

		$pageBase = 'MediaWiki:' . $this->assetPrefix . $this->assetName;

		if ( $this->assetKind === 'scripts' ) {
			return [
				$pageBase . '.js' => [
					'type' => 'script'
				]
			];
		}

		return [
			$pageBase . '.css' => [
				'type' => 'style',
				'media' => 'screen'
			]
		];
	}
}
