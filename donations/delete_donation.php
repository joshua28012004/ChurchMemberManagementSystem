<?php

session_start();

/* =====================================================
   SECURITY: PREVENT BROWSER CACHING
===================================================== */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


/* =====================================================
   SECURITY HEADERS
===================================================== */

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");


/* =====================================================
   SESSION SECURITY CHECK
===================================================== */

if (!isset($_SESSION['admin'])) {

    header("Location: ../login.php");
    exit();

}


include '../includes/db.php';


/* =====================================================
   CHECK DATABASE CONNECTION
===================================================== */

if (!$conn) {

    header("Location: view_donations.php?msg=error");
    exit();

}


/* =====================================================
   REQUEST METHOD CHECK (MUST BE POST)
===================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: view_donations.php");
    exit();
}


/* =====================================================
   CSRF TOKEN VALIDATION
===================================================== */

$submitted_token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($submitted_token) ||
    !hash_equals($_SESSION['csrf_token'], $submitted_token)
) {
    header("Location: view_donations.php?msg=invalid");
    exit();
}


/* =====================================================
   STRICT ID VALIDATION
===================================================== */

if (
    !isset($_POST['id']) ||
    !is_string($_POST['id']) ||
    !ctype_digit($_POST['id'])
) {

    header("Location: view_donations.php");
    exit();

}


$id = (int)$_POST['id'];


/* =====================================================
   ID MUST BE GREATER THAN ZERO
===================================================== */

if ($id <= 0) {

    header("Location: view_donations.php");
    exit();

}


/* =====================================================
   CHECK WHETHER DONATION EXISTS
===================================================== */

$check_stmt = mysqli_prepare(
    $conn,
    "SELECT id FROM donations WHERE id = ? LIMIT 1"
);

if (!$check_stmt) {

    header("Location: view_donations.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $check_stmt,
    "i",
    $id
);


if (!mysqli_stmt_execute($check_stmt)) {

    mysqli_stmt_close($check_stmt);

    header("Location: view_donations.php?msg=error");
    exit();

}


$check_result = mysqli_stmt_get_result($check_stmt);


if (!$check_result) {

    mysqli_stmt_close($check_stmt);

    header("Location: view_donations.php?msg=error");
    exit();

}


if (mysqli_num_rows($check_result) === 0) {

    mysqli_stmt_close($check_stmt);

    header("Location: view_donations.php?msg=notfound");
    exit();

}


mysqli_stmt_close($check_stmt);


/* =====================================================
   DELETE DONATION
===================================================== */

$delete_stmt = mysqli_prepare(
    $conn,
    "DELETE FROM donations WHERE id = ?"
);

if (!$delete_stmt) {

    header("Location: view_donations.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $delete_stmt,
    "i",
    $id
);


if (!mysqli_stmt_execute($delete_stmt)) {

    mysqli_stmt_close($delete_stmt);

    header("Location: view_donations.php?msg=error");
    exit();

}


/* =====================================================
   VERIFY EXACTLY ONE DONATION WAS DELETED
===================================================== */

$affected_rows = mysqli_stmt_affected_rows($delete_stmt);

mysqli_stmt_close($delete_stmt);


if ($affected_rows === 1) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header("Location: view_donations.php?msg=deleted");
    exit();

}


/* =====================================================
   DELETE DID NOT AFFECT A DONATION
===================================================== */

header("Location: view_donations.php?msg=notfound");
exit();

?>