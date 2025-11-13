<?php
// Core Database Functions

/**
 * Get PDO database connection
 */
function fn_core_database_connection() {
    $config = require BASE_PATH . 'config.php';
    $username = $config['database']['username'];
    $password = $config['database']['password'];
    $dsn = 'mysql:' . http_build_query($config['database'], '', ';');

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Fetch multiple rows
 */
function fn_core_database_rows($query, $params = []) {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Fetch single row
 */
function fn_core_database_row($query, $params = []) {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Insert row with redirect
 */
function fn_core_insert_row($query, $params = [], $return = '/') {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    header("Location: $return");
    exit;
}

/**
 * Insert row without redirect, return last insert ID
 */
function fn_core_insert_row_no_redirect($query, $params = []) {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

/**
 * Update row with redirect
 */
function fn_core_edit_row($query, $params = [], $return = '/') {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    header("Location: $return");
    exit;
}

/**
 * Update row without redirect
 */
function fn_core_edit_row_no_redirect($query, $params = []) {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Delete row with redirect
 */
function fn_core_delete_row($query, $params = [], $return = '/') {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    header("Location: $return");
    exit;
}

/**
 * Count all rows in table
 */
function fn_core_count_rows($table) {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Count rows filtered by company_id
 */
function fn_core_count_rows_company($table, $company_id) {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Count rows filtered by user_id
 */
function fn_core_count_rows_user($table, $user_id) {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Execute raw SQL (for migrations, etc.)
 */
function fn_core_execute_sql($query) {
    $pdo = fn_core_database_connection();
    return $pdo->exec($query);
}

/**
 * Get table schema
 */
function fn_core_get_table_columns($table) {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->query("SHOW COLUMNS FROM $table");
    return $stmt->fetchAll();
}

/**
 * Check if record exists
 */
function fn_core_record_exists($table, $field, $value) {
    $pdo = fn_core_database_connection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE $field = ?");
    $stmt->execute([$value]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}
