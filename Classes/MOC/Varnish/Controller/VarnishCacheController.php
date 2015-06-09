<?php
namespace MOC\Varnish\Controller;

use MOC\Varnish\Service\ContentCacheFlusherService;
use MOC\Varnish\Service\VarnishBanService;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Error\Message;
use TYPO3\Flow\Http\Client\CurlEngine;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Http\Request;
use TYPO3\Neos\Domain\Model\Site;
use TYPO3\Neos\Domain\Service\ContentContext;

class VarnishCacheController extends \TYPO3\Neos\Controller\Module\AbstractModuleController {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\TYPO3CR\Domain\Service\NodeTypeManager
	 */
	protected $nodeTypeManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Neos\Domain\Service\ContentContextFactory
	 */
	protected $contextFactory;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Neos\Domain\Service\NodeSearchService
	 */
	protected $nodeSearchService;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Neos\Domain\Repository\SiteRepository
	 */
	protected $siteRepository;

	/**
	 * @return void
	 */
	public function indexAction() {
		$this->view->assign('activeSites', $this->siteRepository->findOnline());
	}

	/**
	 * @param string $searchWord
	 * @param Site $selectedSite
	 * @return void
	 */
	public function searchForNodeAction($searchWord, Site $selectedSite = NULL) {
		$documentNodeTypes = $this->nodeTypeManager->getSubNodeTypes('TYPO3.Neos:Document');
		$shortcutNodeType = $this->nodeTypeManager->getNodeType('TYPO3.Neos:Shortcut');
		$nodeTypes = array_diff($documentNodeTypes, array($shortcutNodeType));
		$sites = array();
		$activeSites = $this->siteRepository->findOnline();
		foreach ($selectedSite ? array($selectedSite) : $activeSites as $site) {
			/** @var ContentContext $liveContext */
			$liveContext = $this->contextFactory->create(array(
				'workspaceName' => 'live',
				'currentSite' => $site
			));
			$firstActiveDomain = $site->getFirstActiveDomain();
			$nodes = $this->nodeSearchService->findByProperties($searchWord, $nodeTypes, $liveContext, $liveContext->getCurrentSiteNode());
			if (count($nodes) > 0) {
				$sites[$site->getNodeName()] = array(
					'site' => $site,
					'domain' => $firstActiveDomain ? $firstActiveDomain->getHostPattern() : $this->request->getHttpRequest()->getUri()->getHost(),
					'nodes' => $nodes
				);
			}
		}
		$this->view->assignMultiple(array(
			'searchWord' => $searchWord,
			'protocol' => $this->request->getHttpRequest()->getUri()->getScheme(),
			'selectedSite' => $selectedSite,
			'sites' => $sites,
			'activeSites' => $activeSites
		));
	}

	/**
	 * @param \TYPO3\TYPO3CR\Domain\Model\Node $node
	 * @param string $searchWord
	 * @return void
	 */
	public function purgeCacheAction(\TYPO3\TYPO3CR\Domain\Model\Node $node, $searchWord) {
		$service = new ContentCacheFlusherService();
		$service->flushForNode($node);
		return TRUE;
	}

	/**
	 * @param string $tags
	 * @param Site $site
	 * @return void
	 */
	public function purgeCacheByTagsAction($tags, Site $site = NULL) {
		$domain = $site !== NULL ? $site->getFirstActiveDomain()->getHostPattern() : NULL;
		$tags = explode(',', $tags);
		$service = new VarnishBanService();
		$service->banByTags($tags, $domain);
		$this->flashMessageContainer->addMessage(new Message(sprintf('Varnish cache cleared for tags "%s" for %s', implode('", "', $tags), $site ? 'site ' . $site->getName() : 'installation')));
		$this->redirect('index');
	}

	/**
	 * @param Site $site
	 * @param string $contentType
	 * @return void
	 */
	public function purgeAllVarnishCacheAction(Site $site = NULL, $contentType = NULL) {
		$domain = $site !== NULL ? $site->getFirstActiveDomain()->getHostPattern() : NULL;
		$service = new VarnishBanService();
		$service->banAll($domain, $contentType);
		$this->flashMessageContainer->addMessage(new Message(sprintf('All varnish cache cleared for %s%s', $site ? 'site ' . $site->getName() : 'installation', $contentType ? ' with content type "' . $contentType . '"' : '')));
		$this->redirect('index');
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public function checkUrlAction($url) {
		$request = Request::create(new Uri($url));
		$request->setHeader('X-Cache-Debug', '1');
		$engine = new CurlEngine();
		$engine->setOption(CURLOPT_SSL_VERIFYPEER, FALSE);
		$engine->setOption(CURLOPT_SSL_VERIFYHOST, FALSE);
		$response = $engine->sendRequest($request);
		return sprintf('<div id="url-check">%s</div>', json_encode(array(
			'statusCode' => $response->getStatusCode(),
			'host' => parse_url($url, PHP_URL_HOST),
			'url' => $url,
			'headers' => array_map(function($value) {
				return array_pop($value);
			}, $response->getHeaders()->getAll())
		)));
	}

}