<?php

namespace MediaWiki\Tests\Integration\User\TempUser;

use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\TempUser\Pattern;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\User\TempUser\RealTempUserConfig
 * @group Database
 */
class RealTempUserConfigTest extends \MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	public static function provideIsAutoCreateAction() {
		return [
			'disabled' => [
				'enabled' => false,
				'configOverrides' => [],
				'action' => 'edit',
				'expected' => false
			],
			'disabled by action' => [
				'enabled' => true,
				'configOverrides' => [ 'actions' => [] ],
				'action' => 'edit',
				'expected' => false
			],
			'enabled' => [
				'enabled' => true,
				'configOverrides' => [],
				'action' => 'edit',
				'expected' => true
			],
			// Create isn't an action in the ActionFactory sense, but is is an
			// action in PermissionManager
			'create' => [
				'enabled' => true,
				'configOverrides' => [],
				'action' => 'create',
				'expected' => true
			],
			'unknown action' => [
				'enabled' => true,
				'configOverrides' => [],
				'action' => 'foo',
				'expected' => false
			],
		];
	}

	/**
	 * @dataProvider provideIsAutoCreateAction
	 * @param bool $enabled
	 * @param array $configOverrides
	 * @param string $action
	 * @param bool $expected
	 */
	public function testIsAutoCreateAction( $enabled, $configOverrides, $action, $expected ) {
		if ( $enabled ) {
			$this->enableAutoCreateTempUser( $configOverrides );
		} else {
			$this->disableAutoCreateTempUser( $configOverrides['reservedPattern'] ?? null );
		}
		$tuc = $this->getServiceContainer()->getTempUserConfig();
		$this->assertSame( $expected, $tuc->isAutoCreateAction( $action ) );
	}

	public static function provideShouldAutoCreate() {
		return [
			'enabled' => [
				'enabled' => true,
				'id' => 0,
				'rights' => [ 'createaccount' ],
				'action' => 'edit',
				'expected' => true
			],
			'disabled by config' => [
				'enabled' => false,
				'id' => 0,
				'rights' => [ 'createaccount' ],
				'action' => 'edit',
				'expected' => false
			],
			'logged in' => [
				'enabled' => true,
				'id' => 1,
				'rights' => [ 'createaccount' ],
				'action' => 'edit',
				'expected' => false
			],
			'no createaccount right' => [
				'enabled' => true,
				'id' => 0,
				'rights' => [ 'edit' ],
				'action' => 'edit',
				'expected' => false
			],
			'wrong action' => [
				'enabled' => true,
				'id' => 0,
				'rights' => [ 'createaccount' ],
				'action' => 'upload',
				'expected' => false
			],
		];
	}

	/**
	 * @dataProvider provideShouldAutoCreate
	 * @param bool $enabled
	 * @param int $id
	 * @param string[] $rights
	 * @param string $action
	 * @param bool $expected
	 */
	public function testShouldAutoCreate( $enabled, $id, $rights, $action, $expected ) {
		if ( $enabled ) {
			$this->enableAutoCreateTempUser();
		} else {
			$this->disableAutoCreateTempUser();
		}
		$tuc = $this->getServiceContainer()->getTempUserConfig();
		$user = new SimpleAuthority(
			new UserIdentityValue( $id, $id ? 'Test' : '127.0.0.1' ),
			$rights
		);
		$this->assertSame( $expected, $tuc->shouldAutoCreate( $user, $action ) );
	}

	public static function provideIsTempName() {
		return [
			'disabled' => [
				'enabled' => false,
				'name' => '*Some user',
				'expected' => false,
			],
			'default mismatch' => [
				'enabled' => true,
				'name' => 'Test',
				'expected' => false,
			],
			'default match' => [
				'enabled' => true,
				'name' => '*Some user',
				'expected' => true,
			]
		];
	}

	/**
	 * @dataProvider provideIsTempName
	 * @param bool $enabled
	 * @param string $name
	 * @param bool $expected
	 */
	public function testIsTempName( $enabled, $name, $expected ) {
		if ( $enabled ) {
			$this->enableAutoCreateTempUser();
		} else {
			$this->disableAutoCreateTempUser();
		}
		$tuc = $this->getServiceContainer()->getTempUserConfig();
		$this->assertSame( $expected, $tuc->isTempName( $name ) );
	}

	private function getTempUserConfig() {
		$this->enableAutoCreateTempUser();
		return $this->getServiceContainer()->getTempUserConfig();
	}

	public function testGetPlaceholderName() {
		$this->assertSame(
			'*Unregistered *',
			$this->getTempUserConfig()->getPlaceholderName()
		);
	}

	public static function provideIsReservedName() {
		return [
			'no matchPattern when disabled' => [
				'enabled' => false,
				'configOverrides' => [],
				'name' => '*Unregistered 39',
				'expected' => false,
			],
			'matchPattern match' => [
				'enabled' => true,
				'configOverrides' => [],
				'name' => '*Unregistered 39',
				'expected' => true,
			],
			'genPattern match' => [
				'enabled' => true,
				'configOverrides' => [ 'matchPattern' => null ],
				'name' => '*Unregistered 39',
				'expected' => true,
			],
			'reservedPattern match with enabled=false' => [
				'enabled' => false,
				'configOverrides' => [ 'reservedPattern' => '*$1' ],
				'name' => '*Foo*',
				'expected' => true
			]
		];
	}

	/**
	 * @dataProvider provideIsReservedName
	 * @param bool $enabled
	 * @param array $configOverrides
	 * @param string $name
	 * @param bool $expected
	 */
	public function testIsReservedName( $enabled, $configOverrides, $name, $expected ) {
		if ( $enabled ) {
			$this->enableAutoCreateTempUser( $configOverrides );
		} else {
			$this->disableAutoCreateTempUser( $configOverrides['reservedPattern'] ?? null );
		}
		$tuc = $this->getServiceContainer()->getTempUserConfig();
		$this->assertSame( $expected, $tuc->isReservedName( $name ) );
	}

	public function testGetMatchPatterns() {
		$this->enableAutoCreateTempUser( [ 'matchPattern' => [ '*$1', '~$1' ] ] );
		$tuc = $this->getServiceContainer()->getTempUserConfig();
		$this->assertCount( 2, $tuc->getMatchPatterns() );
		$actualPatterns = array_map( static function ( Pattern $pattern ) {
			return TestingAccessWrapper::newFromObject( $pattern )->pattern;
		}, $tuc->getMatchPatterns() );
		$this->assertArrayEquals( [ '*$1', '~$1' ], $actualPatterns );
	}

	public function testGetMatchPattern() {
		$this->expectDeprecation();
		$this->enableAutoCreateTempUser( [ 'matchPattern' => [ '*$1', '~$1' ] ] );
		$tuc = $this->getServiceContainer()->getTempUserConfig();
		$this->assertSame( '*$1', TestingAccessWrapper::newFromObject( $tuc->getMatchPattern() )->pattern );
	}
}
