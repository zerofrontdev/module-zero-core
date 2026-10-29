<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\Framework\App;

use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Core\Plugin\Framework\App\WebsiteDefaultContext;

/**
 * On a website run by its own domain (MAGE_RUN_TYPE=website) a guest on its
 * default store view varies by nothing: no X-Magento-Vary, so Varnish caches.
 */
#[AllowMockObjectsWithoutExpectations]
class WebsiteDefaultContextTest extends TestCase
{
    private function plugin(?string $runType, bool $storeInUrl = false): WebsiteDefaultContext
    {
        $store = $this->createMock(Store::class);
        $store->method('isUseStoreInUrl')->willReturn($storeInUrl);
        $websiteDefault = $this->createMock(Store::class);
        $websiteDefault->method('getCode')->willReturn('marketplace');
        $websiteDefault->method('getDefaultCurrencyCode')->willReturn('USD');
        $website = $this->createMock(Website::class);
        $website->method('getDefaultStore')->willReturn($websiteDefault);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $stores->method('getWebsite')->willReturn($website);
        $request = $this->createMock(Http::class);
        $request->method('getServerValue')->willReturn($runType);

        return new WebsiteDefaultContext($stores, $request);
    }

    public function testTheWebsitesOwnDefaultStoreViewIsTheDefault(): void
    {
        $plugin = $this->plugin('website');
        $context = $this->createMock(HttpContext::class);

        $this->assertSame(
            [StoreManagerInterface::CONTEXT_STORE, 'marketplace', 'marketplace'],
            $plugin->beforeSetValue($context, StoreManagerInterface::CONTEXT_STORE, 'marketplace', 'default')
        );
        $this->assertSame(
            [HttpContext::CONTEXT_CURRENCY, 'USD', 'USD'],
            $plugin->beforeSetValue($context, HttpContext::CONTEXT_CURRENCY, 'USD', 'EUR')
        );
    }

    public function testOtherSetupsAndValuesAreLeftAlone(): void
    {
        $context = $this->createMock(HttpContext::class);

        $this->assertNull($this->plugin(null)->beforeSetValue($context, StoreManagerInterface::CONTEXT_STORE, 'beam', 'default'));
        $this->assertNull($this->plugin('store')->beforeSetValue($context, StoreManagerInterface::CONTEXT_STORE, 'beam', 'beam'));
        $this->assertNull($this->plugin('website', true)->beforeSetValue($context, StoreManagerInterface::CONTEXT_STORE, 'beam', 'default'));
        $this->assertNull($this->plugin('website')->beforeSetValue($context, 'customer_group', 1, 0));
    }

    public function testTheVaryStringIsEmptyForTheWebsitesDefault(): void
    {
        $context = new HttpContext([], [], new \Magento\Framework\Serialize\Serializer\Json());
        $args = $this->plugin('website')->beforeSetValue($context, StoreManagerInterface::CONTEXT_STORE, 'marketplace', 'default');
        $context->setValue(...$args);

        $this->assertSame([], $context->getData(), 'nothing differs from the default: no X-Magento-Vary cookie');
    }
}
