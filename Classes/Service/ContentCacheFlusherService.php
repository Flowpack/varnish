<?php
declare(strict_types=1);

namespace Flowpack\Varnish\Service;

use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\Core\Cache\ContentCache;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Fusion\Helper\CachingHelper;

/**
 * @Flow\Scope("singleton")
 *
 * @deprecated will be removed with 6.0
 */
class ContentCacheFlusherService
{

    /**
     * @Flow\Inject
     * @var VarnishBanService
     */
    protected $varnishBanService;

    /**
     * @var CachingHelper
     */
    protected $cachingHelper;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @var array
     */
    protected $tagsToFlush = [];

    /**
     * @var array
     */
    protected $domainsToFlush = [];

    /**
     * @param NodeInterface $node The node which has changed in some way
     * @return void
     * @throws NodeTypeNotFoundException
     */
    public function flushForNode(NodeInterface $node): void
    {
        $this->generateCacheTags($node);
    }

    /**
     * @param NodeData $nodeData The node which has changed in some way
     * @return void
     * @throws NodeTypeNotFoundException
     */
    public function flushForNodeData(NodeData $nodeData): void
    {
        $this->generateCacheTags($nodeData);
    }

    /**
     * Generates cache tags to be flushed for a node which is flushed on shutdown.
     *
     * @param NodeInterface|NodeData $node The node which has changed in some way
     * @return void
     * @throws NodeTypeNotFoundException
     */
    protected function generateCacheTags(NodeInterface $node): void
    {
        $this->tagsToFlush[ContentCache::TAG_EVERYTHING] = 'which were tagged with "Everything".';

        $workspaceHash = $this->getCachingHelper()->renderWorkspaceTagForContextNode('live');

        $nodeIdentifier = $node->getIdentifier();

        $this->generateCacheTagsForNodeIdentifier($workspaceHash .'_'. $nodeIdentifier);
        $this->generateCacheTagsForNodeType($node->getNodeType()->getName(), $nodeIdentifier, $workspaceHash);

        $traversedNode = $node;
        while ($traversedNode->getDepth() > 1) {
            $traversedNode = $traversedNode->getParent();
            // Workaround for issue #56566 in Neos.ContentRepository
            if ($traversedNode === null) {
                break;
            }
            $this->generateCacheTagsForDescendantOf($workspaceHash . '_' . $traversedNode->getIdentifier());
        }

        if ($node instanceof NodeInterface && $node->getContext() instanceof ContentContext) {
            /** @var Site $site */
            $site = $node->getContext()->getCurrentSite();
            if ($site->hasActiveDomains()) {
                $domains = $site->getActiveDomains()->map(function (Domain $domain) {
                    return $domain->getHostname();
                })->toArray();
                $this->domainsToFlush = array_unique(array_merge($this->domainsToFlush, $domains));
            }
        }
    }

    /**
     * @param string $cacheIdentifier
     */
    protected function generateCacheTagsForNodeIdentifier(string $cacheIdentifier): void
    {
        $tagName = 'Node_' . $cacheIdentifier;
        $this->tagsToFlush[$tagName] = sprintf('which were tagged with "%s" because node identifier "%s" has changed.', $tagName, $cacheIdentifier);
        // Note, as we don't have a node here we cannot go up the structure.
        $this->generateCacheTagsForDescendantOf($cacheIdentifier);
    }

    /**
     * @param string $cacheIdentifier
     */
    protected function generateCacheTagsForDescendantOf(string $cacheIdentifier): void
    {
        $tagName = 'DescendantOf_' . $cacheIdentifier;
        $this->tagsToFlush[$tagName] = sprintf('which were tagged with "%s" because node "%s" has changed.', $tagName, $cacheIdentifier);
    }

    /**
     * @param string $nodeTypeName
     * @param string|null $referenceNodeIdentifier
     * @param string $nodeTypePrefix
     *
     * @throws NodeTypeNotFoundException
     */
    protected function generateCacheTagsForNodeType(string $nodeTypeName, string $referenceNodeIdentifier = null, string $nodeTypePrefix = ''): void
    {
        $nodeTypesToFlush = $this->getAllImplementedNodeTypeNames($this->nodeTypeManager->getNodeType($nodeTypeName));

        if ($nodeTypePrefix !== '') {
            $nodeTypePrefix = rtrim($nodeTypePrefix, '_') . '_';
        }
        foreach ($nodeTypesToFlush as $nodeTypeNameToFlush) {
            $tagName = 'NodeType_' . $nodeTypePrefix . $nodeTypeNameToFlush;
            $this->tagsToFlush[$tagName] = sprintf('which were tagged with "%s" because node "%s" has changed and was of type "%s".', $tagName, $referenceNodeIdentifier ?: '', $nodeTypeName);
        }
    }

    /**
     * Flush caches according to the previously registered node changes.
     *
     * @return void
     */
    public function shutdownObject(): void
    {
        if (!empty($this->tagsToFlush)) {
            $this->varnishBanService->banByTags(array_keys($this->tagsToFlush), $this->domainsToFlush);
        }
    }

    /**
     * @param NodeType $nodeType
     * @return array<string>
     */
    protected function getAllImplementedNodeTypeNames(NodeType $nodeType): array
    {
        $self = $this;
        $types = array_reduce($nodeType->getDeclaredSuperTypes(), static function (array $types, NodeType $superType) use ($self) {
            return array_merge($types, $self->getAllImplementedNodeTypeNames($superType));
        }, [$nodeType->getName()]);
        $types = array_unique($types);
        return $types;
    }

    /**
     * @return CachingHelper
     */
    protected function getCachingHelper(): CachingHelper
    {
        if (!$this->cachingHelper instanceof CachingHelper) {
            $this->cachingHelper = new CachingHelper();
        }
        return $this->cachingHelper;
    }
}
