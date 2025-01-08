<?php
declare(strict_types=1);

namespace Flowpack\Varnish\Controller;

use Flowpack\Varnish\Service\ContentCacheFlusherService;
use Flowpack\Varnish\Service\VarnishBanService;
use Neos\ContentRepository\Core\NodeType\NodeTypeNames;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindDescendantNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\NodeType\NodeTypeCriteria;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Mvc\View\JsonView;
use Neos\FluidAdaptor\View\TemplateView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\SiteNodeUtility;

class VarnishCacheController extends AbstractModuleController
{
    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = [
        'html' => TemplateView::class,
        'json' => JsonView::class,
    ];

    #[Flow\Inject]
    protected SiteRepository $siteRepository;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SiteNodeUtility $siteNodeUtility;

    #[Flow\Inject]
    protected ContentCacheFlusherService $contentCacheFlusherService;

    public function indexAction(): void
    {
        $this->view->assign('activeSites', $this->siteRepository->findOnline());
    }

    /**
     * @param string $searchWord
     * @param Site $selectedSite
     * @return void
     * @throws \Neos\Flow\Mvc\Exception\ForwardException
     */
    public function searchForNodeAction(string $searchWord = '', Site $selectedSite = null): void
    {
        // Legacy UI sends a XHR GET request to the same URL with missing POST values
        if ($searchWord === '') {
            $this->forward('index');
        }

        $sites = [];
        $activeSites = $this->siteRepository->findOnline();
        foreach ($selectedSite ? [$selectedSite] : $activeSites as $site) {
            $contentRepository = $this->contentRepositoryRegistry->get($site->getConfiguration()->contentRepositoryId);
            $nodeTypeManager = $contentRepository->getNodeTypeManager();

            $documentNodeTypes = $nodeTypeManager->getSubNodeTypes('Neos.Neos:Document');
            $shortcutNodeType = $nodeTypeManager->getNodeType('Neos.Neos:Shortcut');

            $nodes = [];

            $contentDimensionSpacePoints = $contentRepository->getVariationGraph()->getDimensionSpacePoints();
            foreach ($contentDimensionSpacePoints as $contentDimensionSpacePoint) {
                $currentSiteNode = $this->siteNodeUtility->findSiteNodeBySite(
                    $site,
                    WorkspaceName::forLive(),
                    $contentDimensionSpacePoint
                );
                $subgraph = $this->contentRepositoryRegistry->subgraphForNode($currentSiteNode);

                $descendantNodes = $subgraph->findDescendantNodes(
                    $currentSiteNode->aggregateId,
                    filter: FindDescendantNodesFilter::create(
                        nodeTypes: NodeTypeCriteria::create(
                            NodeTypeNames::fromArray(array_map(static fn ($documentNodeType) => $documentNodeType->name, $documentNodeTypes)),
                            NodeTypeNames::fromArray([$shortcutNodeType->name])),
                        searchTerm: $searchWord)
                );


                if ($descendantNodes->count()) {
                    $nodes[] = array_map(fn ($node) => [
                        'node' => $node,
                        'nodeAddress' => NodeAddress::fromNode($node)->toJson(),
                        'nodeTypeIcon' => $nodeTypeManager->getNodeType($node->nodeTypeName)->getConfiguration('ui.icon'),
                        'nodeTypeLabel' => $nodeTypeManager->getNodeType($node->nodeTypeName)->getLabel(),
                    ], iterator_to_array($descendantNodes->getIterator()));
                }
            }

            if (count($nodes) > 0) {
                $sites[$site->getNodeName()->value] = [
                    'site' => $site,
                    'nodes' => array_merge(...$nodes)
                ];
            }

        }
        $this->view->assignMultiple([
            'searchWord' => $searchWord,
            'selectedSite' => $selectedSite,
            'sites' => $sites,
            'activeSites' => $activeSites
        ]);
    }

    public function purgeCacheAction(string $nodeAddress): void
    {
        $nodeAddress = NodeAddress::fromJsonString($nodeAddress);
        $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->contentRepositoryId);
        $subgraph = $contentRepository->getContentSubgraph($nodeAddress->workspaceName, $nodeAddress->dimensionSpacePoint);
        $node = $subgraph->findNodeById($nodeAddress->aggregateId);

        $this->contentCacheFlusherService->flushForNode($node);

        $this->view->assign('value', true);
    }

    /**
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     */
    public function purgeCacheByTagsAction(string $tags, Site $site = null): void
    {
        $domains = null;
        if ($site !== null && $site->hasActiveDomains()) {
            $domains = $site->getActiveDomains()->map(function (Domain $domain) {
                return $domain->getHostname();
            })->toArray();
        }

        $tags = explode(',', $tags);
        $service = new VarnishBanService();
        $service->banByTags($tags, $domains);
        $this->addFlashMessage(sprintf('Varnish cache cleared for tags "%s" for %s', implode('", "', $tags), $site ? 'site ' . $site->getName() : 'installation'));
        $this->redirect('index');
    }

    /**
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     */
    public function purgeAllVarnishCacheAction(Site $site = null, $contentType = null): void
    {
        $domains = null;
        if ($site !== null && $site->hasActiveDomains()) {
            $domains = $site->getActiveDomains()->map(function (Domain $domain) {
                return $domain->getHostname();
            })->toArray();
        }
        $service = new VarnishBanService();
        $service->banAll($domains, $contentType);
        $this->addFlashMessage(sprintf('All varnish cache cleared for %s%s', $site ? 'site ' . $site->getName() : 'installation', $contentType ? ' with content type "' . $contentType . '"' : ''));
        $this->redirect('index');
    }

    /**
     * @throws \Neos\Flow\Http\Client\CurlEngineException
     * @throws \Neos\Flow\Http\Exception
     */
    public function checkUrlAction(string $url): void
    {
        $uri = new \GuzzleHttp\Psr7\Uri($url);
        if (isset($this->settings['reverseLookupPort'])) {
            $uri = $uri->withPort($this->settings['reverseLookupPort']);
        }
        $request = new \GuzzleHttp\Psr7\ServerRequest('GET', $uri);
        $request = $request->withHeader('X-Cache-Debug', '1');
        $engine = new CurlEngine();
        $engine->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $engine->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $response = $engine->sendRequest($request);
        $this->view->assign('value', [
            'statusCode' => $response->getStatusCode(),
            'host' => parse_url($url, PHP_URL_HOST),
            'url' => $url,
            'headers' => array_map(function ($value) {
                return current($value);
            }, $response->getHeaders())
        ]);
    }
}
