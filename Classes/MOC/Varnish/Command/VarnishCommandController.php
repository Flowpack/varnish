<?php
namespace MOC\Varnish\Command;

use MOC\Varnish\Service\VarnishBanService;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\Source\YamlSource;

/**
 * @Flow\Scope("singleton")
 */
class VarnishCommandController extends \TYPO3\Flow\Cli\CommandController {

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
	public function clearCommand($domain = NULL, $contentType = NULL) {
		$this->varnishBanService->banAll($domain, $contentType);
	}


     /**
     * Imports an ip list to configuration
     *
     * @param string $ip Varnish Server Ip (comma seperated)
     * @return void
     */
    public function iplistCommand($ip)
    {
        $filename = FLOW_PATH_ROOT . 'Configuration/Settings';
        $config = $this->openConfig($filename);
        $ipValid = $this->validateUrl(explode(',',$ip));

        $config['MOC']['Varnish']['varnishUrl'] = $ipValid;
        $this->saveConfig($filename, $config);
    }

    public function validateUrl($ips) {
        $result = array();
        foreach ($ips as $ip) {
            if (strpos($ip, 'http://') === false && strpos($ip, 'https://') === false) {
                $ip = 'http://' . $ip;
            }
            $result[] = $ip;
        }
        return $result;
    }

    public function openConfig($filename) {
        $yaml = new YamlSource();
        return  $yaml->load($filename);
    }

    public function saveConfig($filename, $config) {
        $yaml = new YamlSource();
        return $yaml->save($filename, $config);
    }

}