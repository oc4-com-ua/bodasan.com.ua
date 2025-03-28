<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Header
 *
 * Can be called from $this->load->controller('common/header');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Header extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		// Analytics
		$data['analytics'] = [];

		if (!$this->config->get('config_cookie_id') || (isset($this->request->cookie['policy']) && $this->request->cookie['policy'])) {
			$this->load->model('setting/extension');

			$analytics = $this->model_setting_extension->getExtensionsByType('analytics');

			foreach ($analytics as $analytic) {
				if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
					$data['analytics'][] = $this->load->controller('extension/' . $analytic['extension'] . '/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
				}
			}
		}

		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['title'] = $this->document->getTitle();
		$data['base'] = $this->config->get('config_url');
		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();

		// Hard coding css so they can be replaced via the event's system.
		$data['bootstrap'] = 'catalog/view/stylesheet/bootstrap.css';
		$data['icons'] = 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css';
        $stylesheet = is_mobile() ? 'stylesheet-mobile' : 'stylesheet';
		$data['stylesheet'] = "catalog/view/stylesheet/{$stylesheet}.css";

		// Hard coding scripts so they can be replaced via the event's system.
		$data['jquery'] = 'catalog/view/javascript/jquery/jquery-3.7.1.min.js';

		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');

		$data['name'] = $this->config->get('config_name');

		// Fav icon
		if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$data['icon'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_icon');
		} else {
			$data['icon'] = '';
		}

		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		$this->load->language('common/header');

        $data['header_menu'] = [
            ['name' => 'Доставка', 'href' => $this->url->link('information/information', 'information_id=4')],
            ['name' => 'Оплата', 'href' => $this->url->link('information/information', 'information_id=6')],
            ['name' => 'Повернення', 'href' => $this->url->link('information/information', 'information_id=5')],
            ['name' => 'Про нас', 'href' => $this->url->link('information/information', 'information_id=1')],
            ['name' => 'Контакти', 'href' => $this->url->link('information/contact')],
//            ['name' => 'Відгуки', 'href' => $this->url->link('information/information', 'information_id=6')],
        ];

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

		// Wishlist
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist($this->customer->getId()));
		} else {
			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
		}

		$data['home'] = $this->url->link('common/home');
		$data['wishlist'] = $this->url->link('account/wishlist', (isset($this->session->data['customer_token']) ? 'customer_token=' . $this->session->data['customer_token'] : ''));
		$data['logged'] = $this->customer->isLogged();

		if (!$this->customer->isLogged()) {
			$data['register'] = $this->url->link('account/register');
			$data['login'] = $this->url->link('account/login');
		} else {
			$data['account'] = $this->url->link('account/account', 'customer_token=' . $this->session->data['customer_token']);
			$data['order'] = $this->url->link('account/order', 'customer_token=' . $this->session->data['customer_token']);
			$data['transaction'] = $this->url->link('account/transaction', 'customer_token=' . $this->session->data['customer_token']);
			$data['download'] = $this->url->link('account/download', 'customer_token=' . $this->session->data['customer_token']);
			$data['logout'] = $this->url->link('account/logout');
		}

		$data['shopping_cart'] = $this->url->link('checkout/cart');
		$data['checkout'] = $this->url->link('checkout/checkout');
		$data['contact'] = $this->url->link('information/contact');
		$data['telephone'] = $this->config->get('config_telephone');
		$data['telephone_clear'] = preg_replace('/(?!^\+)[^0-9]/', '', $data['telephone']);
		$data['telephone2'] = $this->config->get('config_telephone2');
		$data['telephone2_clear'] = preg_replace('/(?!^\+)[^0-9]/', '', $data['telephone2']);
		$data['working_hours'] = $this->config->get('config_open');
		$data['viber'] = $this->config->get('config_viber');
		$data['telegram'] = $this->config->get('config_telegram');

		$data['language'] = $this->load->controller('common/language');
		$data['currency'] = $this->load->controller('common/currency');
		$data['search'] = $this->load->controller('common/search');
		$data['cart'] = $this->load->controller('common/cart');
		$data['menu'] = $this->load->controller('common/menu');

		return $this->load->view('common/header', $data);
	}
}
