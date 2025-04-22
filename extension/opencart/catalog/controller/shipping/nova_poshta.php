<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Shipping;

class NovaPoshta extends \Opencart\System\Engine\Controller {
    public function autocomplete(): void {
        $this->response->addHeader('Content-Type: application/json');

        $type = $this->request->get['type'] ?? '';
        $term = $this->request->get['term'] ?? '';
        $city_ref = $this->request->get['city_ref'] ?? '';

        if (!$type || !$term) {
            $this->response->setOutput(json_encode([]));
            return;
        }

        $result = [];

        switch ($type) {
            case 'city':
                if (mb_strlen($term) >= 3) {
                    $result = $this->searchCities($term);
                }
                break;

            case 'warehouse':
            case 'postamat':
                if ($city_ref && (mb_strlen($term) >= 1 || $term === '*')) {
                    $type_of_warehouse = ($type === 'postamat') ? 'Postomat' : '';
                    $search = ($term === '*') ? '' : $term;
                    $result = $this->searchWarehouses($city_ref, $search, $type_of_warehouse);
                }
                break;

            case 'street':
                if ($city_ref && mb_strlen($term) >= 1 || $term === '*') {
                    $search = ($term === '*') ? '' : $term;
                    $result = $this->searchStreets($city_ref, $search);
                }
                break;
        }

        $this->response->setOutput(json_encode($result));
    }

    private function searchCities(string $search): array {
        $api_key = $this->config->get('shipping_nova_poshta_api_key');

        $data = [
            'apiKey' => $api_key,
            'modelName' => 'Address',
            'calledMethod' => 'searchSettlements',
            'methodProperties' => [
                'CityName' => $search,
                'Limit'    => 50
            ]
        ];

        $response = $this->curlRequest('https://api.novaposhta.ua/v2.0/json/', $data);

        $results = [];
        if (!empty($response['data'][0]['Addresses'])) {
            foreach ($response['data'][0]['Addresses'] as $city) {
                $results[] = [
                    'value' => $city['Present'],
                    'ref'   => $city['DeliveryCity'],
                ];
            }
        }
        return $results;
    }

    private function searchWarehouses(string $city_ref, string $search, string $type_of_warehouse = ''): array {
        $api_key = $this->config->get('shipping_nova_poshta_api_key');

        $data = [
            'apiKey' => $api_key,
            'modelName' => 'AddressGeneral',
            'calledMethod' => 'getWarehouses',
            'methodProperties' => [
                'CityRef' => $city_ref,
                'FindByString' => $search,
                'Limit' => 50
            ]
        ];

        if ($type_of_warehouse === 'Postomat') {
            $data['methodProperties']['TypeOfWarehouse'] = 'Postomat';
        }

        $response = $this->curlRequest('https://api.novaposhta.ua/v2.0/json/', $data);

        $results = [];
        if (!empty($response['data'])) {
            foreach ($response['data'] as $w) {
                $results[] = [
                    'value' => $w['Description'],
                    'ref'   => $w['Ref']
                ];
            }
        }

        return $results;
    }

    private function searchStreets(string $city_ref, string $search): array {
        $api_key = $this->config->get('shipping_nova_poshta_api_key');

        $data = [
            'apiKey' => $api_key,
            'modelName' => 'Address',
            'calledMethod' => 'getStreet',
            'methodProperties' => [
                'CityRef' => $city_ref,
                'FindByString' => $search,
                'Limit' => 50
            ]
        ];

        $response = $this->curlRequest('https://api.novaposhta.ua/v2.0/json/', $data);

        $results = [];
        if (!empty($response['data'])) {
            foreach ($response['data'] as $s) {
                $results[] = [
                    'value' => $s['StreetsType'] . ' ' . $s['Description'],
                    'ref'   => $s['Ref']
                ];
            }
        }

        return $results;
    }

    private function curlRequest(string $url, array $data): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log->write("CURL Error: $error | URL: $url | Data: " . json_encode($data));
            return [];
        }

        $result = json_decode($response, true);
        return $result ?? [];
    }
}
