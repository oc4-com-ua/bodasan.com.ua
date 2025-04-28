<?php
namespace Opencart\Catalog\Controller\Feed;

class GoogleMerchant extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->response->addHeader('Content-Type: application/xml');

        $this->load->model('catalog/product');

        $products = $this->model_catalog_product->getProducts();

        $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $output .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $output .= '<channel>' . "\n";
        $output .= '<title><![CDATA[' . $this->config->get('config_name') . ']]></title>' . "\n";
        $output .= '<link>' . HTTP_SERVER . '</link>' . "\n";

        foreach ($products as $product) {
            $output .= '<item>' . "\n";
            $output .= '<g:id>' . $product['model'] . '</g:id>' . "\n";
            $output .= '<g:title><![CDATA[' . $product['name'] . ']]></g:title>' . "\n";
            $output .= '<g:description><![CDATA[' . strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8')) . ']]></g:description>' . "\n";
            $output .= '<g:link>' . htmlspecialchars($this->url->link('product/product', 'product_id=' . $product['product_id'])) . '</g:link>' . "\n";

            if ($product['image']) {
                $output .= '<g:image_link>' . htmlspecialchars(HTTP_SERVER . 'image/' . $product['image']) . '</g:image_link>' . "\n";
            }

            $output .= '<g:availability>' . ($product['quantity'] > 0 ? 'in stock' : 'out of stock') . '</g:availability>' . "\n";

            $price = number_format($product['price'], 2, '.', '');

            if (!empty($product['old_price']) && $product['old_price'] > $product['price']) {
                $old_price = number_format($product['old_price'], 2, '.', '');
                $output .= '<g:price>' . $old_price . ' UAH</g:price>' . "\n";
                $output .= '<g:sale_price>' . $price . ' UAH</g:sale_price>' . "\n";
            } else {
                $output .= '<g:price>' . $price . ' UAH</g:price>' . "\n";
            }

            if (!empty($product['manufacturer_id'])) {
                $this->load->model('catalog/manufacturer');
                $manufacturer = $this->model_catalog_manufacturer->getManufacturer((int)$product['manufacturer_id']);

                if (!empty($manufacturer['name'])) {
                    $output .= '<g:brand><![CDATA[' . $manufacturer['name'] . ']]></g:brand>' . "\n";
                } else {
                    $output .= '<g:brand><![CDATA[Unknown]]></g:brand>' . "\n";
                }
            } else {
                $output .= '<g:brand><![CDATA[Unknown]]></g:brand>' . "\n";
            }

            $this->load->model('catalog/product');
            $this->load->model('catalog/category');

            $product_categories = $this->model_catalog_product->getCategories((int)$product['product_id']);

            if (!empty($product_categories)) {
                $category_id = (int)$product_categories[0]['category_id'];
                $category_info = $this->model_catalog_category->getCategory($category_id);

                if (!empty($category_info['name'])) {
                    $output .= '<g:product_type><![CDATA[' . $category_info['name'] . ']]></g:product_type>' . "\n";
                }
            }

            $output .= '<g:condition>new</g:condition>' . "\n";
            $output .= '</item>' . "\n";
        }

        $output .= '</channel>' . "\n";
        $output .= '</rss>';

        $this->response->setOutput($output);
    }
}
