<?php
namespace Opencart\Catalog\Model\Checkout;

class Salesdrive extends \Opencart\System\Engine\Model {
    public function sendOrderToSalesDrive($order_id, $order_data) {
        $apiKey = SALES_DRIVE_API_KEY;

        $request_data = [
            'form' => $apiKey,
            'getResultData' => '1',
            'products' => [],
            'externalId' => $order_id,
            'fName' => $order_data['firstname'],
            'lName' => $order_data['lastname'],
            'phone' => $order_data['telephone'],
            'comment' => $order_data['comment'],
            'sajt' => 'bodasan.com.ua',
        ];

        foreach ($order_data['products'] as $product) {
            $request_data['products'][] = [
                'id' => $product['model'],
                'costPerItem' => $product['price'],
                'amount' => (string)$product['quantity'],
                'discount' => ''
            ];
        }

        if ($order_data['shipping_method']['code'] === 'nova_poshta.nova_poshta') {
            $request_data['shipping_method'] = 'id_9'; // id_9 в CRM для Нової пошти

            $shipping_address = '';
            $service_type = '';
            $warehouse_number = '';
            $city = $order_data['shipping_custom_field']['city'] ?? '';
            $city_ref = $order_data['shipping_custom_field']['city_ref'] ?? '';
            $branch = $order_data['shipping_custom_field']['branch'] ?? '';
            $branch_ref = $order_data['shipping_custom_field']['branch_ref'] ?? '';
            $postamat = $order_data['shipping_custom_field']['postamat'] ?? '';
            $postamat_ref = $order_data['shipping_custom_field']['postamat_ref'] ?? '';
            $street = $order_data['shipping_custom_field']['street'] ?? '';
            $street_ref = $order_data['shipping_custom_field']['street_ref'] ?? '';
            $building_number = $order_data['shipping_custom_field']['house'] ?? '';
            $flat = $order_data['shipping_custom_field']['flat'] ?? '';

            if ($order_data['shipping_custom_field']['delivery_type'] === 'branch') {
                $service_type = 'Warehouse';
                $warehouse_number = $branch_ref;
                $shipping_address = "{$city}, {$branch}";
            }

            if ($order_data['shipping_custom_field']['delivery_type'] === 'postamat') {
                $service_type = 'Warehouse';
                $warehouse_number = $postamat_ref;
                $shipping_address = "{$city}, {$postamat}";
            }

            if ($order_data['shipping_custom_field']['delivery_type'] === 'courier') {
                $service_type = 'Doors';
                $shipping_address = "{$city}, {$street}";
                if ($building_number) {
                    $shipping_address .= ", буд.{$building_number}";
                }
                if ($flat) {
                    $shipping_address .= ", кв.{$flat}";
                }
            }

            $np_data = [
                'ServiceType' => $service_type,
                'payer' => 'recipient',
                'city' => $city_ref,
                'WarehouseNumber' => $warehouse_number,
                "Street" => $street_ref,
                "BuildingNumber" => $building_number,
                "Flat" => $flat,
            ];

            $request_data['shipping_address'] = $shipping_address;
            $request_data['novaposhta'] = $np_data;
        }

         /*if ($order_data['shipping_method']['code'] === 'ukr_poshta.ukr_poshta') {
             $request_data['shipping_method'] = 'id_16'; // id_9 в CRM для Укрпошти

             $shipping_address = '';
             $service_type = '';
             $warehouse_number = '';
             $city = $order_data['shipping_custom_field']['city'] ?? '';
             $region_id = $order_data['shipping_custom_field']['region_id'] ?? '';
             $region_ua = $order_data['shipping_custom_field']['region_ua'] ?? '';
             $district_id = $order_data['shipping_custom_field']['district_id'] ?? '';
             $district_ua = $order_data['shipping_custom_field']['district_ua'] ?? '';
             $city_id = $order_data['shipping_custom_field']['city_id'] ?? '';
             $city_ua = $order_data['shipping_custom_field']['city_ua'] ?? '';
             $branch = $order_data['shipping_custom_field']['branch'] ?? '';
             $branch_id = $order_data['shipping_custom_field']['branch_id'] ?? '';
             $branch_index = $order_data['shipping_custom_field']['branch_index'] ?? '';
             $branch_street = $order_data['shipping_custom_field']['branch_street'] ?? '';
             $branch_building_no = $order_data['shipping_custom_field']['branch_building_no'] ?? '';
             $branch_name_ua = $order_data['shipping_custom_field']['branch_name_ua'] ?? '';
             $courier_street = $order_data['shipping_custom_field']['courier_street'] ?? '';
             $courier_street_id = $order_data['shipping_custom_field']['courier_street_id'] ?? '';
             $courier_street_ua = $order_data['shipping_custom_field']['courier_street_ua'] ?? '';
             $courier_house = $order_data['shipping_custom_field']['courier_house'] ?? '';
             $courier_flat = $order_data['shipping_custom_field']['courier_flat'] ?? '';

             if ($order_data['shipping_custom_field']['delivery_type'] === 'branch') {
                 $service_type = 'Warehouse';
                 $warehouse_number = $branch_ref;
                 $shipping_address = "{$city}, {$branch}";
             }

             if ($order_data['shipping_custom_field']['delivery_type'] === 'courier') {
                 $service_type = 'Doors';
                 $shipping_address = "{$city}, {$street}";
                 if ($building_number) {
                     $shipping_address .= ", буд.{$building_number}";
                 }
                 if ($flat) {
                     $shipping_address .= ", кв.{$flat}";
                 }
             }

             $ukr_data = [
                 'ServiceType' => $service_type,
                 'payer' => 'recipient',
                 'city' => $city_ref,
                 'WarehouseNumber' => $warehouse_number,
                 "Street" => $street_ref,
                 "BuildingNumber" => $building_number,
                 "Flat" => $flat,
             ];

             $request_data['shipping_address'] = $shipping_address;
             $request_data['ukrposhta'] = $ukr_data;
         }*/

        $request_data['payment_method'] = '';

        if ($order_data['payment_method']['code'] === 'cod.cod') {
            $request_data['payment_method'] = 'id_12'; // id_12 - в CRM для Післяплати
        } else if ($order_data['payment_method']['code'] === 'liqpay.liqpay') {
            $request_data['payment_method'] = 'LiqPay';
        }

        $json_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://4-sport.salesdrive.me/handler/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log->write('SalesDrive API error: ' . $error);
            return false;
        }

        $response_data = json_decode($response, true);

        if (isset($response_data['success']) && $response_data['success'] === true) {
            if (isset($response_data['data']['orderId'])) {
                return $response_data['data']['orderId'];
            } else {
                $this->log->write('SalesDrive API response missing orderId.');
                return false;
            }
        } else {
            $this->log->write('SalesDrive API responded with error: ' . $response);
            return false;
        }
    }

    public function updateOrderPaymentStatus($order_id, $salesdrive_status) {
        $apiKey = SALES_DRIVE_API_KEY;

        $request_data = [
            'form'       => $apiKey,
            'externalId' => $order_id,
            'getResultData' => '1',
            'data' => [
                'statusOplati' => $salesdrive_status
            ]
        ];

        $json_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://4-sport.salesdrive.me/api/order/update/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            $this->log->write('SalesDrive UPDATE payment status error: ' . $error . '; httpCode: ' . $httpCode);
        } else {
            $this->log->write('SalesDrive response (payment status updated): ' . $response . '; httpCode: ' . $httpCode);
        }
    }
}
