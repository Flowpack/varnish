<?php
namespace MOC\Varnish\Cache;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\Exception\InvalidDataException;
use Neos\Flow\Utility\Environment;

/**
 * A string frontend that stores cache metadata (tags, lifetime) for entries
 */
class MetadataAwareStringFrontend extends \Neos\Cache\Frontend\StringFrontend {

	const SEPARATOR = '|';

	/**
	 * Store metadata of all loaded cache entries indexed by identifier
	 *
	 * @var array
	 */
	protected $metadata = array();

	/**
	 * @Flow\Inject
	 * @var Environment
	 */
	protected $environment;

	/**
	 * @Flow\Inject
	 * @var \MOC\Varnish\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * Set a cache entry and store additional metadata (tags and lifetime)
	 *
	 * {@inheritdoc}
	 */
	public function set($entryIdentifier, $content, array $tags = array(), $lifetime = NULL) {
		$content = $this->insertMetadata($content, $entryIdentifier, $tags, $lifetime);
		parent::set($entryIdentifier, $content, $tags, $lifetime);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($entryIdentifier) {
		$content = parent::get($entryIdentifier);
		if ($content !== FALSE) {
			$content = $this->extractMetadata($entryIdentifier, $content);
		}
		return $content;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getByTag($tag) {
		$entries = parent::getByTag($tag);
		foreach ($entries as $identifier => $content) {
			$entries[$identifier] = $this->extractMetadata($identifier, $content);
		}
		return $entries;
	}

	/**
	 * Insert metadata into the content
	 *
	 * @param string $content
	 * @param string $entryIdentifier The identifier metadata
	 * @param array $tags The tags metadata
	 * @param integer $lifetime The lifetime metadata
	 * @return string The content including the serialized metadata
	 * @throws InvalidDataException
	 */
	protected function insertMetadata($content, $entryIdentifier, array $tags, $lifetime) {
		if (!is_string($content)) {
			throw new InvalidDataException('Given data is of type "' . gettype($content) . '", but a string is expected for string cache.', 1433155737);
		}
		$metadata = array(
			'identifier' => $entryIdentifier,
			'tags' => $tags,
			'lifetime' => $lifetime
		);
		$metadataJson = json_encode($metadata);
		$this->metadata[$entryIdentifier] = $metadata;
		return $metadataJson . self::SEPARATOR . $content;
	}

	/**
	 * Extract metadata from the content and store it
	 *
	 * @param string $entryIdentifier The entry identifier
	 * @param string $content The raw content including serialized metadata
	 * @return string The content without metadata
	 * @throws InvalidDataException
	 */
	protected function extractMetadata($entryIdentifier, $content) {
		$separatorIndex = strpos($content, self::SEPARATOR);
		if ($separatorIndex === FALSE) {
			$exception = new InvalidDataException('Could not find cache metadata in entry with identifier ' . $entryIdentifier, 1433155925);
			if ($this->environment->getContext()->isProduction()) {
				$this->logger->logException($exception);
			} else {
				throw $exception;
			}
		}

		$metadataJson = substr($content, 0, $separatorIndex);
		$metadata = json_decode($metadataJson, TRUE);
		if ($metadata === NULL) {
			$exception = new InvalidDataException('Invalid cache metadata in entry with identifier ' . $entryIdentifier, 1433155926);
			if ($this->environment->getContext()->isProduction()) {
				$this->logger->logException($exception);
			} else {
				throw $exception;
			}
		}

		$this->metadata[$entryIdentifier] = $metadata;

		return substr($content, $separatorIndex + 1);
	}

	/**
	 * @return array Metadata of all loaded entries (indexed by identifier)
	 */
	public function getAllMetadata() {
		return $this->metadata;
	}
}