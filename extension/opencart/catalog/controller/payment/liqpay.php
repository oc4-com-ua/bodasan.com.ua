<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Payment;

class LiqPay extends \Opencart\System\Engine\Controller {
    /**
     * Метод confirm() викликається після натискання "Підтвердити замовлення" на сторінці оформлення (checkout).
     */
    public function confirm(): void {
        $this->load->language('payment/liqpay');
        $this->load->model('checkout/order');

        $order_id = $this->session->data['order_id'];

        if (!$order_id) {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }

        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }

        $public_key  = $this->config->get('payment_liqpay_public_key');
        $private_key = $this->config->get('payment_liqpay_private_key');
        $sandbox = $this->config->get('payment_liqpay_test');

        $liqpay_data = [
            'public_key'  => $public_key,
            'version'     => 3,
            'action'      => 'pay',
            'amount'      => round($order_info['total'], 2),
            'currency'    => $order_info['currency_code'],
            'description' => 'Оплата замовлення №' . $order_id,
            'order_id'    => $order_id,
            'sandbox'   => $sandbox,
            'server_url'  => $this->url->link('extension/opencart/payment/liqpay.callback', 'language=' . $this->config->get('config_language')),
            'result_url'  => $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'))
        ];

        $data = base64_encode(json_encode($liqpay_data));

        $signature = base64_encode(sha1($private_key . $data . $private_key, 1));

        $html_form = '<form id="liqpay_checkout" action="https://www.liqpay.ua/api/3/checkout" method="POST">' .
            '<input type="hidden" name="data" value="' . $data . '"/>' .
            '<input type="hidden" name="signature" value="' . $signature . '"/>' .
            '<noscript><input type="submit" value="Перейти до оплати LiqPay"/></noscript>' .
            '</form>';

        $html_form .= '<script>document.getElementById("liqpay_checkout").submit();</script>';

        $this->response->setOutput($html_form);
    }

    /**
     * Callback від LiqPay
     * LiqPay викликає цей метод POST-запитом, передає поля "data" та "signature".
     */
    public function callback(): void {
        $this->load->model('checkout/order');

        $data = $this->request->post['data'] ?? '';
        $signature = $this->request->post['signature'] ?? '';

        if (!$data || !$signature) {
            die('No data or signature');
        }

        $private_key = $this->config->get('payment_liqpay_private_key');
        $check_sign  = base64_encode(sha1($private_key . $data . $private_key, 1));

        if ($signature !== $check_sign) {
            die('Signature mismatch');
        }

        $parsed_data = json_decode(base64_decode($data), true);

        if (!isset($parsed_data['order_id']) || !isset($parsed_data['status'])) {
            die('Order ID or status not found in response');
        }

        $order_id = (int)$parsed_data['order_id'];
        $status = $parsed_data['status'];

        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info) {
            die('Order not found');
        }

        // За замовчуванням можна відстежувати такі статуси LiqPay:
        // success — успішна оплата
        // failure — неуспішна оплата
        // sandbox — тестова оплата
        // wait_accept / wait_secure та інші – ще не все завершено

        if (in_array($status, ['success', 'sandbox', 'wait_accept'])) {
            // Якщо успішна оплата або тестово успішна
            // Оновлюємо статус замовлення (наприклад, оплачене)
            $order_status_id = $this->config->get('payment_liqpay_order_status_id');
            $this->model_checkout_order->addHistory($order_id, $order_status_id, 'LiqPay payment status: ' . $status, true);

            // Після цього можна робити додаткові дії, наприклад, інтеграцію з CRM
        } else {
            $this->model_checkout_order->addHistory($order_id, $order_info['order_status_id'], 'LiqPay payment failed or not completed. Status: ' . $status, true);
        }

        echo 'ok';
        exit();
    }
}
