<?php

/* =====================================================
   SESSION
===================================================== */

session_start();


/* =====================================================
   PREVENT BROWSER CACHE
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
   ADMIN LOGIN CHECK
===================================================== */

if (!isset($_SESSION['admin'])) {

    header("Location: ../login.php");
    exit();

}


/* =====================================================
   DATABASE
===================================================== */

include "../includes/db.php";


/* =====================================================
   CSRF TOKEN
===================================================== */

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    strlen($_SESSION['csrf_token']) !== 64
) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}


/* =====================================================
   REQUEST METHOD CHECK (MUST BE POST)
===================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: view_events.php");
    exit();
}


/* =====================================================
   CSRF VALIDATION
===================================================== */

$submitted_token = $_POST['csrf_token'] ?? "";

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($submitted_token) ||
    !hash_equals($_SESSION['csrf_token'], $submitted_token)
) {

    header("Location: view_events.php?msg=invalid");
    exit();

}


/* =====================================================
   CHECK EVENT ID
===================================================== */

if (
    !isset($_POST['id']) ||
    !is_string($_POST['id']) ||
    !ctype_digit($_POST['id'])
) {

    header("Location: view_events.php");
    exit();

}


$id = (int)$_POST['id'];


/* =====================================================
   ID MUST BE GREATER THAN ZERO
===================================================== */

if ($id <= 0) {

    header("Location: view_events.php");
    exit();

}


/* =====================================================
   CHECK WHETHER EVENT EXISTS
===================================================== */

$check_stmt = mysqli_prepare(
    $conn,
    "SELECT id FROM events WHERE id = ? LIMIT 1"
);


if (!$check_stmt) {

    header("Location: view_events.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $check_stmt,
    "i",
    $id
);


if (!mysqli_stmt_execute($check_stmt)) {

    mysqli_stmt_close($check_stmt);

    header("Location: view_events.php?msg=error");
    exit();

}


$check_result = mysqli_stmt_get_result($check_stmt);


if (!$check_result) {

    mysqli_stmt_close($check_stmt);

    header("Location: view_events.php?msg=error");
    exit();

}


if (mysqli_num_rows($check_result) === 0) {

    mysqli_stmt_close($check_stmt);

    header("Location: view_events.php?msg=notfound");
    exit();

}


mysqli_stmt_close($check_stmt);


/* =====================================================
   DELETE EVENT
===================================================== */

$delete_stmt = mysqli_prepare(
    $conn,
    "DELETE FROM events WHERE id = ?"
);


if (!$delete_stmt) {

    header("Location: view_events.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $delete_stmt,
    "i",
    $id
);


if (!mysqli_stmt_execute($delete_stmt)) {

    mysqli_stmt_close($delete_stmt);

    header("Location: view_events.php?msg=error");
    exit();

}


/* =====================================================
   VERIFY DELETE
===================================================== */

$affected_rows = mysqli_stmt_affected_rows(
    $delete_stmt
);


mysqli_stmt_close($delete_stmt);


/* =====================================================
   SUCCESS
===================================================== */

if ($affected_rows === 1) {

    /* -----------------------------------------
       REGENERATE CSRF TOKEN
    ----------------------------------------- */

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));


    header("Location: view_events.php?msg=deleted");
    exit();

}


/* =====================================================
   EVENT WAS NOT DELETED
===================================================== */

header("Location: view_events.php?msg=notfound");
exit();

?>