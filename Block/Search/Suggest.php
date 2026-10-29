<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Block\Search;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Zero\Core\ViewModel\SearchSuggest;

/**
 * The search box's suggestions (Controller\Search\Suggest), full page cached
 * per query like the results page: the products it shows tag the response,
 * so saving one of them cleans it.
 */
class Suggest extends Template implements IdentityInterface
{
    /**
     * The shown products' cache tags.
     *
     * @return string[]
     */
    public function getIdentities()
    {
        $viewModel = $this->getData('viewModel');

        return $viewModel instanceof SearchSuggest ? $viewModel->getIdentities() : [];
    }
}
