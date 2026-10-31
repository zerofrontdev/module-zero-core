<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Block;

use Magento\Catalog\Helper\Product\Compare as CompareHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Wishlist\Block\Customer\Sidebar as WishlistSidebar;
use Magento\Wishlist\ViewModel\WishlistData;

/**
 * The sidebar's Compare Products and My Wish List for this visitor: the
 * `sidebar` fragment of the session bootstrap (Controller\Session\Bootstrap,
 * layout handle zero_customer_sidebar), so a category view needs no request
 * of its own; also Controller\Sidebar\Index for pages cached before that.
 * Each block renders only with items; with neither the wrapper stays empty.
 * Per visitor: only ever in a private (no-cache) answer and that visitor's
 * own browser cache, never in a cached page.
 */
class Sidebar extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly CompareHelper $compareHelper,
        private readonly CustomerSession $customerSession,
        private readonly WishlistData $wishlistData,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The blocks with items, without the wrapper.
     */
    public function itemsHtml(): string
    {
        $layout = $this->getLayout();
        // A fragment's layout has no 'formkey' block; the templates' Remove/Add to Cart forms need it.
        if (!$layout->getBlock('formkey')) {
            $layout->createBlock(FormKey::class, 'formkey');
        }

        $html = '';
        if ($this->compareHelper->getItemCount() > 0) {
            $html .= $layout->createBlock(Template::class, 'zero.sidebar.compare')
                ->setTemplate('Magento_Catalog::product/compare/sidebar.phtml')
                ->toHtml();
        }

        if ($this->customerSession->isLoggedIn()) {
            /** @var WishlistSidebar $wishlist */
            $wishlist = $layout->createBlock(WishlistSidebar::class, 'zero.sidebar.wishlist');
            $wishlist->setData('wishlistDataViewModel', $this->wishlistData);
            if ($wishlist->hasWishlistItems()) {
                $html .= $wishlist->setTemplate('Magento_Wishlist::sidebar.phtml')->toHtml();
            }
        }

        return $html;
    }

    /**
     * The same wrapper as the page's placeholder (sidebar/shell.phtml), which it replaces.
     */
    protected function _toHtml(): string
    {
        return '<div class="flex flex-col gap-8">' . $this->itemsHtml() . '</div>';
    }
}
