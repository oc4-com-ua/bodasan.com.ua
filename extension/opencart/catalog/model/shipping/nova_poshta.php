<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Shipping;

class NovaPoshta extends \Opencart\System\Engine\Model {
    public function getQuote(array $address): array {
        $status = $this->config->get('shipping_nova_poshta_status');

        $method_data = [];

        if ($status) {
            $quote_data = [];

            $quote_data['nova_poshta'] = [
                'code'         => 'nova_poshta.nova_poshta',
                'name'         => 'Нова пошта',
                'cost'         => 0.00,
                'tax_class_id' => 0,
                'text'         => $this->currency->format(0.00, $this->session->data['currency'])
            ];

            $method_data = [
                'code'       => 'nova_poshta',
                'name'       => 'Нова пошта',
                'quote'      => $quote_data,
                'sort_order' => $this->config->get('shipping_nova_poshta_sort_order'),
                'error'      => false
            ];
        }

        return $method_data;
    }
}
