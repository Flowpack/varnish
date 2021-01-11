<?php
declare(strict_types=1);

namespace Flowpack\Varnish;

use Flowpack\Varnish\Service\ContentCacheFlusherService;
use Neos\Flow\Configuration\ConfigurationManager;
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
            $enabled = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Flowpack.Varnish.enabled');
            if ((boolean)$enabled === true) {
                $dispatcher->connect(PublishingService::class, 'nodePublished', ContentCacheFlusherService::class, 'flushForNode');
            }
        });
    }
}
