<?php
declare(strict_types=1);

namespace Flowpack\Varnish\Command;

use Flowpack\Varnish\Service\VarnishBanService;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class VarnishCommandController extends \Neos\Flow\Cli\CommandController
{

    /**
     * @var VarnishBanService
     * @Flow\Inject
     */
    protected $varnishBanService;

    /**
     * Clear all cache in Varnish for a optionally given domain & content type
     *
     * The domain is required since the expected VCL only bans for a given domain.
     *
     * @param string $domain The domain to flush, e.g. "example.com"
     * @param string $contentType The mime type to flush, e.g. "image/png"
     * @return void
     */
    public function clearCommand($domain = null, $contentType = null): void
    {
        $this->varnishBanService->banAll($domain, $contentType);
    }
}
