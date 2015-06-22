<?php
namespace MOC\Varnish\Tests\Unit\Cache;

use MOC\Varnish\Cache\MetadataAwareStringFrontend;
use TYPO3\Flow\Cache\Backend\TransientMemoryBackend;
use TYPO3\Flow\Core\ApplicationContext;

class MetadataAwareStringFrontendTest extends \TYPO3\Flow\Tests\UnitTestCase {

	/**
	 * @var MetadataAwareStringFrontend
	 */
	protected $frontend;

	protected function setUp() {
		$this->frontend = new MetadataAwareStringFrontend('test',
			new TransientMemoryBackend(new ApplicationContext('Testing'))
		);
		$this->frontend->initializeObject();
	}

	/**
	 * @test
	 */
	public function setInsertsMetadataThatCanBeFetched() {
		$this->frontend->set('foo', 'Bar', array('Tag1', 'Tag2'), 10240);

		$allMetadata = $this->frontend->getAllMetadata();
		$this->assertArrayHasKey('foo', $allMetadata);
		$metadata = $allMetadata['foo'];
		$this->assertEquals(array('Tag1', 'Tag2'), $metadata['tags']);
		$this->assertEquals(10240, $allMetadata['foo']['lifetime']);
	}

	/**
	 * @test
	 */
	public function getDoesNotReturnMetadata() {
		$this->frontend->set('foo', 'Bar', array('Tag1', 'Tag2'), 10240);
		$content = $this->frontend->get('foo');

		$this->assertEquals('Bar', $content);
	}

	/**
	 * @test
	 */
	public function getByTagDoesNotReturnMetadata() {
		$this->frontend->set('foo', 'Bar', array('Tag1', 'Tag2'), 10240);
		$entries = $this->frontend->getByTag('Tag2');

		$this->assertEquals(array(
			'foo' => 'Bar'
		), $entries);
	}
}