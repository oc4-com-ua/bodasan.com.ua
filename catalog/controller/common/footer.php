<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Footer
 *
 * Can be called from $this->load->controller('common/footer');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Footer extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('common/footer');

        $data['footer_menu'] = [
            ['name' => 'Доставка', 'href' => $this->url->link('information/information', 'information_id=4')],
            ['name' => 'Оплата', 'href' => $this->url->link('information/information', 'information_id=6')],
            ['name' => 'Повернення', 'href' => $this->url->link('information/information', 'information_id=5')],
            ['name' => 'Про нас', 'href' => $this->url->link('information/information', 'information_id=1')],
            ['name' => 'Контакти', 'href' => $this->url->link('information/information', 'information_id=7')],
            ['name' => 'Договір оферти', 'href' => $this->url->link('information/information', 'information_id=2')],
            ['name' => 'Політика конфіденційності', 'href' => $this->url->link('information/information', 'information_id=3')],
//            ['name' => 'Відгуки', 'href' => $this->url->link('information/information', 'information_id=6')],
//            ['name' => 'Карта сайту', 'href' => $this->url->link('information/sitemap')],
        ];

        $data['legal_menu'] = [
//            ['name' => 'Договір оферти', 'href' => $this->url->link('information/information', 'information_id=2')],
//            ['name' => 'Політика конфіденційності', 'href' => $this->url->link('information/information', 'information_id=3')],
        ];

        $data['telephone'] = $this->config->get('config_telephone');
        $data['telephone_clear'] = preg_replace('/(?!^\+)[^0-9]/', '', $data['telephone']);
        $data['telephone2'] = $this->config->get('config_telephone2');
        $data['telephone2_clear'] = preg_replace('/(?!^\+)[^0-9]/', '', $data['telephone2']);
        $data['email_public'] = $this->config->get('config_email_public');
        $data['address'] = html_entity_decode($this->config->get('config_address'));
        $data['working_hours'] = nl2br(htmlspecialchars($this->config->get('config_open')));
        $data['viber'] = $this->config->get('config_viber');
        $data['telegram'] = $this->config->get('config_telegram');
        $data['youtube'] = $this->config->get('config_youtube');
        $data['owner'] = $this->config->get('config_owner');
		$data['copyright'] = sprintf($this->language->get('text_copyright'), date('Y', time()), $this->config->get('config_name'));

		$data['bootstrap'] = 'catalog/view/javascript/bootstrap/js/bootstrap.bundle.min.js';
		$data['scripts'] = $this->document->getScripts('footer');
		$data['cookie'] = $this->load->controller('common/cookie');

		return $this->load->view('common/footer', $data);
	}
}
