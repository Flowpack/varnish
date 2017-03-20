<?php
namespace MOC\Varnish\Tests\Unit\Cache;

use MOC\Varnish\Aspects\ContentCacheAspect;
use MOC\Varnish\Cache\MetadataAwareStringFrontend;
use MOC\Varnish\Service\CacheControlService;
use MOC\Varnish\Service\TokenStorage;
use MOC\Varnish\Log\LoggerInterface;
use Neos\Cache\Backend\TransientMemoryBackend;
use Neos\Cache\EnvironmentConfiguration;
use Neos\Flow\Core\ApplicationContext;
use Neos\Flow\Mvc\Controller\Argument;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\RequestInterface;
use Neos\Flow\Mvc\ResponseInterface;
use Neos\Neos\Controller\Frontend\NodeController;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;

class CacheControlServiceTest extends \Neos\Flow\Tests\UnitTestCase
{

    /**
     * @var CacheControlService
     */
    protected $service;

    /**
     * @var ContentCacheAspect
     */
    protected $mockContentCacheAspect;

    /**
     * @var MetadataAwareStringFrontend
     */
    protected $contentCacheFrontend;

    /**
     * @var RequestInterface
     */
    protected $mockRequest;

    /**
     * @var ResponseInterface
     */
    protected $mockResponse;

    /**
     * @var LoggerInterface
     */
    protected $mockLogger;

    /**
     * @var NodeController
     */
    protected $mockController;

    /**
     * @var ControllerContext
     */
    protected $mockControllerContext;

    /**
     * @var Arguments
     */
    protected $mockArguments;

    /**
     * @var Argument
     */
    protected $mockArgument;

    /**
     * @var NodeInterface
     */
    protected $mockNode;

    /**
     * @var Context
     */
    protected $mockContext;

    /**
     * @var TokenStorage
     */
    protected $mockTokenStorage;

    protected function setUp()
    {
        $this->service = new CacheControlService();
        $this->mockContentCacheAspect = $this->createMock('MOC\Varnish\Aspects\ContentCacheAspect');
        $this->inject($this->service, 'contentCacheAspect', $this->mockContentCacheAspect);
        $this->mockLogger = $this->getMockBuilder('MOC\Varnish\Log\LoggerInterface')->getMock();
        $this->inject($this->service, 'logger', $this->mockLogger);
        $this->contentCacheFrontend = new MetadataAwareStringFrontend('test',
            new TransientMemoryBackend(new EnvironmentConfiguration(
                'Testing',
                'vfs://Foo/'
            ))
        );
        $this->contentCacheFrontend->initializeObject();
        $this->inject($this->service, 'contentCacheFrontend', $this->contentCacheFrontend);
        $this->mockTokenStorage = $this->createMock('MOC\Varnish\Service\TokenStorage');
        $this->inject($this->service, 'tokenStorage', $this->mockTokenStorage);

        $this->mockRequest = $this->createMock('Neos\Flow\Mvc\RequestInterface');
        $this->mockResponse = $this->createMock('Neos\Flow\Http\Response');
        $this->mockController = $this->createMock('Neos\Neos\Controller\Frontend\NodeController');
        $this->mockControllerContext = $this->getMockBuilder('Neos\Flow\Mvc\Controller\ControllerContext')->disableOriginalConstructor()->getMock();
        $this->mockController->expects($this->any())->method('getControllerContext')->willReturn($this->mockControllerContext);
        $this->mockArguments = $this->getMockBuilder('Neos\Flow\Mvc\Controller\Arguments')->getMock();
        $this->mockControllerContext->expects($this->any())->method('getArguments')->willReturn($this->mockArguments);
        $this->mockArguments->expects($this->any())->method('hasArgument')->with('node')->willReturn(true);
        $this->mockArgument = $this->getMockBuilder('Neos\Flow\Mvc\Controller\Argument')->disableOriginalConstructor()->getMock();
        $this->mockArguments->expects($this->any())->method('getArgument')->with('node')->willReturn($this->mockArgument);
        $this->mockNode = $this->createMock('Neos\ContentRepository\Domain\Model\NodeInterface');
        $this->mockArgument->expects($this->any())->method('getValue')->willReturn($this->mockNode);
        $this->mockContext = $this->getMockBuilder('Neos\ContentRepository\Domain\Service\Context')->disableOriginalConstructor()->getMock();
        $this->mockNode->expects($this->any())->method('getContext')->willReturn($this->mockContext);
    }

    /**
     * @test
     */
    public function addHeadersInLiveWorkspaceAndCachedResponseAddsTagsFromCache()
    {
        $this->mockContext->expects($this->any())->method('getWorkspaceName')->willReturn('live');

        $this->contentCacheFrontend->set('entry1', 'Foo', array('Tag1'));
        $this->contentCacheFrontend->set('entry2', 'Bar', array('Tag2'));

        $this->mockResponse->expects($this->at(0))->method('setHeader')->with('X-Cache-Tags', 'Tag1,Tag2');

        $this->service->addHeaders($this->mockRequest, $this->mockResponse, $this->mockController);
    }

    /**
     * @test
     */
    public function addHeadersInLiveWorkspaceAndCachedResponseWillSetMinimalLifetimeOfEntries()
    {
        $this->mockContext->expects($this->any())->method('getWorkspaceName')->willReturn('live');

        $this->contentCacheFrontend->set('entry1', 'Foo', array('Tag1'), 10000);
        $this->contentCacheFrontend->set('entry2', 'Bar', array('Tag2'), 1000);

        $this->mockResponse->expects($this->atLeastOnce())->method('setSharedMaximumAge')->with(1000);

        $this->service->addHeaders($this->mockRequest, $this->mockResponse, $this->mockController);
    }

    /**
     * @test
     */
    public function addHeadersInLiveWorkspaceAndCachedResponseWithDefaultAndSmallEntryLifetime()
    {
        $this->mockContext->expects($this->any())->method('getWorkspaceName')->willReturn('live');

        $this->service->injectSettings(array(
            'cacheHeaders' => array(
                'defaultSharedMaximumAge' => 86400
            )
        ));

        $this->contentCacheFrontend->set('entry1', 'Foo', array('Tag1'), 10000);

        $this->mockResponse->expects($this->atLeastOnce())->method('setSharedMaximumAge')->with(10000);

        $this->service->addHeaders($this->mockRequest, $this->mockResponse, $this->mockController);
    }

    /**
     * @test
     */
    public function addHeadersInLiveWorkspaceAndCachedResponseWithDefaultAndLargeEntryLifetime()
    {
        $this->mockContext->expects($this->any())->method('getWorkspaceName')->willReturn('live');

        $this->service->injectSettings(array(
            'cacheHeaders' => array(
                'defaultSharedMaximumAge' => 86400
            )
        ));

        $this->contentCacheFrontend->set('entry1', 'Foo', array('Tag1'), 124800);

        $this->mockResponse->expects($this->atLeastOnce())->method('setSharedMaximumAge')->with(86400);

        $this->service->addHeaders($this->mockRequest, $this->mockResponse, $this->mockController);
    }

    /**
     * @test
     */
    public function addHeadersInLiveWorkspaceAndCachedResponseAddsSiteToken()
    {
        $this->mockContext->expects($this->any())->method('getWorkspaceName')->willReturn('live');
        $this->mockTokenStorage->expects($this->any())->method('getToken')->willReturn('RandomSiteToken');

        $this->mockResponse->expects($this->at(0))->method('setHeader')->with('X-Site', 'RandomSiteToken');

        $this->service->addHeaders($this->mockRequest, $this->mockResponse, $this->mockController);
    }
}
