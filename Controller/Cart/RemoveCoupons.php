<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Controller\Cart;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\RequestInterface;
use Merlin\MultiCoupon\Api\MultiCouponManagementInterface;

class RemoveCoupon implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly MultiCouponManagementInterface $management,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        $redirect->setPath('checkout/cart');

        try {
            $code = (string)$this->request->getParam('coupon_code');
            $response = $this->management->removeForCustomer($code);
            $this->messageManager->addSuccessMessage(__($response->getMessage()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('The coupon code could not be removed.'));
        }

        return $redirect;
    }
}
