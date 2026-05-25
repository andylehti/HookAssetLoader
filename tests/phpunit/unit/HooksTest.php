<?php

namespace MediaWiki\Extension\HookAssetLoader\Tests\Unit;

use MediaWiki\Extension\HookAssetLoader\Hooks;
use MediaWikiUnitTestCase;

class HooksTest extends MediaWikiUnitTestCase {
	public function testValidAssetNames(): void {
		$this->assertTrue( Hooks::isValidAssetName( 'clean-wide' ) );
		$this->assertTrue( Hooks::isValidAssetName( 'asset1' ) );
		$this->assertTrue( Hooks::isValidAssetName( 'a' ) );
	}

	public function testInvalidAssetNames(): void {
		$this->assertFalse( Hooks::isValidAssetName( '' ) );
		$this->assertFalse( Hooks::isValidAssetName( 'CleanWide' ) );
		$this->assertFalse( Hooks::isValidAssetName( 'clean_wide' ) );
		$this->assertFalse( Hooks::isValidAssetName( 'mediawiki:common.js' ) );
		$this->assertFalse( Hooks::isValidAssetName( '../common' ) );
	}
}
