<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\Framework\App;

use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;

/**
 * A website served on a domain of its own (the web server passes
 * MAGE_RUN_TYPE=website, MAGE_RUN_CODE=<website>, the usual way to run several
 * shops from one Magento) has ITS default store view as the guest's default,
 * not the default store view of the installation's default website.
 *
 * Magento's own context plugin (Store\App\Action\Plugin\Context) and ours
 * (ApplyStoreContext) take the installation's default store view in that case,
 * so on every such website a guest's store and currency "differ from the
 * default", Magento sends an X-Magento-Vary cookie with every page, and
 * Magento's VCL never caches a response that sets one: found 2026-10-25, every
 * page of marketplace.zerofront.dev was UNCACHEABLE in Varnish (0.4 s each,
 * for every visitor). The default of the store and currency context values is
 * corrected here, where both plugins set it.
 */
class WebsiteDefaultContext
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @see HttpContext::setValue()
     * @param mixed $value
     * @param mixed $default
     * @return array|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSetValue(HttpContext $subject, $name, $value, $default)
    {
        if (($name !== StoreManagerInterface::CONTEXT_STORE && $name !== HttpContext::CONTEXT_CURRENCY)
            || $this->request->getServerValue(StoreManager::PARAM_RUN_TYPE) !== ScopeInterface::SCOPE_WEBSITE
        ) {
            return null;
        }
        $store = $this->storeManager->getStore();
        if ($store->isUseStoreInUrl()) {
            return null;
        }
        $websiteDefault = $this->storeManager->getWebsite()->getDefaultStore();
        if (!$websiteDefault) {
            return null;
        }

        return [
            $name,
            $value,
            $name === StoreManagerInterface::CONTEXT_STORE
                ? $websiteDefault->getCode()
                : $websiteDefault->getDefaultCurrencyCode(),
        ];
    }
}
