<?php
require_once 'config.php';

function get_setting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT $key FROM settings LIMIT 1");
    $stmt->execute();
    return $stmt->fetchColumn();
}

function get_all($table, $where = null, $order = "id DESC") {
    global $pdo;
    $query = "SELECT * FROM $table";
    if ($where) $query .= " WHERE $where";
    $query .= " ORDER BY $order";
    return $pdo->query($query)->fetchAll();
}
?>