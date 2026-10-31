<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Controller\Session;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Result\Layout as ResultLayout;
use Magento\Framework\View\Result\LayoutFactory;
use PHPUnit\Framework\TestCase;
use Zero\Core\Controller\PrivateFragmentActionInterface;
use Zero\Core\Controller\Session\Bootstrap;

class BootstrapTest extends TestCase
{
    private function request(?string $fragments): HttpRequest
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(fn ($name) => $name === 'fragments' ? $fragments : null);

        return $request;
    }

    private function formKey(): FormKey
    {
        $formKey = $this->createStub(FormKey::class);
        $formKey->method('getFormKey')->willReturn('KEY123');

        return $formKey;
    }

    public function testWithoutFragmentsAnswersAsBeforeEmptyBodyAndEvent(): void
    {
        $response = $this->createMock(HttpResponse::class);
        $response->expects($this->once())->method('setHeader')->with('HX-Trigger', 'zero:session-ready');
        $raw = $this->createMock(Raw::class);
        $raw->expects($this->once())->method('setContents')->with('')->willReturnSelf();
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($raw);

        $controller = new Bootstrap($factory, $this->createStub(LayoutFactory::class), $this->request(null), $response, $this->formKey());

        $this->assertInstanceOf(PrivateFragmentActionInterface::class, $controller, 'must never be FPC-cached');
        $this->assertSame($raw, $controller->execute());
    }

    public function testRendersOnlyKnownFragmentsWithTheFormKey(): void
    {
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('renderElement')->willReturnCallback(fn ($name) => "<html of $name>");
        $resultLayout = $this->createMock(ResultLayout::class);
        $resultLayout->expects($this->exactly(2))->method('addHandle')
            ->willReturnCallback(function ($handle) use ($resultLayout) {
                $this->assertContains($handle, ['zero_checkout_cartcounter', 'zero_theme_messages']);
                return $resultLayout;
            });
        $resultLayout->method('getLayout')->willReturn($layout);
        $layoutFactory = $this->createStub(LayoutFactory::class);
        $layoutFactory->method('create')->willReturn($resultLayout);

        $json = $this->createMock(Json::class);
        $json->expects($this->once())->method('setData')->with($this->callback(function (array $data) {
            $this->assertSame('KEY123', $data['formKey']);
            $this->assertEquals((object) [
                'cartcounter' => '<html of zero.checkout.cartcounter>',
                'messages' => '<html of zero.theme.messages>',
            ], $data['fragments']);
            return true;
        }))->willReturnSelf();
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($json);
        $response = $this->createMock(HttpResponse::class);
        $response->expects($this->never())->method('setHeader');

        $controller = new Bootstrap($factory, $layoutFactory, $this->request('cartcounter,evil,messages'), $response, $this->formKey());

        $this->assertSame($json, $controller->execute());
    }

    public function testTheSidebarIsAFragmentOfItsOwnHandle(): void
    {
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('renderElement')->willReturnCallback(fn ($name) => "<html of $name>");
        $resultLayout = $this->createMock(ResultLayout::class);
        $resultLayout->expects($this->once())->method('addHandle')->with('zero_customer_sidebar')->willReturnSelf();
        $resultLayout->method('getLayout')->willReturn($layout);
        $layoutFactory = $this->createStub(LayoutFactory::class);
        $layoutFactory->method('create')->willReturn($resultLayout);
        $json = $this->createMock(Json::class);
        $json->expects($this->once())->method('setData')->with($this->callback(function (array $data) {
            $this->assertEquals((object) ['sidebar' => '<html of zero.customer.sidebar>'], $data['fragments']);
            return true;
        }))->willReturnSelf();
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($json);

        (new Bootstrap($factory, $layoutFactory, $this->request('sidebar'), $this->createStub(HttpResponse::class), $this->formKey()))
            ->execute();
    }

    public function testAFailedSidebarIsLeftOutSoTheBrowserDoesNotKeepIt(): void
    {
        // Production mode: Layout logs the block's exception and renders ''.
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('renderElement')->willReturnCallback(
            fn ($name) => $name === 'zero.customer.sidebar' ? '' : ($name === 'zero.customer.welcome' ? '' : "<html of $name>")
        );
        $resultLayout = $this->createStub(ResultLayout::class);
        $resultLayout->method('addHandle')->willReturnSelf();
        $resultLayout->method('getLayout')->willReturn($layout);
        $layoutFactory = $this->createStub(LayoutFactory::class);
        $layoutFactory->method('create')->willReturn($resultLayout);
        $json = $this->createMock(Json::class);
        $json->expects($this->once())->method('setData')->with($this->callback(function (array $data) {
            // An empty welcome is a real answer; an empty sidebar is a failure.
            $this->assertEquals((object) [
                'welcome' => '',
                'cartcounter' => '<html of zero.checkout.cartcounter>',
            ], $data['fragments']);
            return true;
        }))->willReturnSelf();
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($json);

        (new Bootstrap(
            $factory,
            $layoutFactory,
            $this->request('welcome,cartcounter,sidebar'),
            $this->createStub(HttpResponse::class),
            $this->formKey()
        ))->execute();
    }
}
