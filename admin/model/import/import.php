<?php
namespace Opencart\Admin\Model\Import;

class Import extends \Opencart\System\Engine\Model {
    public function __construct($registry) {
        parent::__construct($registry);
    }

    public function saveSettings(array $data): void {
        // Видаляємо попередні налаштування, які зберігаються під ключем 'import_settings'
        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'import_settings'");

        // Записуємо кожне значення з $data в таблицю setting
        foreach ($data as $key => $value) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET 
            `store_id` = '0',
            `code` = 'import_settings',
            `key` = '" . $this->db->escape($key) . "', 
            `value` = '" . $this->db->escape($value) . "'");
        }
    }

    public function getSettings(): array {
        $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `code` = 'import_settings'");
        $settings = [];
        foreach ($result->rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return $settings;
    }

}
