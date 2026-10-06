define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'mage/cookies',
    'jquery',
    'ko'
], function (Component, fullScreenLoader, additionalValidators, cookies, $, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Yogesh_Razorpay/payment/razorpay',
            redirectAfterPlaceOrder: false
        },

        initialize: function () {
            this._super();
            this.message = ko.observable('');
            this.isProcessing = ko.observable(false);
            this.isOrderPlaced = ko.observable(false);
            return this;
        },

        getCode: function () {
            return 'yogesh_razorpay';
        },

        getTitle: function () {
            return window.checkoutConfig.payment.yogesh_razorpay.title;
        },

        getData: function () {
            return {method: this.getCode(), additional_data: {}};
        },

        validate: function () {
            return this._super() && additionalValidators.validate();
        },

        afterPlaceOrder: function () {
            this.isOrderPlaced(true);
            this.startPayment();
        },

        startPayment: function () {
            var self = this,
                config = window.checkoutConfig.payment.yogesh_razorpay;

            if (this.isProcessing()) {
                return;
            }
            this.message('');
            this.isProcessing(true);
            fullScreenLoader.startLoader();
            $.ajax({
                url: config.createOrderUrl,
                type: 'POST',
                dataType: 'json',
                data: {form_key: cookies.get('form_key') || ''}
            }).done(function (response) {
                if (!response.success) {
                    self.fail(response.message);
                    return;
                }
                self.openCheckout(response, config);
            }).fail(function () {
                self.fail('Unable to start Razorpay. Please try ayogesh.');
            });
        },

        openCheckout: function (order, config) {
            var self = this,
                options = {
                    key: order.keyId,
                    amount: order.amount,
                    currency: order.currency,
                    name: order.name,
                    description: order.description,
                    order_id: order.razorpayOrderId,
                    handler: function (response) {
                        self.verifyPayment(response, config);
                    },
                    modal: {
                        ondismiss: function () {
                            self.fail('Payment was not completed. You can try ayogesh.');
                        }
                    }
                };

            if (typeof window.Razorpay === 'undefined') {
                $.getScript('https://checkout.razorpay.com/v1/checkout.js').done(function () {
                    self.showCheckout(options);
                }).fail(function () {
                    self.fail('Razorpay Checkout could not be loaded.');
                });
                return;
            }
            this.showCheckout(options);
        },

        showCheckout: function (options) {
            fullScreenLoader.stopLoader();
            try {
                var checkout = new window.Razorpay(options);
                checkout.on('payment.failed', function () {
                    this.fail('Razorpay reported that the payment failed. Please try ayogesh.');
                }.bind(this));
                checkout.open();
            } catch (error) {
                this.fail('Unable to open Razorpay Checkout.');
            }
        },

        verifyPayment: function (response, config) {
            var self = this;
            fullScreenLoader.startLoader();
            $.ajax({
                url: config.verifyPaymentUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: cookies.get('form_key') || '',
                    razorpay_payment_id: response.razorpay_payment_id,
                    razorpay_order_id: response.razorpay_order_id,
                    razorpay_signature: response.razorpay_signature
                }
            }).done(function (result) {
                if (result.success) {
                    window.location.assign(config.successUrl);
                    return;
                }
                self.fail(result.message);
            }).fail(function (xhr) {
                var responseBody = xhr.responseJSON || {};
                self.fail(responseBody.message || 'Payment verification failed. Contact support before retrying.');
            });
        },

        fail: function (message) {
            fullScreenLoader.stopLoader();
            this.isProcessing(false);
            this.message(message || 'The payment could not be completed.');
        }
    });
});
