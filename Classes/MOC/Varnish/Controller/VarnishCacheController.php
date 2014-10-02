<?php
namespace MOC\Varnish\Controller;

use MOC\Varnish\Service\VarnishBanService;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ActionController;
use Langeland\DeveloperTools\Domain\Model\Configuration;

/**
 * Class ConfigurationController
 *
 * @author Jan-Erik Revsbech <janerik@moc.net>
 */
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
	 *
	 */
	public function indexAction() {
		$this->view->assign('currentDomain', $this->request->getHttpRequest()->getUri()->getHost());
	}

	/**
	 * @param string $searchWord
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
	 */
	public function purgeCacheAction(\TYPO3\TYPO3CR\Domain\Model\Node $node, $searchWord) {
		$service = new VarnishBanService();
		$service->banByNode($node);
		$this->flashMessageContainer->addMessage(new \TYPO3\Flow\Error\Message('Varnish cache clearer for node ' . $node->getName()));
		$this->redirect('searchForNode', NULL, NULL, array('searchWord' => $searchWord));
	}

	/**
	 * @param string $searchWord
	 */
	public function purgeAllVarnishCacheAction($domain = NULL) {
		if ($domain === NULL) {
			$domain = $_SERVER['HTTP_HOST'];
		}
		$service = new VarnishBanService();
		$service->banAll($domain);
		$this->flashMessageContainer->addMessage(new \TYPO3\Flow\Error\Message('All varnish cache cleared for domain ' . $domain));
		$this->redirect('index');
	}

}
