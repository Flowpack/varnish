<?php
namespace Flowpack\Varnish\Tests\Unit\Cache;

use Flowpack\Varnish\Cache\MetadataAwareStringFrontend;
use Neos\Cache\Backend\TransientMemoryBackend;
use Neos\Cache\EnvironmentConfiguration;

class MetadataAwareStringFrontendTest extends \Neos\Flow\Tests\UnitTestCase
{

    /**
     * @var MetadataAwareStringFrontend
     */
    protected $frontend;

    protected function setUp(): void
    {
        $this->frontend = new MetadataAwareStringFrontend(
            'test',
            new TransientMemoryBackend(new EnvironmentConfiguration(
                'Testing',
                'vfs://Foo/'
            ))
        );
        $this->frontend->initializeObject();
    }

    /**
     * @test
     */
    public function setInsertsMetadataThatCanBeFetched(): void
    {
        $this->frontend->set('foo', 'Bar', ['Tag1', 'Tag2'], 10240);

        $allMetadata = $this->frontend->getAllMetadata();
        self::assertArrayHasKey('foo', $allMetadata);
        $metadata = $allMetadata['foo'];
        self::assertEquals(['Tag1', 'Tag2'], $metadata['tags']);
        self::assertEquals(10240, $allMetadata['foo']['lifetime']);
    }

    /**
     * @test
     */
    public function getDoesNotReturnMetadata(): void
    {
        $this->frontend->set('foo', 'Bar', ['Tag1', 'Tag2'], 10240);
        $content = $this->frontend->get('foo');

        self::assertEquals('Bar', $content);
    }

    /**
     * @test
     */
    public function getByTagDoesNotReturnMetadata(): void
    {
        $this->frontend->set('foo', 'Bar', ['Tag1', 'Tag2'], 10240);
        $entries = $this->frontend->getByTag('Tag2');

        self::assertEquals(['foo' => 'Bar'], $entries);
    }
}
