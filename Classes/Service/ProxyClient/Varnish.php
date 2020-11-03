<?php
declare(strict_types=1);

namespace MOC\Varnish\Service\ProxyClient;

use FOS\HttpCache\ProxyClient\Varnish as FOSVarnish;

/**
 * Varnish ProxyClient that allows banning by tags and hosts
 * at the same time
 */
class Varnish extends FOSVarnish
{

    /**
     * @var array
     */
    private $hosts = [];

    public function forHosts(string ... $hosts): self
    {
        $this->hosts = $hosts;

        return $this;
    }

    public function ban(array $headers)
    {
        $headers = array_merge(
            $this->getHostHeader(),
            $headers
        );

        return parent::ban($headers);
    }

    private function getHostHeader(): array
    {
        switch (count($this->hosts)) {
            case 0:
                return [];
            case 1:
                return [self::HTTP_HEADER_HOST => current($this->hosts)];
            default:
                return [self::HTTP_HEADER_HOST => '^(' . implode('|', $this->hosts) . ')$'];
        }
    }

}
