<?php

use Wikimedia\Composer\ComposerLock;

class ComposerLockTest extends PHPUnit\Framework\TestCase {

	private $lock;

	protected function setUp(): void {
		parent::setUp();
		$this->lock = __DIR__ . "/../../../../data/composer/composer.lock";
	}

	/**
	 * @covers Wikimedia\Composer\ComposerLock::__construct
	 * @covers Wikimedia\Composer\ComposerLock::getInstalledDependencies
	 */
	public function testGetInstalledDependencies() {
		$lock = new ComposerLock( $this->lock );
		$this->assertEquals( [
			'wikimedia/cdb' => [
				'version' => '1.0.1',
				'type' => 'library',
				'licenses' => [ 'GPL-2.0-only' ],
				'authors' => [
					[
						'name' => 'Tim Starling',
						'email' => 'tstarling@wikimedia.org',
					],
					[
						'name' => 'Chad Horohoe',
						'email' => 'chad@wikimedia.org',
					],
				],
				'description' => 'Constant Database (CDB) wrapper library for PHP. ' .
					'Provides pure-PHP fallback when dba_* functions are absent.',
			],
			'cssjanus/cssjanus' => [
				'version' => '1.1.1',
				'type' => 'library',
				'licenses' => [ 'Apache-2.0' ],
				'authors' => [],
				'description' => 'Convert CSS stylesheets between left-to-right and right-to-left.',
			],
			'leafo/lessphp' => [
				'version' => '0.5.0',
				'type' => 'library',
				'licenses' => [ 'MIT', 'GPL-3.0-only' ],
				'authors' => [
					[
						'name' => 'Leaf Corcoran',
						'email' => 'leafot@gmail.com',
						'homepage' => 'http://leafo.net',
					],
				],
				'description' => 'lessphp is a compiler for LESS written in PHP.',
			],
			'psr/log' => [
				'version' => '1.0.0',
				'type' => 'library',
				'licenses' => [ 'MIT' ],
				'authors' => [
					[
						'name' => 'PHP-FIG',
						'homepage' => 'http://www.php-fig.org/',
					],
				],
				'description' => 'Common interface for logging libraries',
			],
			'oojs/oojs-ui' => [
				'version' => '0.6.0',
				'type' => 'library',
				'licenses' => [ 'MIT' ],
				'authors' => [],
				'description' => '',
			],
			'composer/installers' => [
				'version' => '1.0.19',
				'type' => 'composer-installer',
				'licenses' => [ 'MIT' ],
				'authors' => [
					[
						'name' => 'Kyle Robinson Young',
						'email' => 'kyle@dontkry.com',
						'homepage' => 'https://github.com/shama',
					],
				],
				'description' => 'A multi-framework Composer library installer',
			],
			'mediawiki/translate' => [
				'version' => '2014.12',
				'type' => 'mediawiki-extension',
				'licenses' => [ 'GPL-2.0-or-later' ],
				'authors' => [
					[
						'name' => 'Niklas Laxström',
						'email' => 'niklas.laxstrom@gmail.com',
						'role' => 'Lead nitpicker',
					],
					[
						'name' => 'Siebrand Mazeland',
						'email' => 's.mazeland@xs4all.nl',
						'role' => 'Developer',
					],
				],
				'description' => 'The only standard solution to translate any kind ' .
					'of text with an avant-garde web interface within MediaWiki, ' .
					'including your documentation and software',
			],
			'mediawiki/universal-language-selector' => [
				'version' => '2014.12',
				'type' => 'mediawiki-extension',
				'licenses' => [ 'GPL-2.0-or-later', 'MIT' ],
				'authors' => [],
				'description' => 'The primary aim is to allow users to select a language ' .
					'and configure its support in an easy way. ' .
					'Main features are language selection, input methods and web fonts.',
			],
		], $lock->getInstalledDependencies() );
	}

}
