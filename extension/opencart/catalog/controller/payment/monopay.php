<?php
declare(strict_types=1);

namespace Opencart\Catalog\Controller\Extension\Opencart\Payment;

class Monopay extends \Opencart\System\Engine\Controller {

    /**
     * Викликається одразу після «Підтвердити замовлення»
     */
    public function confirm(): void {
        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'] ?? 0;

        if (!$order_id) {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }

        $order = $this->model_checkout_order->getOrder($order_id);

        $token = $this->config->get('payment_monopay_token');
        if (!$token) {
            $this->session->data['error'] = 'Monopay token is empty';
            $this->response->redirect($this->url->link('checkout/checkout'));
            return;
        }

        $body = [
            'amount' => (int)round($order['total'] * 100), // копійки
            'ccy' => 980,
            'redirectUrl' => $this->url->link('checkout/success', 'order_id=' . $order_id),
            'webHookUrl' => $this->url->link('extension/opencart/payment/monopay.callback'),
            'merchantPaymInfo' => [
                'reference' => (string)$order_id,
                'destination' => 'Оплата замовлення №' . $order_id
            ]
        ];

        $ch = curl_init('https://api.monobank.ua/api/merchant/invoice/create');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Token: '       . $token,
                'X-Cms: OpenCart',
                'X-Cms-Version: 4.1.0.0'
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE)
        ]);
        $response = curl_exec($ch);
        $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode((string)$response, true);

        if ($http !== 200 || empty($result['pageUrl'])) { // у відповіді також завжди є invoiceId
            $this->session->data['error'] = 'Monopay error: ' . $response;
            $this->response->redirect($this->url->link('checkout/checkout'));
            return;
        }

        $this->response->redirect($result['pageUrl']);
    }

    /**
     * WebHook від monobank. monobank передає сирий JSON у тілі POST
     * та підпис у заголовку X-Sign.
     */
    public function callback(): void {
        $this->load->model('checkout/order');

        $rawBody = file_get_contents('php://input');
        $data    = json_decode($rawBody, true);

        $this->log->write('callback()');
        $this->log->write($data);

        if (!isset($data['reference'], $data['status'])) {
            http_response_code(400);
            exit('Bad callback');
        }

        $order_id = (int)$data['reference'];
        $status   = $data['status'];

        $order = $this->model_checkout_order->getOrder($order_id);
        if (!$order) {
            http_response_code(404);
            exit('Order not found');
        }

        if ($status === 'success') {
            $order_status_id = $this->config->get('payment_monopay_order_status_id');
            $this->model_checkout_order->addHistory($order_id, $order_status_id, 'Monopay: ' . $status, true);

            $this->load->model('checkout/salesdrive');
            $this->model_checkout_salesdrive->updateOrderPaymentStatus($order_id, 'paid');
        } elseif ($status === 'expired' || $status === 'failure') {
            $this->model_checkout_order->addHistory($order_id, $order['order_status_id'], 'Monopay failed: ' . $status, true);
        }

        echo 'OK';
    }
}
