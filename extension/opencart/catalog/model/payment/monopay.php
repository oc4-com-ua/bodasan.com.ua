<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Payment;

/**
 * Class Monopay
 *
 * Can be called from $this->load->model('extension/opencart/payment/monopay');
 *
 * @package Opencart\Catalog\Model\Extension\Opencart\Payment
 */
class Monopay extends \Opencart\System\Engine\Model {
    /**
     * Get Methods
     *
     * @return array<string, mixed>
     */
    public function getMethods(): array {
        $this->load->language('extension/opencart/payment/monopay');

        $status = $this->cart->hasProducts() && ($this->cart->hasStock() || $this->config->get('config_stock_checkout'));

        $method_data = [];

        if ($status) {
            $option_data['monopay'] = [
                'code' => 'monopay.monopay',
                'name' => $this->language->get('heading_title')
            ];

            $method_data = [
                'code'       => 'monopay',
                'name'       => $this->language->get('heading_title'),
                'option'     => $option_data,
                'sort_order' => $this->config->get('payment_monopay_sort_order')
            ];
        }

        return $method_data;
    }
}
