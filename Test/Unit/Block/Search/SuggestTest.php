<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Block\Search;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use PHPUnit\Framework\TestCase;
use Zero\Core\Block\Search\Suggest;
use Zero\Core\ViewModel\SearchSuggest;
use Zero\Search\ViewModel\Search;

/**
 * The suggestions' cache tags: every view model argument with identities.
 */
class SuggestTest extends TestCase
{
    public function testIdentitiesMergeEveryViewModel(): void
    {
        $products = $this->createStub(SearchSuggest::class);
        $products->method('getIdentities')->willReturn(['cat_p_1', 'cat_p']);
        // Zero_Search's view model, added by its zero_search_suggest.xml as `search`.
        $more = $this->createStub(Search::class);
        $more->method('getIdentities')->willReturn(['cat_c_5', 'cms_p_3', 'cat_p']);

        $block = $this->block([
            'viewModel' => $products,
            'search' => $more,
            'other' => $this->createStub(ArgumentInterface::class),
            'template' => 'Zero_Search::suggest.phtml',
        ]);

        $this->assertSame(['cat_p_1', 'cat_p', 'cat_c_5', 'cms_p_3'], $block->getIdentities());
    }

    public function testNoViewModelStillOneTag(): void
    {
        // An empty list becomes "X-Magento-Tags: " and the built-in page cache then writes a tag named '' (a 500).
        $this->assertSame(
            ['zero_search_suggest'],
            $this->block(['template' => 'Zero_Core::search/suggest.phtml'])->getIdentities()
        );
    }

    public function testViewModelsWithNothingToShowStillOneTag(): void
    {
        $products = $this->createStub(SearchSuggest::class);
        $products->method('getIdentities')->willReturn([]);

        $this->assertSame(['zero_search_suggest'], $this->block(['viewModel' => $products])->getIdentities());
    }

    private function block(array $data): Suggest
    {
        $block = (new \ReflectionClass(Suggest::class))->newInstanceWithoutConstructor();
        $block->setData($data);

        return $block;
    }
}
