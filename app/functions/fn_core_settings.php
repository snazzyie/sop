<?php
// Core Settings Functions

/**
 * Get all settings from database
 */
function fn_core_settings_get_all() {
    $query = "SELECT setting_key, setting_value, setting_type FROM settings";
    $rows = fn_core_database_rows($query);

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = fn_core_settings_cast_value($row['setting_value'], $row['setting_type']);
    }

    return $settings;
}

/**
 * Get single setting by key
 */
function fn_core_settings_get($key, $default = null) {
    $query = "SELECT setting_value, setting_type FROM settings WHERE setting_key = ?";
    $row = fn_core_database_row($query, [$key]);

    if (!$row) {
        return $default;
    }

    return fn_core_settings_cast_value($row['setting_value'], $row['setting_type']);
}

/**
 * Set/update setting value
 */
function fn_core_settings_set($key, $value, $type = 'string') {
    $existing = fn_core_settings_get($key);

    $valueStr = fn_core_settings_serialize_value($value, $type);

    if ($existing !== null) {
        $query = "UPDATE settings SET setting_value = ?, setting_type = ?, updated_at = NOW() WHERE setting_key = ?";
        fn_core_edit_row_no_redirect($query, [$valueStr, $type, $key]);
    } else {
        $query = "INSERT INTO settings (setting_key, setting_value, setting_type, created_at) VALUES (?, ?, ?, NOW())";
        fn_core_insert_row_no_redirect($query, [$key, $valueStr, $type]);
    }
}

/**
 * Delete setting
 */
function fn_core_settings_delete($key) {
    $query = "DELETE FROM settings WHERE setting_key = ?";
    fn_core_edit_row_no_redirect($query, [$key]);
}

/**
 * Cast setting value to appropriate type
 */
function fn_core_settings_cast_value($value, $type) {
    switch ($type) {
        case 'int':
        case 'integer':
            return (int)$value;

        case 'float':
        case 'double':
            return (float)$value;

        case 'bool':
        case 'boolean':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);

        case 'json':
            return json_decode($value, true);

        case 'array':
            return json_decode($value, true) ?: [];

        case 'string':
        default:
            return (string)$value;
    }
}

/**
 * Serialize value for storage
 */
function fn_core_settings_serialize_value($value, $type) {
    switch ($type) {
        case 'json':
        case 'array':
            return json_encode($value);

        case 'bool':
        case 'boolean':
            return $value ? '1' : '0';

        default:
            return (string)$value;
    }
}

/**
 * Get settings by prefix (e.g., all 'stripe_*' settings)
 */
function fn_core_settings_get_by_prefix($prefix) {
    $query = "SELECT setting_key, setting_value, setting_type FROM settings WHERE setting_key LIKE ?";
    $rows = fn_core_database_rows($query, [$prefix . '%']);

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = fn_core_settings_cast_value($row['setting_value'], $row['setting_type']);
    }

    return $settings;
}

/**
 * Initialize default settings if they don't exist
 */
function fn_core_settings_initialize_defaults() {
    $defaults = [
        'app_name' => ['value' => 'SOP Recorder', 'type' => 'string'],
        'app_version' => ['value' => '1.0.0', 'type' => 'string'],
        'max_sops_per_team' => ['value' => 100, 'type' => 'int'],
        'max_steps_per_sop' => ['value' => 50, 'type' => 'int'],
        'screenshot_max_size' => ['value' => 5242880, 'type' => 'int'], // 5MB
        'enable_oauth' => ['value' => true, 'type' => 'bool'],
        'enable_api' => ['value' => true, 'type' => 'bool'],
        'maintenance_mode' => ['value' => false, 'type' => 'bool'],
    ];

    foreach ($defaults as $key => $config) {
        $existing = fn_core_settings_get($key);
        if ($existing === null) {
            fn_core_settings_set($key, $config['value'], $config['type']);
        }
    }
}
