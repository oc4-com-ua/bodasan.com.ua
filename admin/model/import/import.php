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

}
