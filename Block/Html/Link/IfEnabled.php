<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Zero\Core\Block\Html\Link;

use Magento\Framework\View\Element\Html\Link\Current;
use Magento\Store\Model\ScopeInterface;

/**
 * A link (a footer link, say) that renders only where the config flag in its
 * `enabled_path` argument is on for the store view: a module's own switch, so
 * that a link never points at a page the module has turned off (a 404). Use
 * `ifconfig` for the link's own "show in the footer" setting as usual.
 *
 *   <block class="Zero\Core\Block\Html\Link\IfEnabled" name="…" ifconfig="zero_company/general/footer_link">
 *       <arguments>
 *           <argument name="enabled_path" xsi:type="string">zero_company/general/enabled</argument>
 *           …
 */
class IfEnabled extends Current
{
    /**
     * @return string
     */
    protected function _toHtml()
    {
        $path = (string) $this->getData('enabled_path');
        if ($path !== '' && !$this->_scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE)) {
            return '';
        }

        return parent::_toHtml();
    }
}
