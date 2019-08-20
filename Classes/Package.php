<?php
namespace MOC\Varnish;

use MOC\Varnish\Service\CacheControlService;
use MOC\Varnish\Service\ContentCacheFlusherService;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Mvc\Dispatcher;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Neos\Service\PublishingService;

class Package extends BasePackage
{

    /**
     * Register slots for sending correct headers and BANS to varnish
     *
     * @param \Neos\Flow\Core\Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(\Neos\Flow\Core\Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(ConfigurationManager::class, 'configurationManagerReady', function (ConfigurationManager $configurationManager) use ($dispatcher) {
            $enabled = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'MOC.Varnish.enabled');
            if ((boolean)$enabled === true) {
                $dispatcher->connect(PublishingService::class, 'nodePublished', ContentCacheFlusherService::class, 'flushForNode');
                $dispatcher->connect(Dispatcher::class, 'afterControllerInvocation', CacheControlService::class, 'addHeaders');
            }
        });
    }
}
