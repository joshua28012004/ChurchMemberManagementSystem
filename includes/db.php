<?php

$host = "localhost";
$user = "root";
$password = "";
$dbname = "church_management";

$conn = mysqli_connect($host, $user, $password, $dbname);

if (!$conn) {
    die("Unable to connect to the database.");
}

mysqli_set_charset($conn, "utf8mb4");

?>