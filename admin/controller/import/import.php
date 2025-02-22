<?php
namespace Opencart\Admin\Controller\Import;

class Import extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('import/import');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('import/import', 'user_token=' . $this->session->data['user_token'])
        ];

        $this->load->model('import/import');


        $data['action_import_images'] = $this->url->link('import/import.downloadImages', 'user_token=' . $this->session->data['user_token']);
        $data['action_import_products'] = $this->url->link('import/import.parseFeed', 'user_token=' . $this->session->data['user_token']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('import/import', $data));
    }

    public function parseFeed(): void {
        $this->load->language('import/import');
        $this->load->model('import/import');

        $result = $this->model_import_import->parseAndStore(FEED_PROM_URL);

        if (!empty($result['error'])) {
            $this->session->data['error_warning'] = $result['error'];
        } else {
            $this->session->data['success'] = $this->language->get('text_parse_success');
        }

        $this->response->redirect($this->url->link('import/import', 'user_token=' . $this->session->data['user_token']));
    }


}
