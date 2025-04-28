<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Module;
/**
 * Class Banner
 *
 * @package Opencart\Catalog\Controller\Extension\Opencart\Module
 */
class Banner extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @param array<string, mixed> $setting
	 *
	 * @return string
	 */
	public function index(array $setting): string {
		static $module = 0;
        static $assets_loaded = false;

        if (!$assets_loaded) {
            // css – підвантажується у <head>
            $this->document->addStyle('catalog/view/javascript/splide/css/splide-core.min.css','stylesheet');

            // js – підвантажується у <footer>
            $this->document->addScript('catalog/view/javascript/splide/js/splide.min.js','footer');

            // щоб не додавати ще раз, якщо банерів декілька
            $assets_loaded = true;

            // «маячок» для footer'а
            if (!defined('BANNER_USED')) {
                define('BANNER_USED', true);
            }
        }

		// Banner
		$this->load->model('design/banner');

		// Image
		$this->load->model('tool/image');

		$data['banners'] = [];

		$results = $this->model_design_banner->getBanner($setting['banner_id']);

		foreach ($results as $result) {
			if (is_file(DIR_IMAGE . html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'))) {
				$data['banners'][] = [
					'title' => $result['title'],
					'link'  => $result['link'],
					'image' => is_mobile() ? 'image/' . $result['image_mobile'] : 'image/' . $result['image'],
				];
			}
		}

		if ($data['banners']) {
			$data['module'] = $module++;

			$data['effect'] = $setting['effect'];
			$data['controls'] = $setting['controls'];
			$data['indicators'] = $setting['indicators'];
			$data['items'] = $setting['items'];
			$data['interval'] = $setting['interval'];
			$data['width'] = $setting['width'];
			$data['height'] = $setting['height'];

			return $this->load->view('extension/opencart/module/banner', $data);
		} else {
			return '';
		}
	}
}
