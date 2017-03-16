<?php
namespace MOC\Varnish\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Fusion\Core\Cache\ContentCache;

/**
 * @Flow\Scope("singleton")
 */
class ContentCacheFlusherService {

	/**
	 * @Flow\Inject
	 * @var VarnishBanService
	 */
	protected $varnishBanService;

	/**
	 * @var array
	 */
	protected $tagsToFlush = array();

	/**
	 * @var array
	 */
	protected $domainsToFlush = array();

	/**
	 * @param NodeInterface $node The node which has changed in some way
	 * @return void
	 */
	public function flushForNode(NodeInterface $node) {
		$this->generateCacheTags($node);
	}

	/**
	 * @param NodeData $nodeData The node which has changed in some way
	 * @return void
	 */
	public function flushForNodeData(NodeData $nodeData) {
		$this->generateCacheTags($nodeData);
	}

	/**
	 * Generates cache tags to be flushed for a node which is flushed on shutdown.
	 *
	 * Code duplicated from Neos' ContentCacheFlusher class
	 *
	 * @param NodeInterface|NodeData $node The node which has changed in some way
	 * @return void
	 */
	protected function generateCacheTags($node) {
		$this->tagsToFlush[ContentCache::TAG_EVERYTHING] = 'which were tagged with "Everything".';

		$nodeTypesToFlush = $this->getAllImplementedNodeTypes($node->getNodeType());
		foreach ($nodeTypesToFlush as $nodeType) {
			/** @var NodeType $nodeType */
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

    		if ($node instanceof NodeInterface && $node->getContext() instanceof ContentContext) {
    			/** @var Site $site */
		        $site = $node->getContext()->getCurrentSite();
    			if ($site->hasActiveDomains()) {
    				$domains = $site->getActiveDomains()->map(function(Domain $domain) {
    					return $domain->getHostname();
    				})->toArray();
    				$this->domainsToFlush = array_unique(array_merge($this->domainsToFlush, $domains));
    			}
    		}
	}

	/**
	 * Flush caches according to the previously registered node changes.
	 *
	 * @return void
	 */
	public function shutdownObject() {
		if ($this->tagsToFlush !== array()) {
			$this->varnishBanService->banByTags(array_keys($this->tagsToFlush), $this->domainsToFlush);
		}
	}

	/**
	 * @param \Neos\ContentRepository\Domain\Model\NodeType $nodeType
	 * @return array<\Neos\ContentRepository\Domain\Model\NodeType>
	 */
	protected function getAllImplementedNodeTypes($nodeType) {
		$types = array($nodeType);
		foreach ($nodeType->getDeclaredSuperTypes() as $superType) {
			$types = array_merge($types, $this->getAllImplementedNodeTypes($superType));
		}
		return $types;
	}
}
