<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Shipping;

class UkrPoshta extends \Opencart\System\Engine\Model {
    public function getQuote(array $address): array {
        $status = $this->config->get('shipping_ukr_poshta_status');

        $method_data = [];

        if ($status) {
            $quote_data = [];

            $quote_data['ukr_poshta'] = [
                'code'         => 'ukr_poshta.ukr_poshta',
                'name'         => 'Укрпошта',
                'cost'         => 0.00,
                'tax_class_id' => 0,
                'text'         => $this->currency->format(0.00, $this->session->data['currency'])
            ];

            $method_data = [
                'code'       => 'ukr_poshta',
                'name'       => 'Укрпошта',
                'quote'      => $quote_data,
                'sort_order' => $this->config->get('shipping_ukr_poshta_sort_order'),
                'error'      => false
            ];
        }

        return $method_data;
    }
}
