<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Home
 *
 * Can be called from $this->load->controller('common/home');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Home extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->document->setTitle($this->config->get('config_meta_title'));
		$this->document->setDescription($this->config->get('config_meta_description'));
		$this->document->setKeywords($this->config->get('config_meta_keyword'));

        $this->load->model('catalog/category');
        $categories = $this->model_catalog_category->getCategories(0);

        $data['catalog_menu'] = [];

        if ($categories) {
            foreach ($categories as $category) {
                if (!$category['status']) {
                    continue;
                }

                $category_image = $category['image'] ? $category['image'] : 'catalog/category/no-image.svg';

                $data['catalog_menu'][] = [
                    'name'  => $category['name'],
                    'href'  => $this->url->link('product/category', 'path=' . $category['category_id']),
                    'image' => $category_image
                ];
            }
        }

        $this->load->model('tool/image');
        $this->load->model('catalog/review');

        $limit_reviews = 6;
        $data['latest_reviews'] = $this->model_catalog_review->getLatestReviews($limit_reviews);
        foreach ($data['latest_reviews'] as &$review) {
            $review['date_added'] = date('Y-m-d', strtotime($review['date_added']));
            $review['price'] = $this->currency->format($review['price'], $this->session->data['currency']);
            if ($review['old_price']) {
                $review['old_price'] = $this->currency->format($review['old_price'], $this->session->data['currency']);
            } else {
                $review['old_price'] = false;
            }
            $review['image'] = $this->model_tool_image->resize($review['image'], '72', '72');
            $review['href'] = $this->url->link('product/product', 'product_id=' . $review['product_id']);
        }

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
