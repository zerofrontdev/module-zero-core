<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Block;

use Magento\Catalog\Helper\Product\Compare as CompareHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Wishlist\Block\Customer\Sidebar as WishlistSidebar;
use Magento\Wishlist\ViewModel\WishlistData;
use PHPUnit\Framework\TestCase;
use Zero\Core\Block\Sidebar;

/**
 * The visitor's sidebar fragment: compare for anyone with items, the wish list only for a signed-in customer with
 * items, nothing otherwise; a form key block for the templates' forms when the fragment's layout has none.
 */
class SidebarTest extends TestCase
{
    /** @var string[] */
    private array $created = [];

    public function testNothingWhenBothAreEmpty(): void
    {
        $this->assertSame('', $this->sidebar(0, true, false)->itemsHtml());
    }

    public function testCompareForAnyVisitorWithItems(): void
    {
        $this->assertSame('<compare/>', $this->sidebar(2, false, true)->itemsHtml(), 'no wish list for guests');
        $this->assertContains('formkey', $this->created);
    }

    public function testWishListOnlyWithItems(): void
    {
        $this->assertSame('<wishlist/>', $this->sidebar(0, true, true)->itemsHtml());
        $this->assertSame('<compare/><wishlist/>', $this->sidebar(1, true, true)->itemsHtml());
    }

    public function testAnExistingFormKeyBlockIsKept(): void
    {
        $this->sidebar(1, false, false, true)->itemsHtml();

        $this->assertNotContains('formkey', $this->created);
    }

    private function sidebar(int $compareCount, bool $loggedIn, bool $wishlistItems, bool $hasFormKey = false): Sidebar
    {
        $this->created = [];
        $compareBlock = $this->createStub(Template::class);
        $compareBlock->method('setTemplate')->willReturnSelf();
        $compareBlock->method('toHtml')->willReturn('<compare/>');
        $wishlistBlock = $this->createStub(WishlistSidebar::class);
        $wishlistBlock->method('setTemplate')->willReturnSelf();
        $wishlistBlock->method('hasWishlistItems')->willReturn($wishlistItems);
        $wishlistBlock->method('toHtml')->willReturn('<wishlist/>');

        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getBlock')->willReturn($hasFormKey ? $this->createStub(FormKey::class) : false);
        $layout->method('createBlock')->willReturnCallback(function ($type, $name = '') use ($compareBlock, $wishlistBlock) {
            $this->created[] = $name;
            return match ($type) {
                Template::class => $compareBlock,
                WishlistSidebar::class => $wishlistBlock,
                default => $this->createStub(AbstractBlock::class),
            };
        });
        $context = $this->createStub(Context::class);
        $context->method('getLayout')->willReturn($layout);
        $compare = $this->createStub(CompareHelper::class);
        $compare->method('getItemCount')->willReturn($compareCount);
        $session = $this->createStub(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn($loggedIn);

        $sidebar = new Sidebar($context, $compare, $session, $this->createStub(WishlistData::class));
        $sidebar->setLayout($layout);

        return $sidebar;
    }
}
