<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Merlin\MultiCoupon\Api\Data\MultiCouponResponseInterface;
use Merlin\MultiCoupon\Api\Data\MultiCouponResponseInterfaceFactory;
use Merlin\MultiCoupon\Api\MultiCouponManagementInterface;

class MultiCouponManagement implements MultiCouponManagementInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteCouponStorage $quoteCouponStorage
     * @param Config $config
     * @param MultiCouponResponseInterfaceFactory $responseFactory
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly Config $config,
        private readonly MultiCouponResponseInterfaceFactory $responseFactory
    ) {
    }

    /**
     * Return the current quote's applied multi-coupon codes.
     *
     * @return string[]
     */
    public function getMyCodes(): array
    {
        return $this->quoteCouponStorage->getCodes($this->getActiveQuote());
    }

    /**
     * Add a coupon code to the active quote.
     *
     * @param string $code
     * @return MultiCouponResponseInterface
     */
    public function addForCustomer(string $code): MultiCouponResponseInterface
    {
        $code = $this->config->normalizeCode($code);
        if ($code === '' || !$this->config->isAllowedCode($code)) {
            return $this->buildResponse(false, 'Coupon code is not allowed.', $this->getMyCodes());
        }

        $quote = $this->getActiveQuote();
        $codes = $this->quoteCouponStorage->addCode($quote, $code);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        return $this->buildResponse(true, 'Coupon code added.', $codes);
    }

    /**
     * Remove a coupon code from the active quote.
     *
     * @param string $code
     * @return MultiCouponResponseInterface
     */
    public function removeForCustomer(string $code): MultiCouponResponseInterface
    {
        $quote = $this->getActiveQuote();
        $codes = $this->quoteCouponStorage->removeCode($quote, $code);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        return $this->buildResponse(true, 'Coupon code removed.', $codes);
    }

    /**
     * Clear all coupon codes from the active quote.
     *
     * @return MultiCouponResponseInterface
     */
    public function clearForCustomer(): MultiCouponResponseInterface
    {
        $quote = $this->getActiveQuote();
        $this->quoteCouponStorage->clearCodes($quote);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        return $this->buildResponse(true, 'Coupon codes cleared.', []);
    }

    /**
     * Return the active checkout quote.
     *
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws LocalizedException
     */
    private function getActiveQuote()
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            throw new LocalizedException(__('No active cart was found.'));
        }

        return $quote;
    }

    /**
     * Build a service response payload.
     *
     * @param bool $success
     * @param string $message
     * @param string[] $codes
     * @return MultiCouponResponseInterface
     */
    private function buildResponse(bool $success, string $message, array $codes): MultiCouponResponseInterface
    {
        /** @var MultiCouponResponseInterface $response */
        $response = $this->responseFactory->create();
        $response->setSuccess($success)
            ->setMessage($message)
            ->setCodes($codes);

        return $response;
    }
}
