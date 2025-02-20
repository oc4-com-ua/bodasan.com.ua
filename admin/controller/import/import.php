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

        $data['preview'] = $this->url->link('import/import.parseFeed', 'user_token=' . $this->session->data['user_token']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        // $this->load->model('import/import');
        // Приклад тестового виклику:
        // $settings = $this->model_import_import->getImportSettings();

        $this->response->setOutput($this->load->view('import/import', $data));
    }

    public function settings(): void {
        $this->load->language('import/import');
        $this->document->setTitle($this->language->get('heading_settings'));
        $this->load->model('import/import');

        $data['import_settings'] = $this->model_import_import->getSettings();

        $data['save'] = $this->url->link('import/import.saveSettings', 'user_token=' . $this->session->data['user_token']);

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('import/import_settings', $data));
    }

    public function saveSettings(): void {
        $this->load->language('import/import');

        $json = [];

        if (!$this->user->hasPermission('modify', 'import/import')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['feed_url'])) {
            $json['error']['feed_url'] = $this->language->get('error_feed_url');
        }

        if (isset($json['error']) && !isset($json['error']['warning'])) {
            $json['error']['warning'] = $this->language->get('error_warning');
        }

        if (!$json) {
            $this->load->model('import/import');
            $this->model_import_import->saveSettings($this->request->post);

            $json['success'] = $this->language->get('text_success_settings');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
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

//        $this->response->redirect($this->url->link('import/import.settings', 'user_token=' . $this->session->data['user_token']));

        // Тимчасово просто виводимо повідомлення
        echo '<div style="margin:20px;">Парсинг завершено. Дані збережено в тимчасові таблиці.</div>';
        exit;
    }


}
