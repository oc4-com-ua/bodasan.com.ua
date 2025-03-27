<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Shipping;

class UkrPoshta extends \Opencart\System\Engine\Controller {
    /**
     * Метод для автопідказок:
     *  ?route=extension/opencart/shipping/ukr_poshta.autocomplete
     *  &type=city|branch|street
     *  &term=рядок
     *  &region_id=? (не обов'язково)
     *  &district_id=? (не обов'язково)
     *  &city_id=? (для branch/street)
     */
    public function autocomplete(): void {
        $this->response->addHeader('Content-Type: application/json');

        $type = $this->request->get['type'] ?? '';
        $term = $this->request->get['term'] ?? '';
        $region_id = $this->request->get['region_id'] ?? '';
        $district_id = $this->request->get['district_id'] ?? '';
        $city_id = $this->request->get['city_id'] ?? '';

        if (!$type) {
            $this->response->setOutput(json_encode([]));
            return;
        }

        $api_key = $this->config->get('shipping_ukr_poshta_api_key') ?? '';

        $results = [];
        switch ($type) {
            case 'city':
                if (!$term) break;
                $results = $this->searchCities($api_key, $term);
                break;

            case 'branch':
                $results = $this->searchBranches($api_key, $region_id, $district_id, $city_id);
                break;

            case 'street':
                if ($city_id) {
                    $results = $this->searchStreets($api_key, $region_id, $district_id, $city_id, $term);
                }
                break;
        }

        $this->response->setOutput(json_encode($results));
    }

    /**
     * Пошук населених пунктів
     */
    private function searchCities(string $api_key, string $city_ua): array {
        $base_url = 'https://www.ukrposhta.ua/address-classifier-ws/get_city_by_region_id_and_district_id_and_city_ua';
        $params = [
            'city_ua' => $city_ua
        ];
        $query     = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $final_url = $base_url . '?' . $query;

        $data = $this->doGetRequest($final_url, $api_key);

        $results = [];
        $entries = $this->extractEntries($data, 'CITY_ID');
        foreach ($entries as $e) {
            $city_id = (string)($e['CITY_ID'] ?? '');
            $city_name = $e['CITY_UA']     ?? '';
            $region_name = $e['REGION_UA']   ?? '';
            $district_name = $e['DISTRICT_UA'] ?? '';
            $shortcitytype_ua = $e['SHORTCITYTYPE_UA'] ?? '';
            $ownof = $e['OWNOF'] ?? '';

            if ($city_id && $city_name) {
                $value = $shortcitytype_ua . ' ' . $city_name;

                if (!$e['IS_DISTRICTCENTER']) {
                    $extras = [];

                    if ($region_name) $extras[] = "{$region_name} обл.";
                    if ($district_name) $extras[] = "{$district_name} р-н.";
                    if ($ownof) $extras[] = "{$ownof} сільрада";

                    if ($extras) {
                        $value .= ' (' . implode(', ', $extras) . ')';
                    }
                }

                $results[] = [
                    'value' => $value,
                    'city_id' => $city_id,
                    'city_ua' => $city_name,
                    'region_id' => (string)($e['REGION_ID']   ?? ''),
                    'region_ua' => $region_name,
                    'district_id' => (string)($e['DISTRICT_ID'] ?? ''),
                    'district_ua' => $district_name,
                ];
            }
        }

        return $results;
    }

    /**
     * Пошук відділень
     */
    private function searchBranches(string $api_key, string $region_id, string $district_id, string $city_id): array {
        $base_url = 'https://www.ukrposhta.ua/address-classifier-ws/get_postoffices_by_city_id';

        $params = [
            'city_id' => $city_id,
            'region_id' => $region_id,
            'district_id' => $district_id,
        ];

        $query = http_build_query($params, '', '&amp;', PHP_QUERY_RFC3986);

        $final_url = $base_url . '?' . $query;

        $data = $this->doGetRequest($final_url, $api_key);

        $results = [];
        $entries = $this->extractEntries($data, 'ID');
        $allowedBranchTypes = ['МВ', 'СВ'];
        foreach ($entries as $item) {
            if (isset($item['LOCK_UA'], $item['ISVPZ'], $item['IS_NOLETTERS'], $item['TYPE_ACRONYM'])) {
                if ($item['LOCK_UA'] === 'Активний запис' && $item['ISVPZ'] === '1' && $item['IS_NOLETTERS'] !== '1' && in_array($item['TYPE_ACRONYM'], $allowedBranchTypes)) {
                    $results[] = [
                        'value' => $item['POSTINDEX'] . ', ' . $item['ADDRESS'],
                        'city_id' => $item['CITY_ID'],
                        'city_type' => $item['SHORTCITYTYPE_UA'],
                        'city_name' => $item['CITY_UA'],
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Пошук вулиць
     */
    private function searchStreets(string $api_key, string $region_id, string $district_id, string $city_id, string $term): array {
        $base_url = 'https://www.ukrposhta.ua/address-classifier-ws/get_street_by_region_id_and_district_id_and_city_id_and_street_ua';

        $params = [
            'city_id'   => $city_id,
            'street_ua' => $term
        ];
        if ($region_id !== '' && $region_id !== '0') {
            $params['region_id'] = $region_id;
        }
        if ($district_id !== '' && $district_id !== '0') {
            $params['district_id'] = $district_id;
        }

        $query     = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $final_url = $base_url . '?' . $query;

        $data = $this->doGetRequest($final_url, $api_key);

        $results = [];
        $entries = $this->extractEntries($data, 'STREET_ID');
        foreach ($entries as $e) {
            $st_id   = (string)($e['STREET_ID']   ?? '');
            $st_name = $e['STREET_UA']           ?? '';
            if ($st_id && $st_name) {
                $results[] = [
                    'value' => $st_name,
                    'ref'   => $st_id,
                    'street_ua' => $st_name
                ];
            }
        }
        return $results;
    }

    /**
     * Допоміжний метод: виконує GET із Bearer-токеном, повертає array.
     */
    private function doGetRequest(string $url, string $api_key): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Accept: application/json'
        ];
        if ($api_key) {
            $headers[] = 'Authorization: Bearer ' . $api_key;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($http_code !== 200) {
            $error = ['error' => 'Запит не успішний', 'http_code' => $http_code, 'response' => $response];
            $this->log->write($error);
            return $error;
        }

        // Спробувати як JSON
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // можливо, XML
            $xml = simplexml_load_string($response);
            if ($xml !== false) {
                $decoded = json_decode(json_encode($xml), true);
            } else {
                $decoded = [];
            }
        }

        if (!is_array($decoded)) {
            $decoded = [];
        }
        return $decoded;
    }

    /**
     * Допоміжний метод: з даних виду
     * {
     *   "Entries": {
     *     "Entry": [ {...}, {...} ]
     *   }
     * }
     * виділяє список Entry (навіть якщо там один об'єкт).
     * Якщо немає ключа $key, повертаємо []
     */
    private function extractEntries(array $data, string $key): array {
        if (empty($data['Entries']['Entry'])) {
            return [];
        }
        $entries = $data['Entries']['Entry'];
        if (isset($entries[$key])) {
            $entries = [ $entries ];
        }
        if (!is_array($entries)) {
            return [];
        }
        return $entries;
    }
}
