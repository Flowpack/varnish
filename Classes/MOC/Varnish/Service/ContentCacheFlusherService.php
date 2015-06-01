<?php
namespace MOC\Varnish\Service;

use FOS\HttpCache\ProxyClient;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeType;
use TYPO3\TypoScript\Core\Cache\ContentCache;

/**
 * @Flow\Scope("singleton")
 */
class ContentCacheFlusherService {

	/**
	 * @var VarnishBanService
	 * @Flow\Inject
	 */
	protected $varnishBanService;

	/**
	 * @var array
	 */
	protected $tagsToFlush = array();

	/**
	 * Generates cache tags to be flushed for a node which is flushed on shutdown.
	 *
	 * Code duplicated from Neos' ContentCacheFlusher class
	 *
	 * @param NodeInterface $node The node which has changed in some way
	 * @return void
	 */
	public function flushForNode(NodeInterface $node) {
		$this->tagsToFlush[ContentCache::TAG_EVERYTHING] = 'which were tagged with "Everything".';

		$nodeTypesToFlush = $this->getAllImplementedNodeTypes($node->getNodeType());
		foreach ($nodeTypesToFlush as $nodeType) {
			/** @var NodeType $nodeTypeName */
			$nodeTypeName = $nodeType->getName();
			$this->tagsToFlush['NodeType_' . $nodeTypeName] = sprintf('which were tagged with "NodeType_%s" because node "%s" has changed and was of type "%s".', $nodeTypeName, $node->getPath(), $node->getNodeType()->getName());
		}

		$this->tagsToFlush['Node_' . $node->getIdentifier()] = sprintf('which were tagged with "Node_%s" because node "%s" has changed.', $node->getIdentifier(), $node->getPath());

		while ($node->getDepth() > 1) {
			$node = $node->getParent();
			if ($node === NULL) {
				break;
			}
			$this->tagsToFlush['DescendantOf_' . $node->getIdentifier()] = sprintf('which were tagged with "DescendantOf_%s" because node "%s" has changed.', $node->getIdentifier(), $node->getPath());
		}
	}

	/**
	 * Flush caches according to the previously registered node changes.
	 *
	 * @return void
	 */
	public function shutdownObject() {
		if ($this->tagsToFlush !== array()) {
			$this->varnishBanService->banByTags(array_keys($this->tagsToFlush));
		}
	}

	/**
	 * @param \TYPO3\TYPO3CR\Domain\Model\NodeType $nodeType
	 * @return array<\TYPO3\TYPO3CR\Domain\Model\NodeType>
	 */
	protected function getAllImplementedNodeTypes($nodeType) {
		$types = array($nodeType);
		foreach ($nodeType->getDeclaredSuperTypes() as $superType) {
			$types = array_merge($types, $this->getAllImplementedNodeTypes($superType));
		}
		return $types;
	}
}
