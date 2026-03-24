<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Merlin\MultiCoupon\Api\Data\MultiCouponResponseInterface;
use Merlin\MultiCoupon\Api\MultiCouponManagementInterface;
use Merlin\MultiCoupon\Model\MultiCouponResponseFactory;

class MultiCouponManagement implements MultiCouponManagementInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly RuleRepository $ruleRepository,
        private readonly Config $config,
        private readonly MultiCouponResponseFactory $responseFactory
    ) {
    }

    public function getMyCodes(): array
    {
        return $this->quoteCouponStorage->getCodes($this->getActiveQuote());
    }

    public function addForCustomer(string $code): MultiCouponResponseInterface
    {
        $code = $this->config->normalizeCode($code);
        $quote = $this->getActiveQuote();

        if (!$this->config->isAllowedCode($code)) {
            throw new LocalizedException(__('Coupon code %1 is not supported by Merlin Multi Coupon.', $code));
        }

        if (!$this->ruleRepository->getRuleByCode($quote, $code)) {
            throw new LocalizedException(__('Coupon code %1 is not currently valid for this basket.', $code));
        }

        $codes = $this->quoteCouponStorage->addCode($quote, $code);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        return $this->buildResponse(true, (string)__('Coupon code %1 has been added.', $code), $codes);
    }

    public function removeForCustomer(string $code): MultiCouponResponseInterface
    {
        $quote = $this->getActiveQuote();
        $codes = $this->quoteCouponStorage->removeCode($quote, $code);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        return $this->buildResponse(true, (string)__('Coupon code %1 has been removed.', $code), $codes);
    }

    public function clearForCustomer(): MultiCouponResponseInterface
    {
        $quote = $this->getActiveQuote();
        $this->quoteCouponStorage->clearCodes($quote);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        return $this->buildResponse(true, (string)__('All Merlin multi coupon codes have been removed.'), []);
    }

    private function getActiveQuote()
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            throw new NoSuchEntityException(__('No active quote is available.'));
        }
        return $quote;
    }

    private function buildResponse(bool $success, string $message, array $codes): MultiCouponResponseInterface
    {
        $response = $this->responseFactory->create();
        $response->setSuccess($success);
        $response->setMessage($message);
        $response->setCodes($codes);
        return $response;
    }
}
