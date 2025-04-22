<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class Checkout
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class Checkout extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		// Validate cart to see if it has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$this->response->redirect($this->url->link('checkout/cart', '', true));
		}

		$this->load->language('checkout/checkout');
		$this->load->language('extension/opencart/shipping/nova_poshta_fields');
		$this->load->language('extension/opencart/shipping/ukr_poshta_fields');

		$this->document->setTitle($this->language->get('heading_title_checkout'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_cart'),
			'href' => $this->url->link('checkout/cart')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title_checkout'),
			'href' => $this->url->link('checkout/checkout')
		];

        $this->load->model('tool/image');
        $this->load->model('checkout/cart');

        $products = $this->model_checkout_cart->getProducts();
        $data['products'] = [];

        foreach ($products as $product) {
            $old_price_total = 0;

            if (isset($product['old_price'])) {
                $old_price_total = $product['old_price'] * $product['quantity'];
            }

            $data['products'][] = [
                    'thumb' => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),
                    'stock' => $product['stock_status'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
                    'price' => $product['price_text'],
                    'total' => $product['total_text'],
                    'old_price_total' => $old_price_total ? $this->currency->format($old_price_total, $this->session->data['currency']) : '',
                    'href' => $this->url->link('product/product', 'product_id=' . $product['product_id']),
                ] + $product;
        }

        $total_products_price = 0;

        foreach ($products as $product) {
            $total_products_price += $product['total'];
        }

        $data['total_products_price'] = $this->currency->format($total_products_price, $this->session->data['currency']);

        $data['register'] = '';

		if ($this->cart->hasShipping()) {
			$data['shipping_methods'] = $this->load->controller('checkout/shipping_method.getShippingMethods');
		} else {
			$data['shipping_methods'] = '';
		}

		$data['payment_methods'] = $this->load->controller('checkout/payment_method.getPaymentMethods');
		$data['confirm'] = $this->load->controller('checkout/confirm');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

        $data['language'] = $this->config->get('config_language');

        $this->response->setOutput($this->load->view('checkout/checkout', $data));
	}
}
