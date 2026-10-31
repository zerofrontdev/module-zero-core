<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Controller\Sidebar;

use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Zero\Core\Block\Sidebar;
use Zero\Core\Controller\PrivateFragmentActionInterface;
use Zero\Core\Controller\Sidebar\Index;

/**
 * The old shell's endpoint (pages cached before the sidebar became a session fragment): the block's items, no wrapper.
 */
class IndexTest extends TestCase
{
    public function testAnswersTheBlocksItemsWithoutTheWrapper(): void
    {
        $sidebar = $this->createStub(Sidebar::class);
        $sidebar->method('itemsHtml')->willReturn('<compare/>');
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->once())->method('createBlock')->with(Sidebar::class)->willReturn($sidebar);
        $layoutFactory = $this->createStub(LayoutFactory::class);
        $layoutFactory->method('create')->willReturn($layout);
        $raw = $this->createMock(Raw::class);
        $raw->expects($this->once())->method('setContents')->with('<compare/>')->willReturnSelf();
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($raw);

        $controller = new Index($resultFactory, $layoutFactory);

        $this->assertInstanceOf(PrivateFragmentActionInterface::class, $controller, 'must never be FPC-cached');
        $this->assertSame($raw, $controller->execute());
    }
}
