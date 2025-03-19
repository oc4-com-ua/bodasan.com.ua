<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Payment;

/**
 * Class LiqPay
 *
 * Can be called from $this->load->model('extension/opencart/payment/liqpay');
 *
 * @package Opencart\Catalog\Model\Extension\Opencart\Payment
 */
class LiqPay extends \Opencart\System\Engine\Model {
    /**
     * Get Methods
     *
     * @return array<string, mixed>
     */
    public function getMethods(): array {
        $this->load->language('extension/opencart/payment/liqpay');

        $status = $this->cart->hasProducts() && ($this->cart->hasStock() || $this->config->get('config_stock_checkout'));

        $method_data = [];

        if ($status) {
            $option_data['liqpay'] = [
                'code' => 'liqpay.liqpay',
                'name' => $this->language->get('heading_title')
            ];

            $method_data = [
                'code'       => 'liqpay',
                'name'       => $this->language->get('heading_title'),
                'option'     => $option_data,
                'sort_order' => $this->config->get('payment_liqpay_sort_order')
            ];
        }

        return $method_data;
    }
}
