<?php

$config_file = __DIR__ . "/db_config.php";

if (!is_file($config_file)) {
    die("Database configuration is missing.");
}

require_once $config_file;

if (
    !isset($db_host, $db_user, $db_password, $db_name) ||
    !is_string($db_host) ||
    !is_string($db_user) ||
    !is_string($db_password) ||
    !is_string($db_name)
) {
    die("Invalid database configuration.");
}

$conn = mysqli_connect(
    $db_host,
    $db_user,
    $db_password,
    $db_name
);

if (!$conn) {
    die("Unable to connect to the database.");
}

mysqli_set_charset($conn, "utf8mb4");

?>