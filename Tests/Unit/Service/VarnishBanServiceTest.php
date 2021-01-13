<?php
declare(strict_types=1);

namespace Flowpack\Varnish\Tests\Unit\Service;


use Flowpack\Varnish\Service\VarnishBanService;
use Neos\Flow\Tests\UnitTestCase;

class VarnishBanServiceTest extends UnitTestCase
{
    /**
     * @var VarnishBanService
     */
    protected $varnishBanServcie;

    public function setUp(): void
    {
        $proxyClass = $this->buildAccessibleProxy(VarnishBanService::class);
        $this->varnishBanServcie = new $proxyClass();
    }

    public function varnishUrlsDataProvider(): array
    {
        return [
            [
                'urls' => 'http://127.0.0.1/',
                'expected' => ['http://127.0.0.1']
            ],
            [
                'urls' => 'http://127.0.0.1/, 192.168.0.1:8081',
                'expected' => ['http://127.0.0.1', 'http://192.168.0.1:8081']
            ],
            [
                'urls' => ['http://127.0.0.1/', '192.168.0.1:8081', 'https://192.168.0.1:8081'],
                'expected' => ['http://127.0.0.1', 'http://192.168.0.1:8081', 'https://192.168.0.1:8081']
            ],
            [
                'urls' => null,
                'expected' => ['http://127.0.0.1']
            ],
        ];
    }

    /**
     * @test
     * @dataProvider varnishUrlsDataProvider
     *
     * @param $urls
     * @param $expected
     */
    public function prepareVarnishUrls($urls, array $expected): void
    {
        $settings['varnishUrl'] = $urls;
        $this->varnishBanServcie->_set('settings', $settings);

        self::assertEquals($expected, $this->varnishBanServcie->_call('prepareVarnishUrls'));
    }
}
