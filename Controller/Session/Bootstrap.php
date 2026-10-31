<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Session;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Result\LayoutFactory;
use Zero\Core\Controller\PrivateFragmentActionInterface;

/**
 * The visitor's session and all per-visitor bits in one request. Pages come
 * from the page cache, so counters, account links, welcome text, messages and
 * form_key load afterwards: once one request each (and waiting on the session
 * lock), now session.js asks `?fragments=cartcounter,welcome,...` and gets
 * {"fragments": {name: html}, "formKey": "..."}. Each fragment's controller
 * still serves later refreshes.
 *
 * It also creates the session first: parallel requests on a first visit made
 * their own, and the page's form_key was another one's ("Invalid Form Key").
 * Without `fragments` (an older cached page): empty body + zero:session-ready.
 */
class Bootstrap implements PrivateFragmentActionInterface
{
    /** Fragment name => [layout handle, block] — the same as each fragment's own controller. */
    private const FRAGMENTS = [
        'messages' => ['zero_theme_messages', 'zero.theme.messages'],
        'welcome' => ['zero_customer_welcome', 'zero.customer.welcome'],
        'accountlinks' => ['zero_customer_accountlinks', 'top.links'],
        'cartcounter' => ['zero_checkout_cartcounter', 'zero.checkout.cartcounter'],
        'wishlistcounter' => ['zero_customer_wishlistcounter', 'zero.customer.wishlistcounter'],
        'comparecounter' => ['zero_customer_comparecounter', 'zero.customer.comparecounter'],
        // The sidebar's Compare Products / My Wish List (category pages): no request of its own (2026-10-31).
        'sidebar' => ['zero_customer_sidebar', 'zero.customer.sidebar'],
    ];

    /**
     * Fragments whose block always renders something (the sidebar's wrapper). An empty one means the
     * block threw: Layout::renderNonCachedElement logs it and returns '' outside developer mode. It is
     * left out of the answer, so session.js does not keep it for 20 minutes and the next page asks again.
     */
    private const NEVER_EMPTY = ['sidebar'];

    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly HttpRequest $request,
        private readonly HttpResponse $response,
        private readonly FormKey $formKey
    ) {
    }

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        $requested = $this->request->getParam('fragments');
        if (!is_string($requested)) {
            $this->response->setHeader('HX-Trigger', 'zero:session-ready');

            return $this->resultFactory->create(ResultFactory::TYPE_RAW)->setContents('');
        }

        $names = array_values(array_intersect(array_keys(self::FRAGMENTS), explode(',', $requested)));
        $html = [];
        if ($names) {
            $resultLayout = $this->resultLayoutFactory->create();
            foreach ($names as $name) {
                $resultLayout->addHandle(self::FRAGMENTS[$name][0]);
            }
            $layout = $resultLayout->getLayout();
            foreach ($names as $name) {
                $fragment = (string) $layout->renderElement(self::FRAGMENTS[$name][1]);
                if ($fragment !== '' || !in_array($name, self::NEVER_EMPTY, true)) {
                    $html[$name] = $fragment;
                }
            }
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData([
            'fragments' => (object) $html,
            'formKey' => $this->formKey->getFormKey(),
        ]);
    }
}
