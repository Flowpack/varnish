<?php
namespace MOC\Varnish;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Package\Package as BasePackage;

class Package extends BasePackage {

	/**
	 * Register slots for sending correct headers and BANS to varnish
	 *
	 * @param \Neos\Flow\Core\Bootstrap $bootstrap The current bootstrap
	 * @return void
	 */
	public function boot(\Neos\Flow\Core\Bootstrap $bootstrap) {
		$dispatcher = $bootstrap->getSignalSlotDispatcher();
		$dispatcher->connect(ConfigurationManager::class, 'configurationManagerReady', function (ConfigurationManager $configurationManager) use ($dispatcher) {
			$enabled = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'MOC.Varnish.enabled');
			if ((boolean)$enabled === true) {
				$dispatcher->connect('Neos\Neos\Service\PublishingService', 'nodePublished', 'MOC\Varnish\Service\ContentCacheFlusherService', 'flushForNode');
				$dispatcher->connect('Neos\Flow\Mvc\Dispatcher', 'afterControllerInvocation', 'MOC\Varnish\Service\CacheControlService', 'addHeaders');
			}
		});
	}

}
