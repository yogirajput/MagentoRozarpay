<?php
declare(strict_types=1);

namespace Yogesh\Razorpay\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

/** Razorpay Checkout is completed in the browser after the Magento order is placed. */
class Method extends AbstractMethod
{
    public const CODE = 'yogesh_razorpay';

    protected $_code = self::CODE;
    protected $_isInitializeNeeded = true;
    protected $_canOrder = true;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canRefund = false;
    protected $_canVoid = false;

    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
        return $this;
    }
}
