<?php
namespace Opencart\Catalog\Controller\Product;
/**
 * Class Thumb
 *
 * @package Opencart\Catalog\Controller\Product
 */
class Thumb extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @param array<string, mixed> $data array of data
	 *
	 * @return string
	 */
	public function index(array $data): string {
		$this->load->language('product/thumb');

        $this->load->model('localisation/stock_status');

        if ($data['quantity'] <= 0) {
            $stock_status_id = $data['stock_status_id'];
        } elseif (!$this->config->get('config_stock_display')) {
            $stock_status_id = (int)$this->config->get('config_stock_status_id');
        } else {
            $stock_status_id = 0;
        }

        $stock_status_info = $this->model_localisation_stock_status->getStockStatus($stock_status_id);

        if ($stock_status_info) {
            $data['stock'] = $stock_status_info['name'];
        } else {
            $data['stock'] = $data['quantity'];
        }

		$data['cart'] = $this->url->link('common/cart.info', 'language=' . $this->config->get('config_language'));

		$data['cart_add'] = $this->url->link('checkout/cart.add', 'language=' . $this->config->get('config_language'));
		$data['wishlist_add'] = $this->url->link('account/wishlist.add', 'language=' . $this->config->get('config_language'));
		$data['compare_add'] = $this->url->link('product/compare.add', 'language=' . $this->config->get('config_language'));

		$data['review_status'] = (int)$this->config->get('config_review_status');

		return $this->load->view('product/thumb', $data);
	}
}
