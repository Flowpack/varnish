<?php
namespace MOC\Varnish;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "MOC.Varnish".            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Package\Package as BasePackage;


/**
 * The MOC Varnish Package
 */
class Package extends BasePackage {

	/**
	 * Register slots for sending correct headers and BANS to varnish
	 *
	 * @param \TYPO3\Flow\Core\Bootstrap $bootstrap The current bootstrap
	 * @return void
	 */
	public function boot(\TYPO3\Flow\Core\Bootstrap $bootstrap) {
		$dispatcher = $bootstrap->getSignalSlotDispatcher();
		$dispatcher->connect('TYPO3\Neos\Service\PublishingService', 'nodePublished', 'MOC\Varnish\Service\CacheControlService', 'handleNodePublished');
		$dispatcher->connect('TYPO3\Flow\Mvc\Dispatcher', 'afterControllerInvocation', 'MOC\Varnish\Service\CacheControlService', 'addHeaders');
	}

}
