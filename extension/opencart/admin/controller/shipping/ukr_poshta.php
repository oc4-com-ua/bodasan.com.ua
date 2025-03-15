<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Shipping;
/**
 * Class UkrPoshta
 *
 * @package Opencart\Admin\Controller\Extension\Opencart\Shipping
 */
class UkrPoshta extends \Opencart\System\Engine\Controller {
    /**
     * Index
     *
     * @return void
     */
    public function index(): void {
        $this->load->language('extension/opencart/shipping/ukr_poshta');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/opencart/shipping/ukr_poshta', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/opencart/shipping/ukr_poshta.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

        $data['shipping_ukr_poshta_api_key'] = $this->config->get('shipping_ukr_poshta_api_key');
        $data['shipping_ukr_poshta_status'] = $this->config->get('shipping_ukr_poshta_status');
        $data['shipping_ukr_poshta_sort_order'] = $this->config->get('shipping_ukr_poshta_sort_order');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/opencart/shipping/ukr_poshta', $data));
    }

    /**
     * Save
     *
     * @return void
     */
    public function save(): void {
        $this->load->language('extension/opencart/shipping/ukr_poshta');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/opencart/shipping/ukr_poshta')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('shipping_ukr_poshta', $this->request->post);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
