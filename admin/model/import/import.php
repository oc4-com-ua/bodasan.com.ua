<?php
namespace Opencart\Admin\Model\Import;

class Import extends \Opencart\System\Engine\Model {
    public function __construct($registry) {
        parent::__construct($registry);
    }

    public function parseAndStore(string $feed_url, $language): array {
        $result = [];

        $category_count = 0;
        $product_count = 0;
        $image_count = 0;
        $video_count = 0;
        $attribute_count = 0;
        $manufacturer_count = 0;

        try {
            $xml_content = @file_get_contents($feed_url);

            if ($xml_content === false) {
                $error_info = error_get_last();
                $this->log->write("file_get_contents error: " . ($error_info['message'] ?? 'unknown'));
                $result['error'] = $language->get('error_feed_unavailable');
                return $result;
            }

            $xml = simplexml_load_string($xml_content);
            if (!$xml) {
                $this->log->write("XML parse error for feed: {$feed_url}");
                $result['error'] = $language->get('error_invalid_xml');
                return $result;
            }

            $this->db->query("TRUNCATE `" . DB_PREFIX . "import_category`");
            $this->db->query("TRUNCATE `" . DB_PREFIX . "import_manufacturer`");
            $this->db->query("TRUNCATE `" . DB_PREFIX . "import_product`");
            $this->db->query("TRUNCATE `" . DB_PREFIX . "import_image`");
            $this->db->query("TRUNCATE `" . DB_PREFIX . "import_video`");
            $this->db->query("TRUNCATE `" . DB_PREFIX . "import_attribute`");

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

            if (isset($xml->shop->offers->offer)) {
                $manufacturers = [];

                foreach ($xml->shop->offers->offer as $offer) {
                    $external_id   = (string)$offer['id'];
                    $available     = (string)$offer['available'];
                    $name          = (string)$offer->name;
                    $description   = (string)$offer->description;
                    $price         = (float)$offer->price;
                    $quantity      = (int)($offer->quantity_in_stock ?? 0);
                    $keywords      = (string)$offer->keywords;
                    $vendor        = (string)$offer->vendor;
                    $sku           = (string)$offer->vendorCode;
                    $url           = (string)$offer->url;

                    $category_external_id = null;
                    if (isset($offer->categoryId)) {
                        $category_external_id = (string)$offer->categoryId[0];
                    }

                    $status = 1;

                    if ($vendor) {
                        $vendors_key = mb_strtolower($vendor);
                        $manufacturers[$vendors_key] = $vendor;
                    }

                    $videos = $this->extractIframesAndClean($description);

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

                    if ($videos) {
                        foreach ($videos as $video) {
                            $this->db->query("INSERT INTO `" . DB_PREFIX . "import_video` SET
                                `product_external_id` = '" . $this->db->escape($external_id) . "',
                                `video` = '" . $this->db->escape($video) . "'
                            ");

                            if ($this->db->countAffected() > 0) {
                                $video_count++;
                            }
                        }
                    }

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

                foreach ($manufacturers as $key => $vendor_name) {
                    $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "import_manufacturer` SET
                        `name` = '" . $this->db->escape($vendor_name) . "'
                    ");

                    if ($this->db->countAffected() > 0) {
                        $manufacturer_count++;
                    }
                }
            }

            $result['success_parse_feed'] = sprintf(
                $language->get('text_parse_success_summary'),
                $category_count,
                $product_count,
                $manufacturer_count,
                $image_count,
                $video_count,
                $attribute_count
            );

            $parse_stats = [
                'date'          => date('Y-m-d H:i:s'),
                'categories'    => $category_count,
                'products'      => $product_count,
                'manufacturers' => $manufacturer_count,
                'images'        => $image_count,
                'videos'        => $video_count,
                'attributes'    => $attribute_count
            ];

            $this->saveParseStats($parse_stats);

            $this->log->write("parseAndStore completed successfully");
        } catch (\Exception $e) {
            $this->log->write("parseAndStore exception: " . $e->getMessage());
            $result['error'] = $language->get('error_during_parsing') . ' ' . $e->getMessage();
        }

        return $result;
    }

    private function saveParseStats(array $stats): void {
        $json_value = json_encode($stats, JSON_UNESCAPED_UNICODE);

        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting`
        WHERE `code` = 'import_feed'
        AND `key` = 'import_feed_stats'");

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

    private function extractIframesAndClean(&$html) {
        $videos = [];

        $iframe_pattern = '#<iframe.*?src\s*=\s*[\'"](.*?)[\'"].*?</iframe>#is';

        preg_match_all($iframe_pattern, $html, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $src) {
                $src = preg_replace('#\?.*$#', '', $src);

                if (preg_match('#^//#', $src)) {
                    $src = 'https:' . $src;
                } elseif (!preg_match('#^https?://#i', $src)) {
                    $src = 'https://' . ltrim($src, '/');
                }
                $videos[] = $src;
            }
        }

        $html = preg_replace('#<iframe.*?</iframe>#is', '', $html);

        $html = preg_replace('#<p>(\s|&nbsp;)*</p>#i', '', $html);
        $html = preg_replace('#<(\w+)>(\s|&nbsp;)*</\1>#i', '', $html);
        $html = trim($html);

        return $videos;
    }

    public function downloadImagesChunk(int $offset, int $limit): array {
        $stats = [
            'processed' => 0,
            'downloaded' => 0,
            'skipped' => 0,
            'failed' => 0,
            'finished' => false
        ];

        $sql = "SELECT * FROM `" . DB_PREFIX . "import_image` ORDER BY import_image_id ASC LIMIT " . (int)$offset . "," . (int)$limit;
        $rows = $this->db->query($sql)->rows;

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

        $top_level_categories = array_filter($import_rows, function($row) {
            return empty($row['parent_external_id']);
        });

        $stats['total'] = count($top_level_categories);

        foreach ($top_level_categories as $cat) {
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
        $name = $cat_data['name'];

        $q = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category` WHERE `external_id` = '" . $this->db->escape($external_id) . "'");

        if ($q->num_rows) {
            $category_id = (int)$q->row['category_id'];

            $this->db->query("UPDATE `" . DB_PREFIX . "category` SET
                `parent_id` = '0',
                `sort_order` = '0',
                `status` = '1',
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

            $this->updateCategoryPath($category_id);

            $this->updateSeoUrlCategory($category_id, $external_id, $name);

            return 'updated';
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "category` SET
                `external_id` = '" . $this->db->escape($external_id) . "',
                `parent_id` = '0',
                `sort_order` = '0',
                `status` = '1',
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

            $this->updateCategoryPath($category_id);

            $this->addSeoUrlCategory($category_id, $external_id, $name);

            return 'new';
        }
    }

    private function updateCategoryPath(int $category_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$category_id . "'");

        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET
            `category_id` = '" . (int)$category_id . "',
            `path_id` = '" . (int)$category_id . "',
            `level` = '0'
        ");
    }

    private function updateSeoUrlCategory(int $category_id, string $external_id, string $name): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'path' AND `value` LIKE '" . (int)$category_id . "%'");

        $this->addSeoUrlCategory($category_id, $external_id, $name);
    }

    private function addSeoUrlCategory(int $category_id, string $external_id, string $name): void {
        $keyword = $external_id . '-' . $this->transliterate($name);

        $q = $this->db->query("SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url`
                               WHERE `keyword` = '" . $this->db->escape($keyword) . "'
                               AND `store_id` = 0");
        if ($q->num_rows) {
            $keyword .= '-2';
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET
            `store_id` = 0,
            `language_id` = 2,
            `key` = 'path',
            `value` = '" . (int)$category_id . "',
            `keyword` = '" . $this->db->escape($keyword) . "'
        ");
    }

    private function getTopLevelExternalId(string $ext_id): string {
        while (true) {
            $q = $this->db->query("SELECT parent_external_id FROM `" . DB_PREFIX . "import_category` WHERE external_id = '" . $this->db->escape($ext_id) . "'");

            if (!$q->num_rows) {
                return $ext_id;
            }

            $parent = $q->row['parent_external_id'];
            if (empty($parent)) {
                return $ext_id;
            }

            $ext_id = $parent;
        }
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

        $q = $this->db->query("SELECT attribute_name FROM `" . DB_PREFIX . "import_attribute`");
        $rows = $q->rows;

        if (!$rows) {
            return $stats;
        }

        $names = [];
        foreach ($rows as $row) {
            $names[ trim($row['attribute_name']) ] = true;
        }

        $uniqueNames = array_keys($names);

        $stats['total'] = count($uniqueNames);

        foreach ($uniqueNames as $attrName) {
            $res = $this->saveAttributeName($attrName);

            if ($res === 'new') {
                $stats['new']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    private function saveAttributeName(string $attrName): string {
        $sql = "SELECT ad.attribute_id 
            FROM `" . DB_PREFIX . "attribute_description` ad
            JOIN `" . DB_PREFIX . "attribute` a ON (ad.attribute_id = a.attribute_id)
            WHERE ad.language_id = '2'
              AND ad.name = '" . $this->db->escape($attrName) . "'
              AND a.attribute_group_id = '1'";
        $q = $this->db->query($sql);

        if ($q->num_rows) {
            return 'skip';
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute` SET
                `attribute_group_id` = '1',
                `sort_order` = '0'
            ");

            $attribute_id = $this->db->getLastId();

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

        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "import_product`");
        $rows = $q->rows;

        if (!$rows) {
            return $stats;
        }

        $stats['total'] = count($rows);

        foreach ($rows as $row) {
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
        $external_id  = $p['external_id'];
        $name         = $p['name'];
        $description  = $p['description'];
        $price        = (float)$p['price'];
        $quantity     = (int)$p['quantity'];
        $sku          = $p['sku'];
        $status       = (int)$p['status'];
        $keywords     = $p['keywords'];
        $seo_url      = $p['seo_url'];
        $manufacturer = $p['manufacturer'];
//        $category_ext = $p['category_external_id'];
        $category_ext = $this->getTopLevelExternalId($p['category_external_id']);

        $q = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product`
                           WHERE `model` = '" . $this->db->escape($external_id) . "'");

        if ($q->num_rows) {
            $product_id = (int)$q->row['product_id'];

            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET
                `sku`       = '" . $this->db->escape($sku) . "',
                `quantity`  = '" . (int)$quantity . "',
                `price`     = '" . (float)$price . "',
                `status`    = '" . (int)$status . "',
                `tax_class_id` = 0,
                `date_available` = '" . date('Y-m-d', strtotime('-1 day')) . "',
                `date_modified` = NOW()
                WHERE `product_id` = '" . (int)$product_id . "'
            ");

            $this->db->query("UPDATE `" . DB_PREFIX . "product_description` SET
                `name` = '" . $this->db->escape($name) . "',
                `description` = '" . $this->db->escape($description) . "',
                `meta_title` = '" . $this->db->escape($name) . "',
                `meta_description` = '',
                `meta_keyword` = '" . $this->db->escape($keywords) . "'
                WHERE `product_id` = '" . (int)$product_id . "'
                AND `language_id` = '2'
            ");

            $manufacturer_id = $this->getManufacturerIdByName($manufacturer);
            $this->db->query("UPDATE `" . DB_PREFIX . "product` 
            SET `manufacturer_id` = '" . (int)$manufacturer_id . "'
            WHERE `product_id` = '" . (int)$product_id . "'");

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

            $this->updateProductImages($product_id, $external_id);

            $this->updateProductVideos($product_id, $external_id);

            $this->updateProductAttributes($product_id, $external_id);

            return 'updated';
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product` SET
                `model`         = '" . $this->db->escape($external_id) . "',
                `sku`           = '" . $this->db->escape($sku) . "',
                `quantity`      = '" . (int)$quantity . "',
                `price`         = '" . (float)$price . "',
                `status`        = '" . (int)$status . "',
                `stock_status_id` = 5,
                `variant` = '',
                `override` = '',
                `tax_class_id` = 0,
                `date_available` = '" . date('Y-m-d', strtotime('-1 day')) . "',
                `date_added`    = NOW(),
                `date_modified` = NOW()
            ");

            $product_id = $this->db->getLastId();

            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_description` SET
                `product_id`   = '" . (int)$product_id . "',
                `language_id`  = '2',
                `name`         = '" . $this->db->escape($name) . "',
                `description`  = '" . $this->db->escape($description) . "',
                `meta_title`   = '" . $this->db->escape($name) . "',
                `meta_description` = '',
                `meta_keyword` = '" . $this->db->escape($keywords) . "'
            ");

            if ($manufacturer) {
                $manufacturer_id = $this->getManufacturerIdByName($manufacturer);
                $this->db->query("UPDATE `" . DB_PREFIX . "product`
                SET `manufacturer_id` = '" . (int)$manufacturer_id . "'
                WHERE `product_id` = '" . (int)$product_id . "'");
            }

            if ($category_ext) {
                $cat_id = $this->getCategoryIdByExternalId($category_ext);
                if ($cat_id) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` SET
                    `product_id` = '" . (int)$product_id . "',
                    `category_id` = '" . (int)$cat_id . "'");
                }
            }

            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET
                `product_id` = '" . (int)$product_id . "',
                `store_id` = '0'
            ");

            $this->updateProductImages($product_id, $external_id);

            $this->updateProductVideos($product_id, $external_id);

            $this->updateProductAttributes($product_id, $external_id);

            $this->addSeoUrlProduct($product_id, $seo_url, $name, $external_id);

            return 'new';
        }
    }

    private function getManufacturerIdByName(string $manufacturer_name): int {
        static $manufacturer_cache = [];

        if (isset($manufacturer_cache[$manufacturer_name])) {
            return $manufacturer_cache[$manufacturer_name];
        }

        $query = $this->db->query("SELECT `manufacturer_id` FROM `" . DB_PREFIX . "manufacturer` WHERE `name` = '" . $this->db->escape($manufacturer_name) . "'");

        if ($query->num_rows > 0) {
            $manufacturer_id = $query->row['manufacturer_id'];
            $manufacturer_cache[$manufacturer_name] = $manufacturer_id;
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
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_image`
                      WHERE `product_id` = '" . (int)$product_id . "'");

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
                $this->db->query("UPDATE `" . DB_PREFIX . "product`
                SET `image` = '" . $this->db->escape($img_path) . "'
                WHERE `product_id` = '" . (int)$product_id . "'");
            } else {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "product_image` SET
                    `product_id` = '" . (int)$product_id . "',
                    `image` = '" . $this->db->escape($img_path) . "',
                    `sort_order` = '" . (int)$sort_order . "'
                ");

                $sort_order++;
            }
        }
    }

    private function updateProductVideos(int $product_id, string $external_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_video`
                      WHERE `product_id` = '" . (int)$product_id . "'");

        $q = $this->db->query("SELECT `video` FROM `" . DB_PREFIX . "import_video`
                           WHERE `product_external_id` = '" . $this->db->escape($external_id) . "'");

        if ($q->num_rows === 0) {
            return;
        }

        $sort_order = 1;
        foreach ($q->rows as $row) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_video` SET
                `product_id` = '" . (int)$product_id . "',
                `video` = '" . $this->db->escape($row['video']) . "',
                `sort_order` = '" . (int)$sort_order . "'
            ");

            $sort_order++;
        }
    }

    private function updateProductAttributes(int $product_id, string $external_id): void {
        $existing_attributes = [];
        $q_existing = $this->db->query("SELECT `attribute_id`, `text` FROM `" . DB_PREFIX . "product_attribute`
                                    WHERE `product_id` = '" . (int)$product_id . "'
                                    AND `language_id` = '2'");
        foreach ($q_existing->rows as $row) {
            $existing_attributes[$row['attribute_id']] = $row['text'];
        }

        $import_attributes = [];
        $q_import = $this->db->query("SELECT `attribute_name`, `attribute_value` FROM `" . DB_PREFIX . "import_attribute`
                                  WHERE `product_external_id` = '" . $this->db->escape($external_id) . "'");

        foreach ($q_import->rows as $row) {
            $attr_name = trim($row['attribute_name']);
            $attr_value = trim($row['attribute_value']);

            $attribute_id = $this->getAttributeIdByName($attr_name);
            if ($attribute_id) {
                $import_attributes[$attribute_id] = $attr_value;
            }
        }

        $attributes_to_delete = array_diff_key($existing_attributes, $import_attributes);
        $attributes_to_insert = array_diff_key($import_attributes, $existing_attributes);
        $attributes_to_update = array_intersect_key($import_attributes, $existing_attributes);

        if (!empty($attributes_to_delete)) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_attribute`
                          WHERE `product_id` = '" . (int)$product_id . "'
                          AND `attribute_id` IN (" . implode(',', array_keys($attributes_to_delete)) . ")");
        }

        if (!empty($attributes_to_insert)) {
            $insert_values = [];
            foreach ($attributes_to_insert as $attribute_id => $attr_value) {
                $insert_values[] = "('" . (int)$product_id . "', '" . (int)$attribute_id . "', '2', '" . $this->db->escape($attr_value) . "')";
            }
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_attribute` (`product_id`, `attribute_id`, `language_id`, `text`) 
                          VALUES " . implode(',', $insert_values));
        }

        foreach ($attributes_to_update as $attribute_id => $attr_value) {
            if ($existing_attributes[$attribute_id] !== $attr_value) {
                $this->db->query("UPDATE `" . DB_PREFIX . "product_attribute` 
                              SET `text` = '" . $this->db->escape($attr_value) . "' 
                              WHERE `product_id` = '" . (int)$product_id . "' 
                              AND `attribute_id` = '" . (int)$attribute_id . "' 
                              AND `language_id` = '2'");
            }
        }
    }

    private function getAttributeIdByName(string $attr_name): int {
        static $attribute_cache = [];

        if (isset($attribute_cache[$attr_name])) {
            return $attribute_cache[$attr_name];
        }

        $sql = "SELECT a.attribute_id
            FROM `" . DB_PREFIX . "attribute` a
            JOIN `" . DB_PREFIX . "attribute_description` ad ON (a.attribute_id = ad.attribute_id)
            WHERE ad.language_id = '2'
              AND ad.name = '" . $this->db->escape($attr_name) . "'
              AND a.attribute_group_id = '1'";
        $q = $this->db->query($sql);

        if ($q->num_rows > 0) {
            $attribute_id = (int)$q->row['attribute_id'];
            $attribute_cache[$attr_name] = $attribute_id;
            return $attribute_id;
        }

        $attribute_cache[$attr_name] = 0;

        return 0;
    }

    private function addSeoUrlProduct(int $product_id, string $seo_url, string $name, string $external_id): void {
        $clean_url = parse_url($seo_url, PHP_URL_PATH);
        $clean_url = pathinfo($clean_url, PATHINFO_FILENAME);

        $q = $this->db->query("SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url`
        WHERE `keyword` = '" . $this->db->escape($clean_url) . "'
        AND `store_id` = 0");

        if ($q->num_rows) {
             throw new \Exception('SEO URL зайнятий');
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET
            `store_id` = 0,
            `language_id` = '2',
            `key` = 'product_id',
            `value` = '" . (int)$product_id . "',
            `keyword` = '" . $this->db->escape($clean_url) . "'
        ");
    }

    public function clearImportTables(): void {
        $tables = [
            'import_category',
            'import_manufacturer',
            'import_product',
            'import_image',
            'import_attribute'
        ];

        foreach ($tables as $tbl) {
            $this->db->query("TRUNCATE `" . DB_PREFIX . $tbl . "`");
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting`
        WHERE `code` = 'import_feed'
        AND `key` = 'import_feed_stats'");
    }
}
