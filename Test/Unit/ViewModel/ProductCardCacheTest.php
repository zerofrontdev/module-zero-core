<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\ViewModel;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\App\Cache\Type\Block as BlockCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Catalog\SwatchImageParams;
use Zero\Core\ViewModel\ProductCardCache;

/**
 * The card cache key: a configurable's varies with the swatch filters that
 * pick its picture, a simple product's does not.
 */
class ProductCardCacheTest extends TestCase
{
    /** @var string[] the keys the cache was asked for */
    private array $keys = [];

    /** @var array<int, array{0: string, 1: string, 2: string[]}> what the cache was given: html, key, tags */
    private array $saved = [];

    /** @var string|false what the cache load returns */
    private string|false $cached = false;

    public function testConfigurableKeyVariesWithSwatchParams(): void
    {
        $this->render('configurable', ['color' => '50']);
        $this->render('configurable', []);
        $this->render('configurable', ['color' => '49']);
        $this->render('configurable', ['color' => '50']);

        $this->assertCount(4, $this->keys);
        $this->assertCount(3, array_unique($this->keys), 'red, plain and blue are three entries');
        $this->assertSame($this->keys[0], $this->keys[3], 'the same filter, the same entry');
    }

    public function testSimpleKeyDoesNot(): void
    {
        $this->render('simple', ['color' => '50']);
        $this->render('simple', []);

        $this->assertCount(2, $this->keys);
        $this->assertSame($this->keys[0], $this->keys[1]);
    }

    public function testRenderedCardIsStoredWithTheFormKeyTokenAndServedBack(): void
    {
        $html = $this->render('configurable', ['color' => '50'], 'form_key=KEY123 <a href="%url%">');

        $this->assertSame('form_key=KEY123 <a href="/checkout/cart/add">', $html);
        $this->assertCount(1, $this->saved);
        [$stored, $key, $tags] = $this->saved[0];
        $this->assertSame('form_key=' . $this->token() . ' <a href="%url%">', $stored, 'stored without the real form key');
        $this->assertStringNotContainsString('KEY123', $stored);
        $this->assertSame($this->keys[0], $key, 'saved under the key it was looked up by');
        $this->assertSame(['cat_p_42', BlockCache::CACHE_TAG], $tags);
    }

    public function testCachedCardGetsThisVisitorsFormKeyAndIsNotRenderedAgain(): void
    {
        $this->cached = 'form_key=' . $this->token() . ' <a href="%url%">';
        $html = $this->render('configurable', ['color' => '50'], 'never rendered');

        $this->assertSame('form_key=KEY123 <a href="/checkout/cart/add">', $html);
        $this->assertSame([], $this->saved, 'a hit is not saved again');
    }

    /** The private placeholder the stored HTML carries instead of the form key. */
    private function token(): string
    {
        return (string) (new \ReflectionClassConstant(ProductCardCache::class, 'FORM_KEY_TOKEN'))->getValue();
    }

    /**
     * Renders one card (cache empty) and notes the key it was looked up by.
     *
     * @param array<string, string|string[]> $swatchParams what SwatchImageParams::get() returns
     */
    private function render(string $typeId, array $swatchParams, string $card = '<li>card</li>'): string
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(function (string $key) {
            $this->keys[] = $key;

            return $this->cached;
        });
        $cache->method('save')->willReturnCallback(function (string $html, string $key, array $tags = []) {
            $this->saved[] = [$html, $key, $tags];

            return true;
        });
        $cacheState = $this->createStub(CacheState::class);
        $cacheState->method('isEnabled')->willReturn(true);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getCurrentCurrencyCode')->willReturn('EUR');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $theme = $this->createStub(ThemeInterface::class);
        $theme->method('getId')->willReturn(4);
        $design = $this->createStub(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($theme);
        $httpContext = $this->createStub(HttpContext::class);
        $httpContext->method('getVaryString')->willReturn('vary');
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('scopeDate')->willReturn(new \DateTime('2026-10-31'));
        $layer = $this->createStub(Layer::class);
        $layer->method('getCurrentCategory')->willReturn(null);
        $layerResolver = $this->createStub(LayerResolver::class);
        $layerResolver->method('get')->willReturn($layer);
        $formKey = $this->createStub(FormKey::class);
        $formKey->method('getFormKey')->willReturn('KEY123');
        if ($typeId === 'configurable') {
            $swatches = $this->createStub(SwatchImageParams::class);
            $swatches->method('get')->willReturn($swatchParams);
        } else {
            // A simple product's picture never changes with the filters: not even read.
            $swatches = $this->createMock(SwatchImageParams::class);
            $swatches->expects($this->never())->method('get');
        }
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(42);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getIdentities')->willReturn(['cat_p_42']);

        $viewModel = new ProductCardCache(
            $cache,
            $cacheState,
            $scopeConfig,
            $storeManager,
            $design,
            $httpContext,
            $this->createStub(CustomerSession::class),
            $timezone,
            $layerResolver,
            $formKey,
            $swatches
        );

        return $viewModel->render(
            $product,
            $this->createStub(BlockInterface::class),
            ['mode' => 'grid'],
            static fn () => $card,
            ['%url%' => '/checkout/cart/add']
        );
    }
}
