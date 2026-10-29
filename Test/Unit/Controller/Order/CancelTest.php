<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Controller\Order;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\OrderCancellation\Model\CancelOrder;
use Magento\OrderCancellation\Model\Config\Config;
use Magento\OrderCancellation\Model\CustomerCanCancel;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zero\Core\Controller\Order\Cancel;

class CancelTest extends TestCase
{
    private RequestInterface&MockObject $request;
    private CustomerSession&MockObject $session;
    private OrderRepositoryInterface&MockObject $orders;
    private Config&MockObject $config;
    private CustomerCanCancel&MockObject $canCancel;
    private CancelOrder&MockObject $cancelOrder;
    private Redirect&MockObject $redirect;
    private ManagerInterface&MockObject $messages;
    private Order&MockObject $order;
    private Cancel $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getParam')->willReturnMap([['order_id', null, '42'], ['reason', null, 'reason2']]);
        $this->session = $this->createMock(CustomerSession::class);
        $this->session->method('isLoggedIn')->willReturn(true);
        $this->session->method('getCustomerId')->willReturn(7);

        $store = $this->createMock(Store::class);
        $this->order = $this->createMock(Order::class);
        $this->order->method('getCustomerId')->willReturn(7);
        $this->order->method('getStore')->willReturn($store);
        $this->order->method('getStoreId')->willReturn(1);
        $this->order->method('getRealOrderId')->willReturn('000000042');
        $this->orders = $this->createMock(OrderRepositoryInterface::class);
        $this->orders->method('get')->willReturn($this->order);

        $this->config = $this->createMock(Config::class);
        $this->config->method('getCancellationReasons')->willReturn(['reason1' => 'Changed my mind', 'reason2' => 'Ordered by mistake']);
        $this->config->method('isOrderCancellationEnabledForStore')->willReturn(true);
        $this->canCancel = $this->createMock(CustomerCanCancel::class);
        $this->canCancel->method('execute')->willReturn(true);
        $this->cancelOrder = $this->createMock(CancelOrder::class);

        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();
        $factory = $this->createMock(RedirectFactory::class);
        $factory->method('create')->willReturn($this->redirect);
        $this->messages = $this->createMock(ManagerInterface::class);

        $this->controller = new Cancel(
            $this->request,
            $this->session,
            $this->orders,
            $this->config,
            $this->canCancel,
            $this->cancelOrder,
            $factory,
            $this->messages,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testTheOwnerCancelsWithTheReasonOfTheGivenKey(): void
    {
        $this->cancelOrder->expects(self::once())->method('execute')->with($this->order, 'Ordered by mistake');
        $this->messages->expects(self::once())->method('addSuccessMessage');
        $this->messages->expects(self::never())->method('addErrorMessage');

        $this->controller->execute();
    }

    public function testAGuestIsSentToTheLoginAndNothingIsCancelled(): void
    {
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn(false);
        $controller = $this->controllerWith(session: $session);
        $this->redirect->expects(self::once())->method('setPath')->with('customer/account/login');
        $this->cancelOrder->expects(self::never())->method('execute');

        $controller->execute();
    }

    public function testSomeoneElsesOrderIsRefusedLikeAMissingOne(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getCustomerId')->willReturn(99);
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('get')->willReturn($order);
        $controller = $this->controllerWith(orders: $orders);
        $this->cancelOrder->expects(self::never())->method('execute');
        $this->messages->expects(self::once())->method('addErrorMessage');

        $controller->execute();
    }

    public function testAMissingOrderIsRefused(): void
    {
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('get')->willThrowException(new NoSuchEntityException());
        $controller = $this->controllerWith(orders: $orders);
        $this->cancelOrder->expects(self::never())->method('execute');
        $this->messages->expects(self::once())->method('addErrorMessage');

        $controller->execute();
    }

    public function testAnUnknownReasonKeyIsRefused(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['order_id', null, '42'], ['reason', null, 'reason9']]);
        $controller = $this->controllerWith(request: $request);
        $this->cancelOrder->expects(self::never())->method('execute');
        $this->messages->expects(self::once())->method('addErrorMessage');

        $controller->execute();
    }

    public function testAnOrderThatCannotBeCancelledAnyMoreIsRefused(): void
    {
        $canCancel = $this->createMock(CustomerCanCancel::class);
        $canCancel->method('execute')->willReturn(false);
        $controller = $this->controllerWith(canCancel: $canCancel);
        $this->cancelOrder->expects(self::never())->method('execute');
        $this->messages->expects(self::once())->method('addErrorMessage');

        $controller->execute();
    }

    public function testAFailureOfTheCancellationIsShownAsAMessage(): void
    {
        $this->cancelOrder->method('execute')->willThrowException(new LocalizedException(__('The refund failed')));
        $this->messages->expects(self::once())->method('addErrorMessage')->with('The refund failed');
        $this->messages->expects(self::never())->method('addSuccessMessage');

        $this->controller->execute();
    }

    private function controllerWith(
        ?RequestInterface $request = null,
        ?CustomerSession $session = null,
        ?OrderRepositoryInterface $orders = null,
        ?CustomerCanCancel $canCancel = null
    ): Cancel {
        $factory = $this->createMock(RedirectFactory::class);
        $factory->method('create')->willReturn($this->redirect);

        return new Cancel(
            $request ?? $this->request,
            $session ?? $this->session,
            $orders ?? $this->orders,
            $this->config,
            $canCancel ?? $this->canCancel,
            $this->cancelOrder,
            $factory,
            $this->messages,
            $this->createMock(LoggerInterface::class)
        );
    }
}
