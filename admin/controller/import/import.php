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

        $parse_stats = $this->model_import_import->getParseStats();
        $data['parse_stats'] = $parse_stats;

        $data['action_parse_feed'] = $this->url->link('import/import.parseFeed', 'user_token=' . $this->session->data['user_token']);

        $data['fetch_url_img'] = $this->url->link('import/import.downloadImagesAjax', 'user_token=' . $this->session->data['user_token']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['action_import'] = $this->url->link('import/import.importAll', 'user_token=' . $this->session->data['user_token']);
        $data['action_clear_parse'] = $this->url->link('import/import.clearImportData', 'user_token=' . $this->session->data['user_token']);

        $data['success_parse_feed'] = !empty($this->session->data['success_parse_feed']) ? $this->session->data['success_parse_feed'] : '';
        $data['error_parse_feed'] = !empty($this->session->data['error_parse_feed']) ? $this->session->data['error_parse_feed'] : '';
        $data['import_summary'] = !empty($this->session->data['import_summary']) ? $this->session->data['import_summary'] : '';
        $data['success_clear_parse'] = !empty($this->session->data['success_clear_parse']) ? $this->session->data['success_clear_parse'] : '';

        unset($this->session->data['success_parse_feed']);
        unset($this->session->data['error_parse_feed']);
        unset($this->session->data['import_summary']);
        unset($this->session->data['success_clear_parse']);

        $this->response->setOutput($this->load->view('import/import', $data));
    }

    public function parseFeed(): void {
        $this->load->language('import/import');
        $this->load->model('import/import');

        $result = $this->model_import_import->parseAndStore(FEED_PROM_URL, $this->language);

        if (!empty($result['error'])) {
            $this->session->data['error_parse_feed'] = $result['error'];
        } else {
            $this->session->data['success_parse_feed'] = $result['success_parse_feed'];
        }

        $this->response->redirect($this->url->link('import/import', 'user_token=' . $this->session->data['user_token']));
    }

    public function downloadImagesAjax(): void {
        $offset = (int)($this->request->get['offset'] ?? 0);
        $limit  = (int)($this->request->get['limit'] ?? 100);

        $this->load->model('import/import');

        $json = $this->model_import_import->downloadImagesChunk($offset, $limit);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function importAll(): void {
        $this->load->language('import/import');
        $this->load->model('import/import');

        if (!isset($this->session->data['import_summary'])) {
            $this->session->data['import_summary'] = [];
        }

        $import_categories = $this->model_import_import->importCategories();

        $this->session->data['import_summary']['categories'] = sprintf(
            $this->language->get('text_import_categories'),
            $import_categories['total'],
            $import_categories['new'],
            $import_categories['updated']
        );

        $import_manufacturers = $this->model_import_import->importManufacturers();

        $this->session->data['import_summary']['manufacturers'] = sprintf(
            $this->language->get('text_import_manufacturers'),
            $import_manufacturers['total'],
            $import_manufacturers['new'],
            $import_manufacturers['skipped']
        );

        $import_attributes = $this->model_import_import->importAttributes();
        $this->session->data['import_summary']['attributes'] = sprintf(
            $this->language->get('text_import_attributes'),
            $import_attributes['total'],
            $import_attributes['new'],
            $import_attributes['skipped']
        );

        $import_products = $this->model_import_import->importProducts();
        $this->session->data['import_summary']['products'] = sprintf(
            $this->language->get('text_import_products'),
            $import_products['total'],
            $import_products['new'],
            $import_products['updated']
        );

        $this->response->redirect($this->url->link('import/import', 'user_token=' . $this->session->data['user_token']));
    }

    public function clearImportData(): void {
        $this->load->language('import/import');
        $this->load->model('import/import');

        try {
            $this->model_import_import->clearImportTables();

            $this->session->data['success_clear_parse'] = $this->language->get('text_clear_success');
        } catch (\Exception $e) {
            $this->session->data['error_warning'] = 'Error: ' . $e->getMessage();
        }

        $this->response->redirect($this->url->link('import/import', 'user_token=' . $this->session->data['user_token']));
    }
}
