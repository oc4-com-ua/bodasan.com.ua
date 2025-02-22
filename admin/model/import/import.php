<?php
namespace Opencart\Admin\Model\Import;

class Import extends \Opencart\System\Engine\Model {
    public function __construct($registry) {
        parent::__construct($registry);
    }

    public function parseAndStore(string $feed_url): array {
        $result = [];

        // 1. Очищаємо таблиці (truncate)
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_category`");
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_manufacturer`");
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_product`");
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_image`");
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_attribute`");

        // 2. Зчитаємо XML
        $xml_content = @file_get_contents($feed_url);

        if (!$xml_content) {
            $result['error'] = 'Не вдалося отримати вміст фіда. Перевір URL!';
            return $result;
        }

        $xml = simplexml_load_string($xml_content);
        if (!$xml) {
            $result['error'] = 'XML фід пошкоджений або неможливо розпарсити!';
            return $result;
        }

        // 3. Парсимо категорії
        if (isset($xml->shop->categories->category)) {
            foreach ($xml->shop->categories->category as $category) {
                $external_id = (string)$category['id'];
                $parent_id   = (string)$category['parentId'];
                $name        = trim((string)$category);

                $this->db->query("INSERT INTO `" . DB_PREFIX . "import_category` SET
                `external_id` = '" . $this->db->escape($external_id) . "',
                `parent_external_id` = '" . $this->db->escape($parent_id) . "',
                `name` = '" . $this->db->escape($name) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()
            ");
            }
        }

        // 4. Парсимо offers (товари)
        if (isset($xml->shop->offers->offer)) {
            // Заводимо масив для зберігання унікальних виробників
            $manufacturers = [];

            foreach ($xml->shop->offers->offer as $offer) {
                // Основні поля
                $external_id   = (string)$offer['id'];
                $available     = (string)$offer['available'];
                $name          = (string)$offer->name;
                $description   = (string)$offer->description;
                $price         = (float)$offer->price;
                $quantity      = (int)($offer->quantity_in_stock ?? 0);
                $keywords      = (string)$offer->keywords;
                $vendor        = (string)$offer->vendor; // виробник
                $sku           = (string)$offer->vendorCode;
                $url           = (string)$offer->url;

                // categoryId
                $category_external_id = null;
                if (isset($offer->categoryId)) {
                    $category_external_id = (string)$offer->categoryId[0];
                }

                // «Статус» можна визначити на основі 'available' або залишити 1
                $status = ($available === 'true') ? 1 : 0;

                // Додаємо унікального виробника в масив
                if ($vendor) {
                    // можна все в lower, якщо боїшся дублікатів
                    $vendors_key = mb_strtolower($vendor);
                    $manufacturers[$vendors_key] = $vendor;
                }

                // Запис у import_product
                $this->db->query("INSERT INTO `" . DB_PREFIX . "import_product` SET
                `external_id` = '" . $this->db->escape($external_id) . "',
                `manufacturer` = '" . $this->db->escape($vendor) . "',
                `name` = '" . $this->db->escape($name) . "',
                `description` = '" . $this->db->escape($description) . "',
                `price` = '" . (float)$price . "',
                `quantity` = '" . (int)$quantity . "',
                `keywords` = '" . $this->db->escape($keywords) . "',
                `category_external_id` = '" . $this->db->escape($category_external_id) . "',
                `status` = '" . (int)$status . "',
                `sku` = '" . $this->db->escape($sku) . "',
                `seo_url` = '" . $this->db->escape($url) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()
            ");

                // Зображення
                if (isset($offer->picture)) {
                    $is_first_image = true;

                    foreach ($offer->picture as $picture) {
                        $picture_url = (string)$picture;

                        $this->db->query("INSERT INTO `" . DB_PREFIX . "import_image` SET
                            `product_external_id` = '" . $this->db->escape($external_id) . "',
                            `image_url` = '" . $this->db->escape($picture_url) . "',
                            `main_image` = '" . ($is_first_image ? 1 : 0) . "'
                        ");

                        $is_first_image = false;
                    }
                }

                // Атрибути (param)
                if (isset($offer->param)) {
                    foreach ($offer->param as $param) {
                        $param_name  = (string)$param['name'];
                        $param_value = (string)$param;

                        $this->db->query("INSERT INTO `" . DB_PREFIX . "import_attribute` SET
                        `product_external_id` = '" . $this->db->escape($external_id) . "',
                        `attribute_name` = '" . $this->db->escape($param_name) . "',
                        `attribute_value` = '" . $this->db->escape($param_value) . "'
                    ");
                    }
                }
            }

            // 5. Записуємо виробників у import_manufacturer
            foreach ($manufacturers as $key => $vendor_name) {
                $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "import_manufacturer` SET
                `name` = '" . $this->db->escape($vendor_name) . "'
            ");
            }
        }

        // Якщо дійшли сюди, все ок
        $result['success'] = 'OK';
        return $result;
    }
}
