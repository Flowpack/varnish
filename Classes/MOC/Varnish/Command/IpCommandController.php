<?php
namespace MOC\Varnish\Command;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\Source\YamlSource;

/**
 * @Flow\Scope("singleton")
 */
class IpCommandController extends \TYPO3\Flow\Cli\CommandController {


     /**
     * Imports an ip list to configuration
     *
     * @param string $ip Varnish Server Ip (comma seperated)
     * @return void
     */
    public function listCommand($ip)
    {
        $configuration = FLOW_PATH_ROOT . 'Configuration/Settings';
        $config = $this->openConfig($configuration);
        $ipValid = $this->validateUrl(explode(',',$ip));

        $config['MOC']['Varnish']['varnishUrl'] = $ipValid;
        $this->saveConfig($configuration, $config);
    }

    /**
     * Add http (if missing) to ip array
     *
     * @param array ips
     * @return array ips
     */
    public function validateUrl($ips) {
        $result = array();
        foreach ($ips as $ip) {
            if (strpos($ip, 'http://') === false) {
                $ip = 'http://' . $ip;
            }
            $result[] = $ip;
        }
        return $result;
    }

    /**
     * Load existing configuration to array
     *
     * @param string configuration
     * @return array
     */
    public function openConfig($configuration) {
        $yaml = new YamlSource();
        return  $yaml->load($configuration);
    }

    /**
     * Save configuration from array
     *
     * @param string configuration
     * @param array
     * @return void
     */
    public function saveConfig($configuration, $config) {
        $yaml = new YamlSource();
        $yaml->save($configuration, $config);
    }

}