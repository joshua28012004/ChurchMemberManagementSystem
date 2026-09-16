<?php

session_start();


/* =====================================================
   SECURITY HEADERS
===================================================== */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

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

include '../includes/db.php';


/* =====================================================
   CHECK MEMBER ID
===================================================== */

if (
    !isset($_GET['id']) ||
    !ctype_digit((string)$_GET['id']) ||
    (int)$_GET['id'] <= 0
) {

    header("Location: view_members.php");
    exit();

}

$id = (int)$_GET['id'];


/* =====================================================
   GET MEMBER PHOTO
===================================================== */

$stmt = mysqli_prepare(
    $conn,
    "SELECT photo
     FROM members
     WHERE id = ?
     LIMIT 1"
);


if (!$stmt) {

    header("Location: view_members.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);


if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    header("Location: view_members.php?msg=error");
    exit();

}


$result = mysqli_stmt_get_result($stmt);


if (!$result) {

    mysqli_stmt_close($stmt);

    header("Location: view_members.php?msg=error");
    exit();

}


/* =====================================================
   CHECK MEMBER EXISTS
===================================================== */

if (mysqli_num_rows($result) === 0) {

    mysqli_stmt_close($stmt);

    header("Location: view_members.php");
    exit();

}


$row = mysqli_fetch_assoc($result);

$photo = $row['photo'] ?? '';


mysqli_stmt_close($stmt);


/* =====================================================
   DELETE MEMBER
===================================================== */

$delete_stmt = mysqli_prepare(
    $conn,
    "DELETE FROM members
     WHERE id = ?"
);


if (!$delete_stmt) {

    header("Location: view_members.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $delete_stmt,
    "i",
    $id
);


if (
    !mysqli_stmt_execute($delete_stmt)
) {

    mysqli_stmt_close($delete_stmt);

    header("Location: view_members.php?msg=error");
    exit();

}


$deleted_rows =
    mysqli_stmt_affected_rows($delete_stmt);


mysqli_stmt_close($delete_stmt);


/* =====================================================
   CONFIRM MEMBER WAS DELETED
===================================================== */

if ($deleted_rows !== 1) {

    header("Location: view_members.php?msg=error");
    exit();

}


/* =====================================================
   DELETE MEMBER PHOTO
===================================================== */

if (!empty($photo)) {

    $safe_photo =
        basename($photo);


    $photo_path =
        __DIR__ .
        '/../uploads/' .
        $safe_photo;


    if (is_file($photo_path)) {

        @unlink($photo_path);

    }

}


/* =====================================================
   REDIRECT
===================================================== */

header(
    "Location: view_members.php?msg=deleted"
);

exit();

?>