<?php
declare(strict_types=1);

namespace Flowpack\Varnish\Service;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Fusion\Cache\CacheFlushingStrategy;
use Neos\Neos\Fusion\Cache\ContentCacheFlusher;
use Neos\Neos\Fusion\Cache\FlushNodeAggregateRequest;

#[Flow\Scope("singleton")]
class ContentCacheFlusherService
{

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected ContentCacheFlusher $contentCacheFlusher;

    public function flushForNode(Node $node): void
    {
        $contentGraph = $this->contentRepositoryRegistry->get($node->contentRepositoryId)->getContentGraph($node->workspaceName);

        $request = FlushNodeAggregateRequest::create(
            $node->contentRepositoryId,
            $node->workspaceName,
            $node->aggregateId,
            $node->nodeTypeName,
            $contentGraph->findAncestorNodeAggregateIds($node->aggregateId),
        );
        $this->contentCacheFlusher->flushNodeAggregate($request, CacheFlushingStrategy::IMMEDIATE);
    }
}
