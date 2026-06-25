<?php

class Config
{
    private static $settings = null;

    public static function get($key, $default = null)
    {
        if (self::$settings === null) {
            self::load();
        }
        return self::$settings[$key] ?? $default;
    }

    public static function getAll()
    {
        if (self::$settings === null) {
            self::load();
        }
        return self::$settings;
    }

    public static function set($key, $value)
    {
        $db = Database::getInstance();
        $existing = $db->fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            $db->update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            $db->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
        self::$settings[$key] = $value;
    }

    private static function load()
    {
        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
            self::$settings = [];
            foreach ($rows as $row) {
                self::$settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            self::$settings = [];
        }
    }

    public static function reload()
    {
        self::$settings = null;
    }
}
