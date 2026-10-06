<?php
declare(strict_types=1);

namespace Yogesh\Razorpay\Model\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\ScopeInterface;
use RuntimeException;

class ApiClient
{
    private const XML_PATH_KEY_ID = 'payment/yogesh_razorpay/key_id';
    private const XML_PATH_KEY_SECRET = 'payment/yogesh_razorpay/key_secret';

    public function __construct(
        private readonly Curl $curl,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getKeyId(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_KEY_ID, ScopeInterface::SCOPE_STORE);
    }

    public function getKeySecret(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_KEY_SECRET, ScopeInterface::SCOPE_STORE);
    }

    public function createOrder(int $amount, string $currency, string $receipt): array
    {
        return $this->request('POST', '/orders', [
            'amount' => $amount,
            'currency' => $currency,
            'receipt' => substr($receipt, 0, 40),
            'payment_capture' => 1,
        ]);
    }

    public function getPayment(string $paymentId): array
    {
        if (!preg_match('/^pay_[A-Za-z0-9]+$/', $paymentId)) {
            throw new RuntimeException('Invalid Razorpay payment identifier.');
        }
        return $this->request('GET', '/payments/' . rawurlencode($paymentId));
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $keyId = $this->getKeyId();
        $secret = $this->getKeySecret();
        if ($keyId === '' || $secret === '') {
            throw new RuntimeException('Razorpay credentials are not configured.');
        }

        $this->curl->setCredentials($keyId, $secret);
        $this->curl->addHeader('Accept', 'application/json');
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->setTimeout(15);
        if ($method === 'POST') {
            $this->curl->post('https://api.razorpay.com/v1' . $path, (string)json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->curl->get('https://api.razorpay.com/v1' . $path);
        }

        $status = $this->curl->getStatus();
        $response = json_decode($this->curl->getBody(), true);
        if ($status < 200 || $status >= 300 || !is_array($response)) {
            throw new RuntimeException('Razorpay could not process the payment request.');
        }
        return $response;
    }
}
