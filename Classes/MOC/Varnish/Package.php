<?php
namespace MOC\Varnish;

use TYPO3\Flow\Package\Package as BasePackage;

class Package extends BasePackage {

	/**
	 * Register slots for sending correct headers and BANS to varnish
	 *
	 * @param \TYPO3\Flow\Core\Bootstrap $bootstrap The current bootstrap
	 * @return void
	 */
	public function boot(\TYPO3\Flow\Core\Bootstrap $bootstrap) {

		$dispatcher = $bootstrap->getSignalSlotDispatcher();
		$dispatcher->connect('TYPO3\Neos\Service\PublishingService', 'nodePublished', 'MOC\Varnish\Service\ContentCacheFlusherService', 'flushForNode');
		$dispatcher->connect('TYPO3\Flow\Mvc\Dispatcher', 'afterControllerInvocation', 'MOC\Varnish\Service\CacheControlService', 'addHeaders');
	}

}
