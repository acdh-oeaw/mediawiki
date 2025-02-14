<?php

namespace MediaWiki\Tests\Rest\Handler;

use File;
use FileRepo;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\MainConfigSchema;
use MediaWiki\Parser\Parsoid\ParsoidOutputAccess;
use MediaWiki\Parser\Parsoid\ParsoidParser;
use MediaWiki\Parser\Parsoid\ParsoidParserFactory;
use MediaWiki\Rest\Handler\Helper\HtmlMessageOutputHelper;
use MediaWiki\Rest\Handler\Helper\HtmlOutputRendererHelper;
use MediaWiki\Rest\Handler\Helper\PageContentHelper;
use MediaWiki\Rest\Handler\Helper\PageRedirectHelper;
use MediaWiki\Rest\Handler\Helper\PageRestHelperFactory;
use MediaWiki\Rest\Handler\LanguageLinksHandler;
use MediaWiki\Rest\Handler\PageHistoryCountHandler;
use MediaWiki\Rest\Handler\PageHistoryHandler;
use MediaWiki\Rest\Handler\PageHTMLHandler;
use MediaWiki\Rest\Handler\PageSourceHandler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\ResponseFactory;
use MediaWiki\Rest\Router;
use PHPUnit\Framework\MockObject\MockObject;
use RepoGroup;
use WANObjectCache;
use Wikimedia\Parsoid\Parsoid;

/**
 * A trait providing utility functions for testing Page Handler classes.
 * This trait is intended to be used on subclasses of MediaWikiUnitTestCase
 * or MediaWikiIntegrationTestCase.
 *
 * @stable to use
 * @package MediaWiki\Tests\Rest\Handler
 */
trait PageHandlerTestTrait {

	private function newRouter( $baseUrl, $rootPath = '' ): Router {
		$router = $this->createNoOpMock( Router::class, [ 'getRoutePath', 'getRouteUrl' ] );
		$router->method( 'getRoutePath' )
			->willReturnCallback( static function (
				string $route,
				array $pathParams = [],
				array $queryParams = []
			) use ( $rootPath ) {
				foreach ( $pathParams as $param => $value ) {
					// NOTE: we use rawurlencode here, since execute() uses rawurldecode().
					// Spaces in path params must be encoded to %20 (not +).
					// Slashes must be encoded as %2F.
					$route = str_replace( '{' . $param . '}', rawurlencode( (string)$value ), $route );
				}

				$url = $rootPath . $route;
				return wfAppendQuery( $url, $queryParams );
			} );
		$router->method( 'getRouteUrl' )
			->willReturnCallback( static function (
				string $route,
				array $pathParams = [],
				array $queryParams = []
			) use ( $baseUrl, $router ) {
				return $baseUrl . $router->getRoutePath( $route, $pathParams, $queryParams );
			} );

		return $router;
	}

	/**
	 * @param Parsoid|MockObject $mockParsoid
	 */
	public function resetServicesWithMockedParsoid( $mockParsoid ): void {
		$services = $this->getServiceContainer();
		$parsoidParser = new ParsoidParser(
			$mockParsoid,
			$services->getParsoidPageConfigFactory(),
			$services->getLanguageConverterFactory(),
			$services->getParserFactory(),
			$services->getGlobalIdGenerator()
		);

		// Create a mock Parsoid factory that returns the ParsoidParser object
		// with the mocked Parsoid object.
		$mockParsoidParserFactory = $this->createNoOpMock( ParsoidParserFactory::class, [ 'create' ] );
		$mockParsoidParserFactory->method( 'create' )->willReturn( $parsoidParser );

		$this->setService( 'ParsoidParserFactory', $mockParsoidParserFactory );
	}

	/**
	 * @return PageHTMLHandler
	 */
	public function newPageHtmlHandler( ?RequestInterface $request = null ) {
		// ParserOutputAccess has a localCache which can return stale content.
		// Resetting ensures that ParsoidCachePrewarmJob gets a fresh copy
		// of ParserOutputAccess and ParsoidOutputAccess without these problems!
		$this->resetServices();

		$services = $this->getServiceContainer();
		$config = [
			'RightsUrl' => 'https://example.com/rights',
			'RightsText' => 'some rights',
			'ParsoidCacheConfig' =>
				MainConfigSchema::getDefaultValue( MainConfigNames::ParsoidCacheConfig )
		];

		$parsoidOutputAccess = new ParsoidOutputAccess(
			new ServiceOptions(
				ParsoidOutputAccess::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig(),
				[ 'ParsoidWikiID' => 'MyWiki' ]
			),
			$services->getParsoidParserFactory(),
			$services->getParserOutputAccess(),
			$services->getPageStore(),
			$services->getRevisionLookup(),
			$services->getParsoidSiteConfig(),
			$services->getContentHandlerFactory()
		);

		$helperFactory = $this->createNoOpMock(
			PageRestHelperFactory::class,
			[ 'newPageContentHelper', 'newHtmlOutputRendererHelper', 'newHtmlMessageOutputHelper', 'newPageRedirectHelper' ]
		);

		$helperFactory->method( 'newPageContentHelper' )
			->willReturn( new PageContentHelper(
				new ServiceOptions( PageContentHelper::CONSTRUCTOR_OPTIONS, $config ),
				$services->getRevisionLookup(),
				$services->getTitleFormatter(),
				$services->getPageStore()
			) );

		$parsoidOutputStash = $this->getParsoidOutputStash();
		$helperFactory->method( 'newHtmlOutputRendererHelper' )
			->willReturn(
				new HtmlOutputRendererHelper(
					$parsoidOutputStash,
					$services->getStatsdDataFactory(),
					$parsoidOutputAccess,
					$services->getHtmlTransformFactory(),
					$services->getContentHandlerFactory(),
					$services->getLanguageFactory()
				)
			);
		$helperFactory->method( 'newHtmlMessageOutputHelper' )
			->willReturn( new HtmlMessageOutputHelper() );

		$request ??= new RequestData( [] );
		$responseFactory = new ResponseFactory( [] );
		$helperFactory->method( 'newPageRedirectHelper' )
			->willReturn(
				new PageRedirectHelper(
					$services->getRedirectStore(),
					$services->getTitleFormatter(),
					$responseFactory,
					$this->newRouter( 'https://example.test/api' ),
					'/test/{title}',
					$request,
					$services->getLanguageConverterFactory()
				)
			);

		return new PageHTMLHandler(
			$helperFactory
		);
	}

	/**
	 * @return PageSourceHandler
	 */
	public function newPageSourceHandler() {
		$services = $this->getServiceContainer();
		return new PageSourceHandler(
			$services->getTitleFormatter(),
			$services->getPageRestHelperFactory()
		);
	}

	public function newPageHistoryHandler() {
		$services = $this->getServiceContainer();
		return new PageHistoryHandler(
			$services->getRevisionStore(),
			$services->getNameTableStoreFactory(),
			$services->getGroupPermissionsLookup(),
			$services->getConnectionProvider(),
			$services->getPageStore(),
			$services->getTitleFormatter(),
			$services->getPageRestHelperFactory()
		);
	}

	public function newPageHistoryCountHandler() {
		$services = $this->getServiceContainer();
		return new PageHistoryCountHandler(
			$services->getRevisionStore(),
			$services->getNameTableStoreFactory(),
			$services->getGroupPermissionsLookup(),
			$services->getConnectionProvider(),
			new WANObjectCache( [ 'cache' => $this->parserCacheBagOStuff, ] ),
			$services->getPageStore(),
			$services->getPageRestHelperFactory()
		);
	}

	public function newLanguageLinksHandler() {
		$services = $this->getServiceContainer();
		return new LanguageLinksHandler(
			$services->getConnectionProvider(),
			$services->getLanguageNameUtils(),
			$services->getTitleFormatter(),
			$services->getTitleParser(),
			$services->getPageStore(),
			$services->getPageRestHelperFactory()
		);
	}

	private function installMockFileRepo( string $fileName, ?string $redirectedFrom = null ): void {
		$repo = $this->createNoOpMock(
			FileRepo::class,
			[]
		);
		$file = $this->createNoOpMock(
			File::class,
			[
				'isLocal',
				'exists',
				'getRepo',
				'getRedirected',
				'getName',
			]
		);
		$file->method( 'isLocal' )->willReturn( false );
		$file->method( 'exists' )->willReturn( true );
		$file->method( 'getRepo' )->willReturn( $repo );
		$file->method( 'getRedirected' )->willReturn( $redirectedFrom );
		$file->method( 'getName' )->willReturn( $fileName );

		$repoGroup = $this->createNoOpMock(
			RepoGroup::class,
			[ 'findFile' ]
		);
		$repoGroup->expects( $this->atLeastOnce() )->method( 'findFile' )
			->willReturn( $file );

		$this->setService(
			'RepoGroup',
			$repoGroup
		);
	}

}
