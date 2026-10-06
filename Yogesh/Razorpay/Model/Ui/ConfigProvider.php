<?php
declare(strict_types=1);

namespace Yogesh\Razorpay\Model\Ui;

use Yogesh\Razorpay\Model\Payment\Method;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ConfigProviderInterface
{
    private const XML_PATH_TITLE = 'payment/' . Method::CODE . '/title';
    private const XML_PATH_ACTIVE = 'payment/' . Method::CODE . '/active';
    private const XML_PATH_KEY_ID = 'payment/' . Method::CODE . '/key_id';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function getConfig(): array
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE)) {
            return [];
        }

        return [
            'payment' => [
                Method::CODE => [
                    'title' => (string)$this->scopeConfig->getValue(self::XML_PATH_TITLE, ScopeInterface::SCOPE_STORE),
                    'keyId' => (string)$this->scopeConfig->getValue(self::XML_PATH_KEY_ID, ScopeInterface::SCOPE_STORE),
                    'createOrderUrl' => $this->urlBuilder->getUrl('razorpay/payment/create-order'),
                    'verifyPaymentUrl' => $this->urlBuilder->getUrl('razorpay/payment/verify'),
                    'successUrl' => $this->urlBuilder->getUrl('checkout/onepage/success'),
                ],
            ],
        ];
    }
}
