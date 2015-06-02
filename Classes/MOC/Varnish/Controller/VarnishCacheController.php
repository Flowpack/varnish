<?php
namespace MOC\Varnish\Controller;

use MOC\Varnish\Service\ContentCacheFlusherService;
use MOC\Varnish\Service\VarnishBanService;
use TYPO3\Flow\Annotations as Flow;

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
	 * @return void
	 */
	public function indexAction() {
		$this->view->assign('currentDomain', $this->request->getHttpRequest()->getUri()->getHost());
	}

	/**
	 * @param string $searchWord
	 * @return void
	 */
	public function searchForNodeAction($searchWord) {
		$liveContext = $this->contextFactory->create(array(
			'workspaceName' => 'live'
		));

		$documentNodeTypes = $this->nodeTypeManager->getSubNodeTypes('TYPO3.Neos:Document');
		$this->view->assignMultiple(array(
			'searchWord' => $searchWord,
			'nodes' => $this->nodeSearchService->findByProperties($searchWord, $documentNodeTypes, $liveContext)
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
		$this->flashMessageContainer->addMessage(new \TYPO3\Flow\Error\Message('Varnish cache cleared for node ' . $node->getName()));
		$this->redirect('searchForNode', NULL, NULL, array('searchWord' => $searchWord));
	}

	/**
	 * @param string $domain
	 * @return void
	 */
	public function purgeAllVarnishCacheAction($domain = NULL) {
		if ($domain === NULL) {
			$domain = $this->request->getHttpRequest()->getUri()->getHost();
		}
		$service = new VarnishBanService();
		$service->banAll($domain);
		$this->flashMessageContainer->addMessage(new \TYPO3\Flow\Error\Message('All varnish cache cleared for domain ' . $domain));
		$this->redirect('index');
	}

}
