<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class Cart
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class Cart extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('checkout/cart');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('checkout/cart')
		];

		$data['list'] = $this->load->controller('checkout/cart.getList');

		$data['language'] = $this->config->get('config_language');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('checkout/cart', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('checkout/cart');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
			$data['error_stock'] = $this->language->get('error_stock');
		} else {
			$data['error_stock'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
			$data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
		} else {
			$data['attention'] = '';
		}

		if ($this->config->get('config_cart_weight')) {
			$data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
		} else {
			$data['weight'] = '';
		}

		$data['edit'] = $this->url->link('checkout/cart.edit');

		// Display prices
		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			$price_status = true;
		} else {
			$price_status = false;
		}

		// Image
		$this->load->model('tool/image');

		// Upload
		$this->load->model('tool/upload');

		$data['products'] = [];

		$this->load->model('checkout/cart');

		$products = $this->model_checkout_cart->getProducts();

		foreach ($products as $product) {
			if ($product['option']) {
				foreach ($product['option'] as $key => $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$product['option'][$key]['value'] = (oc_strlen($value) > 20 ? oc_substr($value, 0, 20) . '..' : $value);
				}
			}

			$subscription = '';

			if ($product['subscription'] && $price_status) {
				if ($product['subscription']['trial_status']) {
					$subscription .= sprintf($this->language->get('text_subscription_trial'), $product['subscription']['trial_price_text'], $product['subscription']['trial_cycle'], $product['subscription']['trial_frequency'], $product['subscription']['trial_duration']);
				}

				if ($product['subscription']['duration']) {
					$subscription .= sprintf($this->language->get('text_subscription_duration'), $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency'], $product['subscription']['duration']);
				} else {
					$subscription .= sprintf($this->language->get('text_subscription_cancel'), $product['subscription']['price_text'], $product['subscription']['cycle'], $product['subscription']['frequency']);
				}
			}

            $old_price_total = 0;

            if (isset($product['old_price'])) {
                $old_price_total = $product['old_price'] * $product['quantity'];
            }

			$data['products'][] = [
				'thumb'        => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),
				'subscription' => $subscription,
				'stock'        => $product['stock_status'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
				'minimum'      => !$product['minimum_status'] ? sprintf($this->language->get('error_minimum'), $product['minimum']) : 0,
				'price'        => $price_status ? $product['price_text'] : '',
				'total'        => $price_status ? $product['total_text'] : '',
                'old_price_total' => $price_status && $old_price_total ? $this->currency->format($old_price_total, $this->session->data['currency']) : '',
                    'href'         => $this->url->link('product/product', 'product_id=' . $product['product_id']),
				'remove'       => $this->url->link('checkout/cart.remove', 'key=' . $product['cart_id'])
			] + $product;
		}

		$data['totals'] = [];

		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;

		// Display prices
		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

			foreach ($totals as $result) {
				$data['totals'][] = ['text' => $price_status ? $this->currency->format($result['value'], $this->session->data['currency']) : ''] + $result;
			}
		}

		$data['modules'] = [];

		$this->load->model('setting/extension');

		$extensions = $this->model_setting_extension->getExtensionsByType('total');

		foreach ($extensions as $extension) {
			$result = $this->load->controller('extension/' . $extension['extension'] . '/checkout/' . $extension['code']);

			if (!$result instanceof \Exception) {
				$data['modules'][] = $result;
			}
		}

		if ($products) {
			$data['continue'] = $this->url->link('common/home');
			$data['checkout'] = $this->url->link('checkout/checkout');
		} else {
			$data['continue'] = $this->url->link('common/home');
		}

        $total_quantity = 0;

        foreach ($products as $product) {
            $total_quantity += $product['quantity'];
        }

        $data['total_quantity'] = $total_quantity;
        $data['text_total_products'] = sprintf($this->language->get('text_total_products'), $total_quantity, $this->getProductWord($total_quantity));

        $total_products_price = 0;

        foreach ($products as $product) {
            $total_products_price += $product['total'];
        }

        $data['total_products_price'] = $this->currency->format($total_products_price, $this->session->data['currency']);

        return $this->load->view('checkout/cart_list', $data);
	}

	/**
	 * Add
	 *
	 * @return void
	 */
	public function add(): void {
		$this->load->language('checkout/cart');

		$json = [];

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		if (isset($this->request->post['quantity'])) {
			$quantity = (int)$this->request->post['quantity'];
		} else {
			$quantity = 1;
		}

		if (isset($this->request->post['option'])) {
			$option = array_filter($this->request->post['option']);
		} else {
			$option = [];
		}

		if (isset($this->request->post['subscription_plan_id'])) {
			$subscription_plan_id = (int)$this->request->post['subscription_plan_id'];
		} else {
			$subscription_plan_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info) {
			// If variant get master product
			if ($product_info['master_id']) {
				$product_id = $product_info['master_id'];
			}

			// Only use values in the override
			if (isset($product_info['override']['variant'])) {
				$override = $product_info['override']['variant'];
			} else {
				$override = [];
			}

			// Merge variant code with options
			foreach ($product_info['variant'] as $key => $value) {
				if (array_key_exists($key, $override)) {
					$option[$key] = $value;
				}
			}

			// Validate options
			$product_options = $this->model_catalog_product->getOptions($product_id);

			foreach ($product_options as $product_option) {
				if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
					$json['error']['option_' . $product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
				}
			}

			// Validate subscription products
			$subscriptions = $this->model_catalog_product->getSubscriptions($product_id);

			if ($subscriptions && (!$subscription_plan_id || !in_array($subscription_plan_id, array_column($subscriptions, 'subscription_plan_id')))) {
				$json['error']['subscription'] = $this->language->get('error_subscription');
			}
		} else {
			$json['error']['warning'] = $this->language->get('error_product');
		}

		if (!$json) {
            $this->load->model('tool/image');

			$this->cart->add($product_id, $quantity, $option, $subscription_plan_id);

            $product_cart = [
                'name' => $product_info['name'],
                'link' => $this->url->link('product/product', 'product_id=' . $product_id),
                'thumb'=> $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),
                'quantity' => $quantity,
                'old_price' => $product_info['old_price'] ? $this->currency->format($quantity * (int)$product_info['old_price'], $this->session->data['currency']) : false,
                'price' => $this->currency->format($quantity * (int)$product_info['price'], $this->session->data['currency']),
            ];

            $template = "<div class='modal-cart__product'>";
                $template .= "<a href='{$product_cart['link']}' class='modal-cart__img'><img src='{$product_cart['thumb']}'></a>";
                $template .= "<div class='modal-cart__inner'>";
                    $template .= "<div class='modal-cart__title'><a href='{$product_cart['link']}' class='modal-cart__title-link'>{$product_cart['name']}</a></div>";
                    $template .= "<div class='modal-cart__price'>";
                        $template .= "<div class='modal-cart__price-main'>";
                            $template .= "<div class='modal-cart__price-current'>{$product_cart['price']}</div>";
                            if ($product_cart['old_price']) {
                                $template .= "<div class='modal-cart__price-old'>{$product_cart['old_price']}</div>";
                            }
                        $template .= "</div>";
                        $template .= "<div class='modal-cart__price-count'>x{$product_cart['quantity']}</div>";
                    $template .= "</div>";
                $template .= "</div>";
            $template .= "</div>";

            $json['success'] = [
                'type' => 'add_cart',
                'template' => $template,
                'data_layer' => [
                    'id' => $product_info['model'],
                    'name' => $product_info['name'],
                    'price' => $product_info['price'],
                    'quantity' => $quantity
                ],
            ];

			// Unset all shipping and payment methods
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
		} else {
			$json['redirect'] = $this->url->link('product/product', 'product_id=' . $product_id, true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Edit
	 *
	 * @return void
	 */
	public function edit(): void {
		$this->load->language('checkout/cart');

		$json = [];

		if (isset($this->request->post['key'])) {
			$key = (int)$this->request->post['key'];
		} else {
			$key = 0;
		}

		if (isset($this->request->post['quantity'])) {
			$quantity = (int)$this->request->post['quantity'];
		} else {
			$quantity = 1;
		}

		// Handles single item update
		$this->cart->update($key, $quantity);

		if ($this->cart->hasProducts()) {
			$json['success'] = $this->language->get('text_edit');
		} else {
			$json['redirect'] = $this->url->link('checkout/cart', '', true);
		}

		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);
		unset($this->session->data['reward']);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Remove
	 *
	 * @return void
	 */
	public function remove(): void {
		$this->load->language('checkout/cart');

		$json = [];

		if (isset($this->request->get['key'])) {
			$key = (int)$this->request->get['key'];
		} else {
			$key = 0;
		}

		// Remove
		$this->cart->remove($key);

		if ($this->cart->hasProducts()) {
			$json['success'] = $this->language->get('text_remove');
		} else {
			$json['redirect'] = $this->url->link('checkout/cart', '', true);
		}

		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);
		unset($this->session->data['reward']);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

    protected function getProductWord(int $count): string {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 == 1 && $mod100 != 11) {
            return 'товар';
        } elseif ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return 'товари';
        } else {
            return 'товарів';
        }
    }

}
