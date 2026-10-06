<?php
declare(strict_types=1);

namespace Yogesh\Razorpay\Controller\Payment;

use Yogesh\Razorpay\Model\Gateway\ApiClient;
use Yogesh\Razorpay\Model\Payment\Method;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class CreateOrder implements HttpPostActionInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ApiClient $apiClient,
        private readonly JsonFactory $jsonFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            $orderId = (int)$this->checkoutSession->getLastRealOrderId();
            if ($orderId < 1) {
                return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => 'No pending order was found.']);
            }

            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            if (!$payment || $payment->getMethod() !== Method::CODE || !$order->canInvoice()) {
                return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => 'This order cannot be paid with Razorpay.']);
            }
            if (strtoupper((string)$order->getOrderCurrencyCode()) !== 'INR') {
                return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => 'This Razorpay integration currently supports INR orders only.']);
            }

            $remoteOrderId = (string)$payment->getAdditionalInformation('razorpay_order_id');
            if ($remoteOrderId === '') {
                $amount = (int)round((float)$order->getGrandTotal() * 100);
                if ($amount < 1) {
                    return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => 'The order amount is invalid.']);
                }
                $remoteOrder = $this->apiClient->createOrder($amount, (string)$order->getOrderCurrencyCode(), (string)$order->getIncrementId());
                $remoteOrderId = (string)($remoteOrder['id'] ?? '');
                if (!preg_match('/^order_[A-Za-z0-9]+$/', $remoteOrderId)) {
                    throw new \RuntimeException('Razorpay returned an invalid order identifier.');
                }
                $payment->setAdditionalInformation('razorpay_order_id', $remoteOrderId);
                $payment->setAdditionalInformation('razorpay_amount', $amount);
                $this->orderRepository->save($order);
            }

            return $result->setData([
                'success' => true,
                'keyId' => $this->apiClient->getKeyId(),
                'razorpayOrderId' => $remoteOrderId,
                'amount' => (int)$payment->getAdditionalInformation('razorpay_amount'),
                'currency' => (string)$order->getOrderCurrencyCode(),
                'name' => (string)$order->getStoreName(),
                'description' => 'Order ' . (string)$order->getIncrementId(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Razorpay order creation failed.', ['exception' => $exception]);
            return $result->setHttpResponseCode(500)->setData(['success' => false, 'message' => 'Unable to start the Razorpay payment. Please try ayogesh.']);
        }
    }
}
