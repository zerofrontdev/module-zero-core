<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Order;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
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
use Psr\Log\LoggerInterface;

/**
 * A customer cancels their own order (the dialog of Magento_OrderCancellationUi
 * in the Zero theme posts here with the form key): the same rules as stock's
 * GraphQL `cancelOrder` mutation (signed-in owner, cancellation switched on
 * for the store view, an order that can still be cancelled, a reason from
 * the configured list), without a script on the page. The reason comes as its
 * key in that list (reason1, reason2...), so a translated label never has to match.
 * Answers with a message on the order's page.
 */
class Cancel implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $session,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Config $config,
        private readonly CustomerCanCancel $customerCanCancel,
        private readonly CancelOrder $cancelOrder,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messages,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        if (!$this->session->isLoggedIn()) {
            return $redirect->setPath('customer/account/login');
        }
        $orderId = (int) $this->request->getParam('order_id');
        $redirect->setPath('sales/order/view', ['order_id' => $orderId]);

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            return $this->refuse($redirect->setPath('sales/order/history'));
        }
        // Someone else's order looks like a missing one.
        if ((int) $order->getCustomerId() !== (int) $this->session->getCustomerId()) {
            return $this->refuse($redirect->setPath('sales/order/history'));
        }
        $reasons = $this->config->getCancellationReasons($order->getStore());
        $reason = $reasons[(string) $this->request->getParam('reason')] ?? null;
        if (!$this->config->isOrderCancellationEnabledForStore((int) $order->getStoreId())
            || !$this->customerCanCancel->execute($order)
            || $reason === null
        ) {
            $this->messages->addErrorMessage((string) __('This order can\'t be cancelled.'));

            return $redirect;
        }
        try {
            $this->cancelOrder->execute($order, (string) $reason);
            $this->messages->addSuccessMessage((string) __('Order %1 was cancelled.', $order->getRealOrderId()));
        } catch (LocalizedException $e) {
            $this->messages->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Zero cancel order ' . $orderId . ': ' . $e->getMessage());
            $this->messages->addErrorMessage((string) __('Could not cancel this order. Please try again later.'));
        }

        return $redirect;
    }

    private function refuse(Redirect $redirect): Redirect
    {
        $this->messages->addErrorMessage((string) __('This order can\'t be cancelled.'));

        return $redirect;
    }
}
