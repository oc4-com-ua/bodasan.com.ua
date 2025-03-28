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

		$this->document->setTitle($this->language->get('heading_title'));

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
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('checkout/checkout')
		];

        $data['register'] = '';

		if ($this->cart->hasShipping()) {
			$data['shipping_methods'] = $this->load->controller('checkout/shipping_method.getShippingMethods');
		} else {
			$data['shipping_methods'] = '';
		}

		$data['payment_methods'] = $this->load->controller('checkout/payment_method.getPaymentMethods');
//		$data['payment_method'] = '';
		$data['confirm'] = $this->load->controller('checkout/confirm');
//		$data['confirm'] = '';

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
