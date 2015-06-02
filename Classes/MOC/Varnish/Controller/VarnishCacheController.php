<?php
namespace MOC\Varnish\Controller;

use MOC\Varnish\Service\ContentCacheFlusherService;
use MOC\Varnish\Service\VarnishBanService;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Client\CurlEngine;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Http\Request;
use TYPO3\Neos\Domain\Model\Site;

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
	 * @return void
	 */
	public function searchForNodeAction($searchWord) {
		$documentNodeTypes = $this->nodeTypeManager->getSubNodeTypes('TYPO3.Neos:Document');
		$sites = array();
		$activeSites = $this->siteRepository->findOnline();
		foreach ($activeSites as $site) {
			$liveContext = $this->contextFactory->create(array(
				'workspaceName' => 'live',
				'currentSite' => $site
			));
			$sites[$site->getNodeName()] = array(
				'site' => $site,
				'domain' => $site->getFirstActiveDomain() ?: $this->request->getHttpRequest()->getUri()->getHost(),
				'nodes' => $this->nodeSearchService->findByProperties($searchWord, $documentNodeTypes, $liveContext, $liveContext->getCurrentSiteNode())
			);
		}
		$this->view->assignMultiple(array(
			'searchWord' => $searchWord,
			'protocol' => $this->request->getHttpRequest()->getUri()->getScheme(),
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
		$this->flashMessageContainer->addMessage(new \TYPO3\Flow\Error\Message('Varnish cache cleared for node ' . $node->getLabel()));
		$this->redirect('searchForNode', NULL, NULL, array('searchWord' => $searchWord));
	}

	/**
	 * @param string $tags
	 * @return void
	 */
	public function purgeCacheByTagsAction($tags) {
		$service = new VarnishBanService();
		$service->banByTags(explode(',', $tags));
		$this->flashMessageContainer->addMessage(new \TYPO3\Flow\Error\Message('Varnish cache cleared for tags "' . implode('"," ', $tags). '""'));
		$this->redirect('index');
	}

	/**
	 * @param Site $site
	 * @param string $contentType
	 * @return void
	 */
	public function purgeAllVarnishCacheAction(Site $site = NULL, $contentType = NULL) {
		$domain = $site !== NULL ? $site->getFirstActiveDomain() : NULL;
		$service = new VarnishBanService();
		$service->banAll($domain, $contentType);
		$this->flashMessageContainer->addMessage(new \TYPO3\Flow\Error\Message(sprintf('All varnish cache cleared for %s', $site ? 'site ' . $site->getName() : 'installation')));
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
			'headers' => $response->getHeaders()->getAll()
		)));
	}

}
