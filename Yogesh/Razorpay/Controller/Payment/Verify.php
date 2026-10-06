<?php
declare(strict_types=1);

namespace Yogesh\Razorpay\Controller\Payment;

use Yogesh\Razorpay\Model\Gateway\ApiClient;
use Yogesh\Razorpay\Model\Payment\Method;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Throwable;

class Verify implements HttpPostActionInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly Http $request,
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
            $data = (array)$this->request->getPostValue();
            $orderId = (int)$this->checkoutSession->getLastRealOrderId();
            if ($orderId < 1) {
                return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => 'No pending order was found.']);
            }

            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            $paymentId = (string)($data['razorpay_payment_id'] ?? '');
            $remoteOrderId = (string)($data['razorpay_order_id'] ?? '');
            $signature = (string)($data['razorpay_signature'] ?? '');
            $expectedRemoteOrderId = (string)$payment?->getAdditionalInformation('razorpay_order_id');

            if (!$payment || $payment->getMethod() !== Method::CODE || $remoteOrderId === '' || $remoteOrderId !== $expectedRemoteOrderId) {
                return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => 'Payment details do not match this order.']);
            }

            if ($payment->getLastTransId() === $paymentId && $order->getState() === Order::STATE_PROCESSING) {
                return $result->setData(['success' => true]);
            }

            $expectedSignature = hash_hmac('sha256', $remoteOrderId . '|' . $paymentId, $this->apiClient->getKeySecret());
            if ($paymentId === '' || !hash_equals($expectedSignature, $signature)) {
                return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => 'Payment signature verification failed.']);
            }

            $remotePayment = $this->apiClient->getPayment($paymentId);
            $expectedAmount = (int)$payment->getAdditionalInformation('razorpay_amount');
            if (($remotePayment['order_id'] ?? '') !== $remoteOrderId
                || ($remotePayment['status'] ?? '') !== 'captured'
                || (int)($remotePayment['amount'] ?? 0) !== $expectedAmount
                || strtoupper((string)($remotePayment['currency'] ?? '')) !== strtoupper((string)$order->getOrderCurrencyCode())
            ) {
                return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => 'Razorpay has not confirmed a captured payment for this order.']);
            }

            $payment->setTransactionId($paymentId);
            $payment->setIsTransactionClosed(true);
            $payment->setAdditionalInformation('razorpay_payment_id', $paymentId);
            $payment->registerCaptureNotification((float)$order->getGrandTotal());
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
            $order->addCommentToStatusHistory('Razorpay payment captured and verified.');
            $this->orderRepository->save($order);

            return $result->setData(['success' => true]);
        } catch (Throwable $exception) {
            $this->logger->error('Razorpay payment verification failed.', ['exception' => $exception]);
            return $result->setHttpResponseCode(500)->setData(['success' => false, 'message' => 'Unable to verify the payment. Please contact support before retrying.']);
        }
    }
}
