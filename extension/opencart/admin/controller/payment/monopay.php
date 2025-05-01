<?php
declare(strict_types=1);

namespace Opencart\Admin\Controller\Extension\Opencart\Payment;

class Monopay extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/opencart/payment/monopay');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/opencart/payment/monopay', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/opencart/payment/monopay.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        $data['payment_monopay_token']          = $this->config->get('payment_monopay_token');
        $data['payment_monopay_order_status_id']= $this->config->get('payment_monopay_order_status_id');
        $data['payment_monopay_status']         = $this->config->get('payment_monopay_status');
        $data['payment_monopay_sort_order']     = $this->config->get('payment_monopay_sort_order');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/opencart/payment/monopay', $data));
    }

    public function save(): void {
        $this->load->language('extension/opencart/payment/monopay');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/opencart/payment/monopay')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_monopay', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
