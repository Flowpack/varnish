<?php
declare(strict_types=1);

namespace MOC\Varnish\Controller;

use MOC\Varnish\Service\ContentCacheFlusherService;
use MOC\Varnish\Service\VarnishBanService;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Error\Messages\Message;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Mvc\View\JsonView;
use Neos\FluidAdaptor\View\TemplateView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\ContentContextFactory;
use Neos\Neos\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\Neos\Domain\Service\NodeSearchService;

class VarnishCacheController extends AbstractModuleController
{

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var NodeSearchService
     */
    protected $nodeSearchService;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;

    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = [
        'html' => TemplateView::class,
        'json' => JsonView::class,
    ];

    public function indexAction(): void
    {
        $this->view->assign('activeSites', $this->siteRepository->findOnline());
    }

    /**
     * @param string $searchWord
     * @param Site $selectedSite
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     * @throws \Neos\Flow\Mvc\Exception\ForwardException
     */
    public function searchForNodeAction(string $searchWord = '', Site $selectedSite = null): void
    {
        // Legacy UI sends a XHR GET request to the same URL with missing POST values
        if ($searchWord === '') {
            $this->forward('index');
        }

        $documentNodeTypes = $this->nodeTypeManager->getSubNodeTypes('Neos.Neos:Document');
        $shortcutNodeType = $this->nodeTypeManager->getNodeType('Neos.Neos:Shortcut');
        $nodeTypes = array_diff($documentNodeTypes, array($shortcutNodeType));
        $sites = [];
        $activeSites = $this->siteRepository->findOnline();
        foreach ($selectedSite ? [$selectedSite] : $activeSites as $site) {
            /** @var Site $site */
            $contextProperties = [
                'workspaceName' => 'live',
                'currentSite' => $site
            ];
            $contentDimensionPresets = $this->contentDimensionPresetSource->getAllPresets();
            if (count($contentDimensionPresets) > 0) {
                $mergedContentDimensions = [];
                foreach ($contentDimensionPresets as $contentDimensionIdentifier => $contentDimension) {
                    $mergedContentDimensions[$contentDimensionIdentifier] = [$contentDimension['default']];
                    foreach ($contentDimension['presets'] as $contentDimensionPreset) {
                        $mergedContentDimensions[$contentDimensionIdentifier] = array_merge($mergedContentDimensions[$contentDimensionIdentifier], $contentDimensionPreset['values']);
                    }
                    $mergedContentDimensions[$contentDimensionIdentifier] = array_values(array_unique($mergedContentDimensions[$contentDimensionIdentifier]));
                }
                $contextProperties['dimensions'] = $mergedContentDimensions;
            }
            /** @var ContentContext $liveContext */
            $liveContext = $this->contextFactory->create($contextProperties);
            $nodes = $this->nodeSearchService->findByProperties($searchWord, $nodeTypes, $liveContext, $liveContext->getCurrentSiteNode());
            if (count($nodes) > 0) {
                $sites[$site->getNodeName()] = [
                    'site' => $site,
                    'nodes' => $nodes
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

    /**
     * @param Node $node
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     */
    public function purgeCacheAction(Node $node): void
    {
        $service = new ContentCacheFlusherService();
        $service->flushForNode($node);
        $this->view->assign('value', true);
    }

    /**
     * @param string $tags
     * @param Site $site
     * @return void
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
     * @param Site $site
     * @param string $contentType
     * @return void
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
     * @param string $url
     * @return void
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
        $this->view->assign('value', array(
            'statusCode' => $response->getStatusCode(),
            'host' => parse_url($url, PHP_URL_HOST),
            'url' => $url,
            'headers' => array_map(function ($value) {
                return current($value);
            }, $response->getHeaders())
        ));
    }
}
