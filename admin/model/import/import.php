<?php
namespace Opencart\Admin\Model\Import;

class Import extends \Opencart\System\Engine\Model {
    public function __construct($registry) {
        parent::__construct($registry);
    }

    public function parseAndStore(string $feed_url, $language): array {
        $result = [];

        // 1. Очищаємо таблиці (truncate)
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_category`");
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_manufacturer`");
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_product`");
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_image`");
        $this->db->query("TRUNCATE `" . DB_PREFIX . "import_attribute`");

        // Лічильники
        $category_count = 0;
        $product_count = 0;
        $image_count = 0;
        $attribute_count = 0;
        $manufacturer_count = 0;

        // 2. Зчитаємо XML
        $xml_content = @file_get_contents($feed_url);

        if (!$xml_content) {
            $result['error'] = $language->get('error_feed_unavailable');
            return $result;
        }

        $xml = simplexml_load_string($xml_content);
        if (!$xml) {
            $result['error'] = $language->get('error_invalid_xml');
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

                if ($this->db->countAffected() > 0) {
                    $category_count++;
                }
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

                if ($this->db->countAffected() > 0) {
                    $product_count++;
                }

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

                        if ($this->db->countAffected() > 0) {
                            $image_count++;
                        }

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

                        if ($this->db->countAffected() > 0) {
                            $attribute_count++;
                        }
                    }
                }
            }

            // 5. Записуємо виробників у import_manufacturer
            foreach ($manufacturers as $key => $vendor_name) {
                $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "import_manufacturer` SET
                `name` = '" . $this->db->escape($vendor_name) . "'
            ");

                if ($this->db->countAffected() > 0) {
                    $manufacturer_count++;
                }
            }
        }


        // Якщо дійшли сюди, все ок
        $result['success_parse_feed'] = sprintf(
            $language->get('text_parse_success_summary'),
            $category_count,
            $product_count,
            $manufacturer_count,
            $image_count,
            $attribute_count
        );

        // Зберігаємо в oc_setting через спеціальний метод
        $parse_stats = [
            'date'          => date('Y-m-d H:i:s'),
            'categories'    => $category_count,
            'products'      => $product_count,
            'manufacturers' => $manufacturer_count,
            'images'        => $image_count,
            'attributes'    => $attribute_count
        ];

        $this->saveParseStats($parse_stats);

        return $result;
    }

    private function saveParseStats(array $stats): void {
        // Конвертуємо у JSON (щоб усі поля були в одному місці)
        $json_value = json_encode($stats, JSON_UNESCAPED_UNICODE);

        // Прибираємо попередній запис
        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting`
        WHERE `code` = 'import_feed'
        AND `key` = 'import_feed_stats'");

        // Додаємо новий
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET
            `store_id` = 0,
            `code` = 'import_feed',
            `key` = 'import_feed_stats',
            `value` = '" . $this->db->escape($json_value) . "'
        ");
    }

    public function getParseStats(): array {
        $q = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting`
        WHERE `code` = 'import_feed'
        AND `key` = 'import_feed_stats'");

        if ($q->num_rows) {
            return json_decode($q->row['value'], true);
        }
        return [];
    }

    public function downloadImagesChunk(int $offset, int $limit): array {
        $stats = [
            'processed' => 0,
            'downloaded' => 0,
            'skipped' => 0,
            'failed' => 0,
            'finished' => false
        ];

        // Вибираємо $limit записів з oc_import_image, починаючи з $offset
        $sql = "SELECT * FROM `" . DB_PREFIX . "import_image` ORDER BY import_image_id ASC LIMIT " . (int)$offset . "," . (int)$limit;
        $rows = $this->db->query($sql)->rows;

        // Якщо немає записів, значить все вже опрацьовано
        if (!$rows) {
            $stats['finished'] = true;
            return $stats;
        }

        foreach ($rows as $row) {
            $stats['processed']++;

            $external_id = $row['product_external_id'];
            $image_url   = $row['image_url'];

            $baseDir = DIR_IMAGE . 'catalog/products/' . $this->db->escape($external_id) . '/';
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0755, true);
            }

            $fileName = basename($image_url);
            $savePath = $baseDir . $fileName;

            if (is_file($savePath)) {
                $stats['skipped']++;
                // $this->log->write("Image skip: $savePath");
                continue;
            }

            $imageContent = @file_get_contents($image_url);
            if ($imageContent !== false) {
                file_put_contents($savePath, $imageContent);
                $stats['downloaded']++;
                // $this->log->write("Image downloaded: $savePath");
            } else {
                $stats['failed']++;
                 $this->log->write("Image download FAILED: $image_url");
            }
        }

        return $stats;
    }

    public function importCategories(): array {
        $stats = [
            'total'   => 0,
            'new'     => 0,
            'updated' => 0
        ];

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "import_category`");
        $import_rows = $query->rows;

        if (!$import_rows) {
            return $stats;
        }

        $categories_for_sort = [];
        foreach ($import_rows as $row) {
            $categories_for_sort[] = [
                'external_id'        => $row['external_id'],
                'parent_external_id' => $row['parent_external_id'],
                'name'               => $row['name']
            ];
        }

        $sortedCategories = $this->sortCategoriesByLevel($categories_for_sort);

        $stats['total'] = count($sortedCategories);

        foreach ($sortedCategories as $cat) {
            $res = $this->saveCategory($cat);

            if ($res === 'new') {
                $stats['new']++;
            } elseif ($res === 'updated') {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    private function saveCategory(array $cat_data): string {
        $external_id = $cat_data['external_id'];
        $parent_ext_id = $cat_data['parent_external_id'];
        $name = $cat_data['name'];

        $status = 1;
        $sort_order = 0;

        $parent_id = 0;
        if ($parent_ext_id !== '0') {
            $qparent = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category` WHERE `external_id` = '" . $this->db->escape($parent_ext_id) . "'");
            if ($qparent->num_rows) {
                $parent_id = (int)$qparent->row['category_id'];
            }
        }

        $q = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category` WHERE `external_id` = '" . $this->db->escape($external_id) . "'");
        if ($q->num_rows) {
            $category_id = (int)$q->row['category_id'];

            $this->db->query("UPDATE `" . DB_PREFIX . "category` SET
                `parent_id` = '" . (int)$parent_id . "',
                `sort_order` = '" . (int)$sort_order . "',
                `status` = '" . (int)$status . "',
                `date_modified` = NOW()
                WHERE `category_id` = '" . (int)$category_id . "'");

            $this->db->query("UPDATE `" . DB_PREFIX . "category_description` SET
                `name` = '" . $this->db->escape($name) . "',
                `description` = '',
                `meta_title` = '" . $this->db->escape($name) . "',
                `meta_description` = '',
                `meta_keyword` = ''
                WHERE `category_id` = '" . (int)$category_id . "'
                AND `language_id` = 2
            ");

            $this->updateCategoryPath($category_id, $parent_id);

            $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url`
                          WHERE `key` = 'path' AND `value` LIKE '" . (int)$category_id . "%'");
            $this->addSeoUrlCategory($category_id, $parent_id, $name, $external_id);

            return 'updated';
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "category` SET
                `external_id` = '" . $this->db->escape($external_id) . "',
                `parent_id` = '" . (int)$parent_id . "',
                `sort_order` = '" . (int)$sort_order . "',
                `status` = '" . (int)$status . "',
                `date_added` = NOW(),
                `date_modified` = NOW()
            ");

            $category_id = $this->db->getLastId();

            $this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` SET
                `category_id` = '" . (int)$category_id . "',
                `language_id` = '2',
                `name` = '" . $this->db->escape($name) . "',
                `description` = '',
                `meta_title` = '" . $this->db->escape($name) . "',
                `meta_description` = '',
                `meta_keyword` = ''
            ");

            $this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store` SET
                `category_id` = '" . (int)$category_id . "',
                `store_id` = '0'
            ");

            $this->updateCategoryPath($category_id, $parent_id);

            $this->addSeoUrlCategory($category_id, $parent_id, $name, $external_id);

            return 'new';
        }
    }

    private function updateCategoryPath(int $category_id, int $parent_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$category_id . "'");

        $level = 0;

        if ($parent_id > 0) {
            $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path`
                               WHERE `category_id` = '" . (int)$parent_id . "'
                               ORDER BY `level` ASC");
            foreach ($q->rows as $row) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET
                    `category_id` = '" . (int)$category_id . "',
                    `path_id` = '" . (int)$row['path_id'] . "',
                    `level` = '" . (int)$level . "'
                ");
                $level++;
            }
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET
            `category_id` = '" . (int)$category_id . "',
            `path_id` = '" . (int)$category_id . "',
            `level` = '" . (int)$level . "'
        ");
    }

    private function updateSeoUrlCategory(int $category_id, int $parent_id, string $name, string $external_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url`
                      WHERE `key` = 'path' AND `value` LIKE '" . (int)$category_id . "%'");

        $this->addSeoUrlCategory($category_id, $parent_id, $name, $external_id);
    }

    private function addSeoUrlCategory(int $category_id, int $parent_id, string $name, string $external_id): void {
        $value = $this->buildSeoValue($this->db, $parent_id, $category_id);

        $keyword = $this->buildSeoKeyword($this->db, $parent_id, $external_id, $name);

        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $this->db->escape($keyword) . "' AND `store_id` = 0");
        if ($q->num_rows) {
             throw new \Exception('SEO URL "' . $keyword . '" зайнятий.');
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET
            `store_id` = 0,
            `language_id` = 2,
            `key` = 'path',
            `value` = '" . $this->db->escape($value) . "',
            `keyword` = '" . $this->db->escape($keyword) . "'
        ");
    }

    private function sortCategoriesByLevel(array $categories): array {
        // Визначаємо рівень кожної категорії
        $levels = [];

        // Функція для визначення рівня категорії
        function determineLevel($category_id, $categories, &$levels) {
            if (isset($levels[$category_id])) {
                return $levels[$category_id];
            }

            foreach ($categories as $category) {
                if ($category['external_id'] == $category_id) {
                    if ($category['parent_external_id'] == 0) {
                        $levels[$category_id] = 1; // Головна категорія
                    } else {
                        $levels[$category_id] = determineLevel($category['parent_external_id'], $categories, $levels) + 1;
                    }
                    return $levels[$category_id];
                }
            }

            return 1; // За замовчуванням рівень 1
        }

        // Визначаємо рівні для всіх категорій
        foreach ($categories as $category) {
            $levels[$category['external_id']] = determineLevel($category['external_id'], $categories, $levels);
        }

        // Додаємо рівень до кожної категорії
        foreach ($categories as &$category) {
            $category['level'] = $levels[$category['external_id']];
        }
        unset($category);

        // Сортуємо за рівнем
        usort($categories, function ($a, $b) {
            return $a['level'] <=> $b['level'];
        });

        return $categories;
    }

    private function buildSeoValue($db, $parent_id, $category_id) {
        $path = [$category_id];

        while ($parent_id > 0) {
            $query = $db->query("SELECT `parent_id` FROM `" . DB_PREFIX . "category` WHERE `category_id` = '" . (int)$parent_id . "'");
            if ($query->num_rows) {
                $path[] = $parent_id;
                $parent_id = $query->row['parent_id'];
            } else {
                break;
            }
        }

        return implode('_', array_reverse($path));
    }

    private function buildSeoKeyword($db, $parent_id, $external_id, $name) {
        $path = [$external_id . '-' . $this->transliterate($name)];

        while ($parent_id > 0) {
            $query = $db->query("SELECT `parent_id`, `external_id`, `name` FROM `" . DB_PREFIX . "category` c 
                             LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.`category_id` = cd.`category_id` AND cd.`language_id` = 2)
                             WHERE c.`category_id` = '" . (int)$parent_id . "'");
            if ($query->num_rows) {
                $parent_external_id = $query->row['external_id'];
                $parent_name = $query->row['name'];
                $path[] = $parent_external_id . '-' . $this->transliterate($parent_name);
                $parent_id = $query->row['parent_id'];
            } else {
                break;
            }
        }

        return implode('/', array_reverse($path));
    }

    private function transliterate($string) {
        $translit_table = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g',
            'д' => 'd', 'е' => 'e', 'є' => 'ye', 'ж' => 'zh', 'з' => 'z',
            'и' => 'y', 'і' => 'i', 'ї' => 'yi', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
            'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
            'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ю' => 'yu', 'я' => 'ya', 'ь' => '', 'ъ' => '', 'ы' => 'y', 'э' => 'e',
            ' ' => '-', '_' => '-', ',' => '', '.' => '', '/' => '-', '\\' => '-',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'H', 'Ґ' => 'G',
            'Д' => 'D', 'Е' => 'E', 'Є' => 'Ye', 'Ж' => 'Zh', 'З' => 'Z',
            'И' => 'Y', 'І' => 'I', 'Ї' => 'Yi', 'Й' => 'Y', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P',
            'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F',
            'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch',
            'Ю' => 'Yu', 'Я' => 'Ya'
        ];

        $transliterated = strtr($string, $translit_table);
        $transliterated = preg_replace('/[^a-zA-Z0-9\-]/', '', $transliterated);

        return strtolower($transliterated);
    }

    public function importManufacturers(): array {
        $stats = [
            'total'   => 0,
            'new'     => 0,
            'skipped' => 0
        ];

        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "import_manufacturer`");
        $rows = $q->rows;

        if (!$rows) {
            return $stats;
        }

        $stats['total'] = count($rows);

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');

            $res = $this->saveManufacturer($name);

            if ($res === 'new') {
                $stats['new']++;
            } elseif ($res === 'skipped') {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    private function saveManufacturer(string $name): string {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "manufacturer` WHERE `name` = '" . $this->db->escape($name) . "'");

        if ($q->num_rows) {
            return 'skipped';
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` 
                SET `name` = '" . $this->db->escape($name) . "', 
                    `sort_order` = '0'
            ");

            $manufacturer_id = $this->db->getLastId();

            $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer_to_store`
                SET `manufacturer_id` = '" . (int)$manufacturer_id . "',
                    `store_id` = '0'
            ");

            $this->addSeoUrlManufacturer($manufacturer_id, $name);

            return 'new';
        }
    }

    private function addSeoUrlManufacturer(int $manufacturer_id, string $name): void {
        $keyword = $this->transliterate($name);

        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url`
            WHERE `store_id` = 0
            AND `keyword` = '" . $this->db->escape($keyword) . "'
        ");

        if ($q->num_rows) {
             throw new \Exception('SEO URL зайнятий');
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET
            `store_id` = 0,
            `language_id` = 2,
            `key` = 'manufacturer_id',
            `value` = '" . (int)$manufacturer_id . "',
            `keyword` = '" . $this->db->escape($keyword) . "'
        ");
    }

    public function importAttributes(): array {
        $stats = [
            'total'   => 0,
            'new'     => 0,
            'skipped' => 0
        ];

        // 1. Зчитаємо дані з `import_attribute`
        //    але там може бути багато рядків із однаковими `attribute_name`
        $q = $this->db->query("SELECT attribute_name FROM `" . DB_PREFIX . "import_attribute`");
        $rows = $q->rows;

        if (!$rows) {
            return $stats; // нічого імпортувати
        }

        // 2. Створюємо унікальний список назв
        $names = [];
        foreach ($rows as $row) {
            $names[ trim($row['attribute_name']) ] = true;
        }

        // Тепер у $names — унікальні ключі
        $uniqueNames = array_keys($names);

        $stats['total'] = count($uniqueNames);

        // 3. Для кожної унікальної назви викликаємо saveAttributeName()
        //    і збільшуємо лічильники
        foreach ($uniqueNames as $attrName) {
            $res = $this->saveAttributeName($attrName);

            if ($res === 'new') {
                $stats['new']++;
            } else {
                // якщо 'skip' або 'skipped', то ++$stats['skipped']
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    private function saveAttributeName(string $attrName): string {
        // 1) Шукаємо в oc_attribute_description, де language_id=2 і name=attrName
        $sql = "SELECT ad.attribute_id 
            FROM `" . DB_PREFIX . "attribute_description` ad
            JOIN `" . DB_PREFIX . "attribute` a ON (ad.attribute_id = a.attribute_id)
            WHERE ad.language_id = '2'
              AND ad.name = '" . $this->db->escape($attrName) . "'
              AND a.attribute_group_id = '1'";
        $q = $this->db->query($sql);

        if ($q->num_rows) {
            // Цей атрибут уже існує
            return 'skip';
        } else {
            // 2) Створюємо новий атрибут
            // припустимо, attribute_group_id=1, sort_order=0
            $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute` SET
                `attribute_group_id` = '1',
                `sort_order` = '0'
            ");

            $attribute_id = $this->db->getLastId();

            // 3) Додаємо назву в attribute_description
            $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute_description` SET
                `attribute_id` = '" . (int)$attribute_id . "',
                `language_id` = '2',
                `name` = '" . $this->db->escape($attrName) . "'
            ");

            return 'new';
        }
    }

    public function importProducts(): array {
        $stats = [
            'total'   => 0,
            'new'     => 0,
            'updated' => 0
        ];

        // 1. Зчитаємо дані з `import_product` (де зберігається external_id, manufacturer, category_external_id, seo_url...)
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "import_product`");
        $rows = $q->rows;

        if (!$rows) {
            return $stats; // немає товарів
        }

        $stats['total'] = count($rows);

        foreach ($rows as $row) {
            // Викликаємо saveProduct($row) — який робить INSERT/UPDATE
            $res = $this->saveProduct($row);

            if ($res === 'new') {
                $stats['new']++;
            } elseif ($res === 'updated') {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    private function saveProduct(array $p): string {
        // p містить external_id, manufacturer, name, description, price, quantity, category_external_id, seo_url, sku, status, keywords...
        $external_id  = $p['external_id'];
        $name         = $p['name'];
        $description  = $p['description'];
        $price        = (float)$p['price'];
        $quantity     = (int)$p['quantity'];
        $sku          = $p['sku'];
        $status       = (int)$p['status'];
        $keywords     = $p['keywords'];
        $seo_url      = $p['seo_url'] ?? ''; // якщо треба
        $manufacturer = $p['manufacturer'];
        $category_ext = $p['category_external_id'];

        // Шукаємо чи товар існує
        $q = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product`
                           WHERE `model` = '" . $this->db->escape($external_id) . "'");

        if ($q->num_rows) {
            // UPDATE
            $product_id = (int)$q->row['product_id'];

            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET
                `sku`       = '" . $this->db->escape($sku) . "',
                `quantity`  = '" . (int)$quantity . "',
                `price`     = '" . (float)$price . "',
                `status`    = '" . (int)$status . "',
                `date_modified` = NOW()
                WHERE `product_id` = '" . (int)$product_id . "'
            ");

            // UPDATE product_description (тут 1 мова = language_id=2)
            $this->db->query("UPDATE `" . DB_PREFIX . "product_description` SET
                `name` = '" . $this->db->escape($name) . "',
                `description` = '" . $this->db->escape($description) . "',
                `meta_title` = '" . $this->db->escape($name) . "',
                `meta_description` = '',
                `meta_keyword` = '" . $this->db->escape($keywords) . "'
                WHERE `product_id` = '" . (int)$product_id . "'
                AND `language_id` = '2'
            ");

            // UPDATE manufacturer_id
            $manufacturer_id = $this->getManufacturerIdByName($manufacturer);
            $this->db->query("UPDATE `" . DB_PREFIX . "product` 
            SET `manufacturer_id` = '" . (int)$manufacturer_id . "'
            WHERE `product_id` = '" . (int)$product_id . "'");

            // UPDATE category (product_to_category) — видалити старі, додати нові
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_category`
            WHERE `product_id` = '" . (int)$product_id . "'");

            if ($category_ext) {
                $cat_id = $this->getCategoryIdByExternalId($category_ext);
                if ($cat_id) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` SET
                    `product_id` = '" . (int)$product_id . "',
                    `category_id` = '" . (int)$cat_id . "'");
                }
            }

            // Зображення
            $this->updateProductImages($product_id, $external_id);

            // Атрибути
            $this->updateProductAttributes($product_id, $external_id);

            return 'updated';

        } else {
            // INSERT
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product` SET
                `model`         = '" . $this->db->escape($external_id) . "',
                `sku`           = '" . $this->db->escape($sku) . "',
                `quantity`      = '" . (int)$quantity . "',
                `price`         = '" . (float)$price . "',
                `status`        = '" . (int)$status . "',
                `stock_status_id` = 5,
                `variant` = '',
                `override` = '',
                `date_added`    = NOW(),
                `date_modified` = NOW()
            ");

            $product_id = $this->db->getLastId();

            // product_description
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_description` SET
                `product_id`   = '" . (int)$product_id . "',
                `language_id`  = '2',
                `name`         = '" . $this->db->escape($name) . "',
                `description`  = '" . $this->db->escape($description) . "',
                `meta_title`   = '" . $this->db->escape($name) . "',
                `meta_description` = '',
                `meta_keyword` = '" . $this->db->escape($keywords) . "'
            ");

            // manufacturer_id
            if ($manufacturer) {
                $manufacturer_id = $this->getManufacturerIdByName($manufacturer);
                $this->db->query("UPDATE `" . DB_PREFIX . "product`
                SET `manufacturer_id` = '" . (int)$manufacturer_id . "'
                WHERE `product_id` = '" . (int)$product_id . "'");
            }

            // product_to_category
            if ($category_ext) {
                $cat_id = $this->getCategoryIdByExternalId($category_ext);
                if ($cat_id) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` SET
                    `product_id` = '" . (int)$product_id . "',
                    `category_id` = '" . (int)$cat_id . "'");
                }
            }

            // product_to_store
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET
                `product_id` = '" . (int)$product_id . "',
                `store_id` = '0'
            ");

            // Зображення
            $this->updateProductImages($product_id, $external_id);

            // Атрибути
            $this->updateProductAttributes($product_id, $external_id);

            // SEO
            $this->addSeoUrlProduct($product_id, $seo_url, $name, $external_id);

            return 'new';
        }
    }

    private function getManufacturerIdByName(string $manufacturer_name): int {
        static $manufacturer_cache = []; // Кеш для уникнення повторних запитів

        // Перевірка в кеші
        if (isset($manufacturer_cache[$manufacturer_name])) {
            return $manufacturer_cache[$manufacturer_name];
        }

        // Отримання manufacturer_id з бази
        $query = $this->db->query("SELECT `manufacturer_id` FROM `" . DB_PREFIX . "manufacturer` WHERE `name` = '" . $this->db->escape($manufacturer_name) . "'");

        if ($query->num_rows > 0) {
            $manufacturer_id = $query->row['manufacturer_id'];
            $manufacturer_cache[$manufacturer_name] = $manufacturer_id; // Збереження в кеші
            return (int)$manufacturer_id;
        }

        return 0;
    }

    private function getCategoryIdByExternalId(string $category_external_id): int {
        $q = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category`
                           WHERE `external_id` = '" . $this->db->escape($category_external_id) . "'");

        if ($q->num_rows) {
            return (int)$q->row['category_id'];
        }

        return 0;
    }

    private function updateProductImages(int $product_id, string $external_id): void {
        // 1. Видаляємо старі product_image
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_image`
                      WHERE `product_id` = '" . (int)$product_id . "'");

        // 2. Отримуємо всі зображення для товару
        $q = $this->db->query("SELECT `image_url`, `main_image` FROM `" . DB_PREFIX . "import_image`
                           WHERE `product_external_id` = '" . $this->db->escape($external_id) . "'
                           ORDER BY `main_image` DESC, `import_image_id` ASC");

        if ($q->num_rows === 0) {
            return;
        }

        $sort_order = 1;
        foreach ($q->rows as $row) {
            $filename = basename(parse_url($row['image_url'], PHP_URL_PATH));
            $img_path = 'catalog/products/' . $external_id . '/' . $filename;

            if ($row['main_image'] == 1) {
                // Встановлюємо головне зображення
                $this->db->query("UPDATE `" . DB_PREFIX . "product`
                SET `image` = '" . $this->db->escape($img_path) . "'
                WHERE `product_id` = '" . (int)$product_id . "'");
            } else {
                // Додаємо додаткове зображення
                $this->db->query("INSERT INTO `" . DB_PREFIX . "product_image` SET
                `product_id` = '" . (int)$product_id . "',
                `image` = '" . $this->db->escape($img_path) . "',
                `sort_order` = '" . (int)$sort_order . "'");

                $sort_order++;
            }
        }
    }

    private function updateProductAttributes(int $product_id, string $external_id): void {
        // 1. Отримуємо всі атрибути для товару з `oc_product_attribute`
        $existing_attributes = [];
        $q_existing = $this->db->query("SELECT `attribute_id`, `text` FROM `" . DB_PREFIX . "product_attribute`
                                    WHERE `product_id` = '" . (int)$product_id . "'
                                    AND `language_id` = '2'");
        foreach ($q_existing->rows as $row) {
            $existing_attributes[$row['attribute_id']] = $row['text'];
        }

        // 2. Отримуємо всі атрибути з імпорту для цього товару
        $import_attributes = [];
        $q_import = $this->db->query("SELECT `attribute_name`, `attribute_value` FROM `" . DB_PREFIX . "import_attribute`
                                  WHERE `product_external_id` = '" . $this->db->escape($external_id) . "'");

        foreach ($q_import->rows as $row) {
            $attr_name = trim($row['attribute_name']);
            $attr_value = trim($row['attribute_value']);

            // Отримуємо attribute_id (з кешуванням)
            $attribute_id = $this->getAttributeIdByName($attr_name);
            if ($attribute_id) {
                $import_attributes[$attribute_id] = $attr_value;
            }
        }

        // 3. Визначаємо, що оновлювати, що додавати, а що видаляти
        $attributes_to_delete = array_diff_key($existing_attributes, $import_attributes); // В БД, але немає в імпорті
        $attributes_to_insert = array_diff_key($import_attributes, $existing_attributes); // В імпорті, але немає в БД
        $attributes_to_update = array_intersect_key($import_attributes, $existing_attributes); // Є в обох, але значення може відрізнятися

        // 4. Видаляємо атрибути, які більше не існують у нових даних
        if (!empty($attributes_to_delete)) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_attribute`
                          WHERE `product_id` = '" . (int)$product_id . "'
                          AND `attribute_id` IN (" . implode(',', array_keys($attributes_to_delete)) . ")");
        }

        // 5. Додаємо нові атрибути
        if (!empty($attributes_to_insert)) {
            $insert_values = [];
            foreach ($attributes_to_insert as $attribute_id => $attr_value) {
                $insert_values[] = "('" . (int)$product_id . "', '" . (int)$attribute_id . "', '2', '" . $this->db->escape($attr_value) . "')";
            }
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_attribute` (`product_id`, `attribute_id`, `language_id`, `text`) 
                          VALUES " . implode(',', $insert_values));
        }

        // 6. Оновлюємо значення атрибутів, якщо вони змінилися
        foreach ($attributes_to_update as $attribute_id => $attr_value) {
            if ($existing_attributes[$attribute_id] !== $attr_value) { // Якщо значення змінилося
                $this->db->query("UPDATE `" . DB_PREFIX . "product_attribute` 
                              SET `text` = '" . $this->db->escape($attr_value) . "' 
                              WHERE `product_id` = '" . (int)$product_id . "' 
                              AND `attribute_id` = '" . (int)$attribute_id . "' 
                              AND `language_id` = '2'");
            }
        }
    }

    private function getAttributeIdByName(string $attr_name): int {
        static $attribute_cache = []; // Локальний кеш у межах одного запиту

        // Перевірка в кеші
        if (isset($attribute_cache[$attr_name])) {
            return $attribute_cache[$attr_name];
        }

        // Отримання attribute_id з бази
        $sql = "SELECT a.attribute_id
            FROM `" . DB_PREFIX . "attribute` a
            JOIN `" . DB_PREFIX . "attribute_description` ad ON (a.attribute_id = ad.attribute_id)
            WHERE ad.language_id = '2'
              AND ad.name = '" . $this->db->escape($attr_name) . "'
              AND a.attribute_group_id = '1'";
        $q = $this->db->query($sql);

        if ($q->num_rows > 0) {
            $attribute_id = (int)$q->row['attribute_id'];
            $attribute_cache[$attr_name] = $attribute_id; // Збереження в кеші
            return $attribute_id;
        }

        // Якщо не знайдено, кешуємо значення 0, щоб уникнути зайвих запитів
        $attribute_cache[$attr_name] = 0;
        return 0;
    }

    private function addSeoUrlProduct(int $product_id, string $seo_url, string $name, string $external_id): void {
        $clean_url = parse_url($seo_url, PHP_URL_PATH); // Отримуємо шлях без домену
        $clean_url = pathinfo($clean_url, PATHINFO_FILENAME); // Видаляємо розширення

        // Перевірка унікальності
        $q = $this->db->query("SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url`
        WHERE `keyword` = '" . $this->db->escape($clean_url) . "'
        AND `store_id` = 0");

        if ($q->num_rows) {
            // Можеш кинути Exception або додати суфікс
             throw new \Exception('SEO URL зайнятий');
        }

        // Додаємо
        $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET
            `store_id` = 0,
            `language_id` = '2',
            `key` = 'product_id',
            `value` = '" . (int)$product_id . "',
            `keyword` = '" . $this->db->escape($clean_url) . "'
        ");
    }

}
