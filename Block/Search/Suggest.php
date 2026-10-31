<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Block\Search;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;

/**
 * The search box's suggestions (Controller\Search\Suggest), full page cached
 * per query like the results page: what it shows tags the response (the
 * products, and the categories and pages Zero_Search lists), so saving one of
 * them cleans it. Every view model argument with identities counts.
 */
class Suggest extends Template implements IdentityInterface
{
    /**
     * The cache tags of everything shown: the identities of each argument
     * that has them, read after the template rendered.
     *
     * @return string[]
     */
    public function getIdentities()
    {
        $tags = [];
        foreach ($this->getData() as $value) {
            if ($value instanceof IdentityInterface) {
                $tags[] = $value->getIdentities();
            }
        }

        $identities = array_values(array_unique(array_merge([], ...$tags)));

        // An empty list makes the page cache send "X-Magento-Tags: " and the built-in cache
        // (file tag index) then tries to write a tag named '' and the request is a 500: a search
        // with no result at all. One constant tag keeps the response well formed.
        return $identities ?: ['zero_search_suggest'];
    }
}
