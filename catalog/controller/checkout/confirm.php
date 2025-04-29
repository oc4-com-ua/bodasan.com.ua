<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class Confirm
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class Confirm extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('checkout/confirm');

		// Order Totals
		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$this->load->model('checkout/cart');

		($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

		$status = ($this->customer->isLogged() || !$this->config->get('config_customer_price'));

		// Validate customer data is set
		if (!isset($this->session->data['customer'])) {
			$status = false;
		}

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$status = false;
		}

		// Shipping
		if ($this->cart->hasShipping()) {
			// Validate shipping address
			/*if (!isset($this->session->data['shipping_address']['address_id'])) {
				$status = false;
			}*/

			// Validate shipping method
			if (!isset($this->session->data['shipping_method'])) {
				$status = false;
			}
		} else {
//			unset($this->session->data['shipping_address']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
		}

		// Validate has payment address if required
		/*if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
			$status = false;
		}*/

		// Validate payment methods
		if (!isset($this->session->data['payment_method'])) {
			$status = false;
		}

		// Validate checkout terms
		if ($this->config->get('config_checkout_id') && empty($this->session->data['agree'])) {
			$status = false;
		}

		if (isset($this->session->data['order_id'])) {
			$order_id = $this->session->data['order_id'];
		} else {
			$order_id = 0;
		}

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if ($order_id && !$order_info) {
			unset($this->session->data['order_id']);
		}

		// Generate order if payment method is set
		if ($status) {
			$order_data = [];

			$order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
			$order_data['subscription_id'] = 0;

			// Store Details
			$order_data['store_id'] = $this->config->get('config_store_id');
			$order_data['store_name'] = $this->config->get('config_name');
			$order_data['store_url'] = $this->config->get('config_url');

			// Customer Details
			$order_data['customer_id'] = $this->session->data['customer']['customer_id'];
			$order_data['customer_group_id'] = $this->session->data['customer']['customer_group_id'];
			$order_data['firstname'] = $this->session->data['customer']['firstname'];
			$order_data['lastname'] = $this->session->data['customer']['lastname'];
			$order_data['email'] = $this->session->data['customer']['email'];
			$order_data['telephone'] = $this->session->data['customer']['telephone'];
			$order_data['custom_field'] = $this->session->data['customer']['custom_field'];

			// Payment Details
			/*if ($this->config->get('config_checkout_payment_address')) {
				$order_data['payment_address_id'] = $this->session->data['payment_address']['address_id'];
				$order_data['payment_firstname'] = $this->session->data['payment_address']['firstname'];
				$order_data['payment_lastname'] = $this->session->data['payment_address']['lastname'];
				$order_data['payment_company'] = $this->session->data['payment_address']['company'];
				$order_data['payment_address_1'] = $this->session->data['payment_address']['address_1'];
				$order_data['payment_address_2'] = $this->session->data['payment_address']['address_2'];
				$order_data['payment_city'] = $this->session->data['payment_address']['city'];
				$order_data['payment_postcode'] = $this->session->data['payment_address']['postcode'];
				$order_data['payment_zone'] = $this->session->data['payment_address']['zone'];
				$order_data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
				$order_data['payment_country'] = $this->session->data['payment_address']['country'];
				$order_data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
				$order_data['payment_address_format'] = $this->session->data['payment_address']['address_format'];
				$order_data['payment_custom_field'] = $this->session->data['payment_address']['custom_field'] ?? [];
			} else {
				$order_data['payment_address_id'] = 0;
				$order_data['payment_firstname'] = '';
				$order_data['payment_lastname'] = '';
				$order_data['payment_company'] = '';
				$order_data['payment_address_1'] = '';
				$order_data['payment_address_2'] = '';
				$order_data['payment_city'] = '';
				$order_data['payment_postcode'] = '';
				$order_data['payment_zone'] = '';
				$order_data['payment_zone_id'] = 0;
				$order_data['payment_country'] = '';
				$order_data['payment_country_id'] = 0;
				$order_data['payment_address_format'] = '';
				$order_data['payment_custom_field'] = [];
			}*/

			$order_data['payment_method'] = $this->session->data['payment_method'];

			// Shipping Details
			if ($this->cart->hasShipping()) {
				/*$order_data['shipping_address_id'] = $this->session->data['shipping_address']['address_id'];
				$order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
				$order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
				$order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
				$order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
				$order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
				$order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
				$order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
				$order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
				$order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
				$order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
				$order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
				$order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
				$order_data['shipping_custom_field'] = $this->session->data['shipping_address']['custom_field'] ?? [];*/

				$order_data['shipping_method'] = $this->session->data['shipping_method'];
			} else {
				/*$order_data['shipping_address_id'] = 0;
				$order_data['shipping_firstname'] = '';
				$order_data['shipping_lastname'] = '';
				$order_data['shipping_company'] = '';
				$order_data['shipping_address_1'] = '';
				$order_data['shipping_address_2'] = '';
				$order_data['shipping_city'] = '';
				$order_data['shipping_postcode'] = '';
				$order_data['shipping_zone'] = '';
				$order_data['shipping_zone_id'] = 0;
				$order_data['shipping_country'] = '';
				$order_data['shipping_country_id'] = 0;
				$order_data['shipping_address_format'] = '';
				$order_data['shipping_custom_field'] = [];*/

				$order_data['shipping_method'] = [];
			}

			if (isset($this->session->data['comment'])) {
				$order_data['comment'] = $this->session->data['comment'];
			} else {
				$order_data['comment'] = '';
			}

			$total_data = [
				'totals' => $totals,
				'taxes'  => $taxes,
				'total'  => $total
			];

			$order_data = array_merge($order_data, $total_data);

			$order_data['affiliate_id'] = 0;
			$order_data['commission'] = 0;
			$order_data['marketing_id'] = 0;
			$order_data['tracking'] = '';

			if (isset($this->session->data['tracking'])) {
				$subtotal = $this->cart->getSubTotal();

				// Affiliate
				if ($this->config->get('config_affiliate_status')) {
					$this->load->model('account/affiliate');

					$affiliate_info = $this->model_account_affiliate->getAffiliateByTracking($this->session->data['tracking']);

					if ($affiliate_info) {
						$order_data['affiliate_id'] = $affiliate_info['customer_id'];
						$order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
						$order_data['tracking'] = $this->session->data['tracking'];
					}
				}

				$this->load->model('marketing/marketing');

				$marketing_info = $this->model_marketing_marketing->getMarketingByCode($this->session->data['tracking']);

				if ($marketing_info) {
					$order_data['marketing_id'] = $marketing_info['marketing_id'];
					$order_data['tracking'] = $this->session->data['tracking'];
				}
			}

			$order_data['language_id'] = $this->config->get('config_language_id');
			$order_data['language_code'] = $this->config->get('config_language');

			$order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
			$order_data['currency_code'] = $this->session->data['currency'];
			$order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);

			$order_data['ip'] = oc_get_ip();

			if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
				$order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
			} elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
				$order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
			} else {
				$order_data['forwarded_ip'] = '';
			}

			if (isset($this->request->server['HTTP_USER_AGENT'])) {
				$order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
			} else {
				$order_data['user_agent'] = '';
			}

			if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
				$order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
			} else {
				$order_data['accept_language'] = '';
			}

			// Products
			$order_data['products'] = [];

			// Use cart products to get data for order
			$products = $this->cart->getProducts();

			foreach ($products as $product) {
				$subscription_data = [];

				if ($product['subscription']) {
					$subscription_data = [
						'trial_tax' => $this->tax->getTax($product['subscription']['trial_price'], $product['tax_class_id']),
						'tax'       => $this->tax->getTax($product['subscription']['price'], $product['tax_class_id'])
					] + $product['subscription'];
				}

				$order_data['products'][] = [
					'subscription' => $subscription_data,
					'tax'          => $this->tax->getTax($product['price'], $product['tax_class_id'])
				] + $product;
			}

			if (!$order_id) {
				$this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);
			} elseif ($order_info && !$order_info['order_status_id']) {
				$this->model_checkout_order->editOrder($order_id, $order_data);
			}
		}

		// Display prices
		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			$price_status = true;
		} else {
			$price_status = false;
		}

		$data['products'] = [];

		// Use model cart products to get data for template
		$products = $this->model_checkout_cart->getProducts();

		foreach ($products as $product) {
			if ($product['option']) {
				foreach ($product['option'] as $key => $option) {
					$product['option'][$key]['value'] = (oc_strlen($option['value']) > 20 ? oc_substr($option['value'], 0, 20) . '..' : $option['value']);
				}
			}

			$subscription = '';

			if ($product['subscription']) {
				if ($product['subscription']['trial_status']) {
					$subscription .= sprintf($this->language->get('text_subscription_trial'), $product['subscription']['trial_price_text'], $product['subscription']['trial_cycle'], $product['subscription']['trial_frequency_text'], $product['subscription']['trial_duration']);
				}

				if ($product['subscription']['duration']) {
					$subscription .= sprintf($this->language->get('text_subscription_duration'), $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency_text'], $product['subscription']['duration']);
				} else {
					$subscription .= sprintf($this->language->get('text_subscription_cancel'), $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency_text']);
				}
			}

			$data['products'][] = [
				'subscription' => $subscription,
				'price'        => $price_status ? $product['price_text'] : '',
				'total'        => $price_status ? $product['total_text'] : '',
				'href'         => $this->url->link('product/product', 'product_id=' . $product['product_id'])
			] + $product;
		}

		$data['totals'] = [];

		foreach ($totals as $total) {
			$data['totals'][] = ['text' => $this->currency->format($total['value'], $this->session->data['currency'])] + $total;
		}

		// Validate if payment method has been set.
		if (isset($this->session->data['payment_method'])) {
			$code = oc_substr($this->session->data['payment_method']['code'], 0, strpos($this->session->data['payment_method']['code'], '.'));
		} else {
			$code = '';
		}

		$extension_info = $this->model_setting_extension->getExtensionByCode('payment', $code);

		if ($status && $extension_info) {
			$data['payment'] = $this->load->controller('extension/' . $extension_info['extension'] . '/payment/' . $extension_info['code']);
		} else {
			$data['payment'] = '';
		}

		// Validate if payment method has been set.
		return $this->load->view('checkout/confirm', $data);
	}

	/**
	 * Confirm
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->response->setOutput($this->index());
	}




    public function simpleCheckout(): void {
        $json = [];

        $this->load->language('checkout/simple_checkout');

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $firstname = $this->request->post['firstname'] ?? '';
            $lastname = $this->request->post['lastname'] ?? '';
            $telephone = $this->request->post['telephone'] ?? '';
            $shipping_method = json_decode(htmlspecialchars_decode($this->request->post['shipping_method']), true);
            $shipping_custom_field = '';

            if ($shipping_method['code'] === 'nova_poshta.nova_poshta') {
                $deliveryType = $this->request->post['np_delivery_type'] ?? '';

                $np_data = [
                    'delivery_type' => $deliveryType,
                    'city' => $this->request->post['np_city'] ?? '',
                    'city_ref' => $this->request->post['np_city_ref'] ?? '',
                ];

                switch ($deliveryType) {
                    case 'branch':
                        $np_data['branch'] = $this->request->post['np_branch'] ?? null;
                        $np_data['branch_ref'] = $this->request->post['np_branch_ref'] ?? null;
                        break;

                    case 'postamat':
                        $np_data['postamat'] = $this->request->post['np_postamat'] ?? null;
                        $np_data['postamat_ref'] = $this->request->post['np_postamat_ref'] ?? null;
                        break;

                    case 'courier':
                        $np_data['street'] = $this->request->post['np_courier_street'] ?? null;
                        $np_data['street_ref'] = $this->request->post['np_courier_street_ref'] ?? null;
                        $np_data['house'] = $this->request->post['np_courier_house'] ?? null;
                        $np_data['flat'] = $this->request->post['np_courier_flat'] ?? null;
                        break;
                }

                $shipping_custom_field = $np_data;
            }

            if ($shipping_method['code'] === 'ukr_poshta.ukr_poshta') {
                $deliveryType = $this->request->post['ukr_delivery_type'] ?? '';

                $ukr_data = [
                    'delivery_type' => $deliveryType,
                    'city' => $this->request->post['ukr_city'] ?? '',
                    'city_id' => $this->request->post['ukr_city_id'] ?? '',
                    'region_id' => $this->request->post['ukr_region_id'] ?? '',
                    'district_id' => $this->request->post['ukr_district_id'] ?? '',
                ];

                switch ($deliveryType) {
                    case 'branch':
                        $ukr_data['branch'] = $this->request->post['ukr_branch'] ?? null;
                        $ukr_data['branch_index'] = $this->request->post['ukr_branch_index'] ?? null;
                        break;

                    case 'courier':
                        $ukr_data['courier_street'] = $this->request->post['ukr_courier_street'] ?? null;
                        $ukr_data['courier_street_id'] = $this->request->post['ukr_courier_street_id'] ?? null;
                        $ukr_data['courier_house'] = $this->request->post['ukr_courier_house'] ?? null;
                        $ukr_data['courier_house_index'] = $this->request->post['ukr_courier_house_index'] ?? null;
                        $ukr_data['courier_flat'] = $this->request->post['ukr_courier_flat'] ?? null;
                        break;
                }

                $shipping_custom_field = $ukr_data;
            }

            $payment_method = json_decode(htmlspecialchars_decode($this->request->post['payment_method']), true);
            $comment = $this->request->post['comment'] ?? '';

            if (empty($firstname) || empty($lastname) || empty($telephone)) {
                $json['error'] = $this->language->get('error_required_fields');
            } else {
                $totals = [];
                $taxes = $this->cart->getTaxes();
                $total = 0;

                $this->load->model('checkout/cart');
                ($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

                $order_data = [
                    'invoice_prefix'      => $this->config->get('config_invoice_prefix'),
                    'store_id'            => $this->config->get('config_store_id'),
                    'store_name'          => $this->config->get('config_name'),
                    'store_url'           => $this->config->get('config_url'),
                    'subscription_id'     => 0,
                    'customer_id'         => 0,
                    'customer_group_id'   => $this->config->get('config_customer_group_id'),
                    'firstname'           => $firstname,
                    'lastname'            => $lastname,
                    'email'               => 'no-email@example.com', // OpenCart вимагає email, тому ставимо заглушку
                    'telephone'           => $telephone,
                    'custom_field'        => [],
                    'payment_address_id'  => 0,
                    'payment_firstname'   => $firstname,
                    'payment_lastname'    => $lastname,
                    'payment_company'     => '',
                    'payment_address_1'   => '',
                    'payment_address_2'   => '',
                    'payment_city'        => '',
                    'payment_postcode'    => '',
                    'payment_country'     => '',
                    'payment_country_id'  => 0,
                    'payment_zone'        => '',
                    'payment_zone_id'     => 0,
                    'payment_address_format' => '',
                    'payment_custom_field'   => [],
                    'payment_method'      => $payment_method,
                    'shipping_address_id'  => 0,
                    'shipping_firstname'   => '',
                    'shipping_lastname'    => '',
                    'shipping_company'     => '',
                    'shipping_address_1'   => '',
                    'shipping_address_2'   => '',
                    'shipping_city'        => '',
                    'shipping_postcode'    => '',
                    'shipping_country'     => '',
                    'shipping_country_id'  => 0,
                    'shipping_zone'        => '',
                    'shipping_zone_id'     => 0,
                    'shipping_address_format' => '',
                    'shipping_custom_field'   => $shipping_custom_field,
                    'shipping_method'      => $shipping_method,
                    'affiliate_id'         => 0,
                    'commission'           => 0,
                    'marketing_id'         => 0,
                    'tracking'             => '',
                    'currency_id'         => $this->currency->getId($this->session->data['currency']),
                    'currency_code'       => $this->session->data['currency'],
                    'currency_value'      => $this->currency->getValue($this->session->data['currency']),
                    'language_id'         => $this->config->get('config_language_id'),
                    'language_code'       => $this->config->get('config_language'),
                    'ip'                  => $this->request->server['REMOTE_ADDR'] ?? '',
                    'forwarded_ip'        => $this->request->server['HTTP_X_FORWARDED_FOR'] ?? $this->request->server['HTTP_CLIENT_IP'] ?? '',
                    'user_agent'          => $this->request->server['HTTP_USER_AGENT'] ?? '',
                    'accept_language'     => $this->request->server['HTTP_ACCEPT_LANGUAGE'] ?? '',
                    'comment'             => $comment,
                    'total'               => $total,
                    'products'            => [],
                    'totals'              => $totals
                ];

                $data_layer_items = [];

                foreach ($this->cart->getProducts() as $product) {
                    $order_data['products'][] = [
                        'product_id' => $product['product_id'],
                        'name'       => $product['name'],
                        'quantity'   => $product['quantity'],
                        'price'      => $product['price'],
                        'total'      => $product['total'],
                        'master_id'      => $product['master_id'],
                        'model'      => $product['model'],
                        'tax'      => 0,
                        'reward'      => $product['reward'],
                    ];

                    $data_layer_items[] = [
                        'item_id' => $product['model'],
                        'item_name' => $product['name'],
                        'price' => $product['price'],
                        'quantity' => $product['quantity'],
                    ];
                }

                $this->load->model('checkout/order');
                $order_id = $this->model_checkout_order->addOrder($order_data);

                if ($order_id) {
                    $json['success'] = true;
                    $json['order_id'] = $order_id;
                    $this->session->data['order_id'] = $order_id;
                    $json['payment_method'] = $payment_method['code'];
                    if ($payment_method['code'] === 'liqpay.liqpay') {
                        $json['redirect'] = $this->url->link('extension/opencart/payment/liqpay.confirm', 'order_id=' . $order_id);
                    }

                    $json['data_layer'] = [
                        'phone_number' => $telephone,
                        'order_id' => $order_id,
                        'total' => $total,
                        'items' => $data_layer_items,
                    ];

                    $this->load->model('checkout/salesdrive');
                    $this->model_checkout_salesdrive->sendOrderToSalesDrive($order_id, $order_data);

                    $this->model_checkout_order->addHistory($order_id, $this->config->get('config_order_status_id'));
                } else {
                    $json['error'] = $this->language->get('error_order_creation');
                }
            }
        }

        $this->response->setOutput(json_encode($json));
    }

}
