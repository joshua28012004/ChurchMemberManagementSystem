<?php

session_start();


/* =====================================================
   SECURITY / CACHE HEADERS
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
   CURRENT DATE
===================================================== */

$today = date("m-d");


/* =====================================================
   GET TODAY'S BIRTHDAYS
===================================================== */

$today_stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM members
     WHERE dob IS NOT NULL
       AND dob != ''
       AND DATE_FORMAT(dob, '%m-%d') = ?
     ORDER BY fullname ASC"
);


if (!$today_stmt) {

    header("Location: birthday_reminders.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $today_stmt,
    "s",
    $today
);


if (!mysqli_stmt_execute($today_stmt)) {

    mysqli_stmt_close($today_stmt);

    header("Location: birthday_reminders.php?msg=error");
    exit();

}


$today_result = mysqli_stmt_get_result($today_stmt);


if (!$today_result) {

    mysqli_stmt_close($today_stmt);

    header("Location: birthday_reminders.php?msg=error");
    exit();

}


/* =====================================================
   GET UPCOMING BIRTHDAYS
===================================================== */

$upcoming_stmt = mysqli_prepare(
    $conn,
    "SELECT *,
            DATE_FORMAT(dob, '%m-%d') AS birthday_day
     FROM members
     WHERE dob IS NOT NULL
       AND dob != ''
     ORDER BY
         CASE
             WHEN DATE_FORMAT(dob, '%m-%d') >= ?
             THEN DATE_FORMAT(dob, '%m-%d')
             ELSE '99-99'
         END ASC,
         fullname ASC"
);


if (!$upcoming_stmt) {

    mysqli_stmt_close($today_stmt);

    header("Location: birthday_reminders.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $upcoming_stmt,
    "s",
    $today
);


if (!mysqli_stmt_execute($upcoming_stmt)) {

    mysqli_stmt_close($today_stmt);
    mysqli_stmt_close($upcoming_stmt);

    header("Location: birthday_reminders.php?msg=error");
    exit();

}


$upcoming_result =
    mysqli_stmt_get_result($upcoming_stmt);


if (!$upcoming_result) {

    mysqli_stmt_close($today_stmt);
    mysqli_stmt_close($upcoming_stmt);

    header("Location: birthday_reminders.php?msg=error");
    exit();

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Birthday Reminders</title>


<!-- Bootstrap -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- Bootstrap Icons -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">


<style>

/* =====================================================
   BODY
===================================================== */

body {

    background: #f5f6fa;

}


/* =====================================================
   MAIN CARD
===================================================== */

.main-card {

    border: none;

    border-radius: 15px;

    box-shadow:
        0 5px 20px rgba(0,0,0,0.10);

}


/* =====================================================
   HEADER
===================================================== */

.page-header {

    background: linear-gradient(
        135deg,
        #ff4b91,
        #ff8a5c
    );

    color: white;

    padding: 25px;

    border-radius: 15px 15px 0 0;

}


.page-header h2 {

    font-weight: 700;

}


/* =====================================================
   BIRTHDAY CARD
===================================================== */

.birthday-card {

    border: none;

    border-radius: 12px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.08);

}


/* =====================================================
   TODAY BIRTHDAY
===================================================== */

.today-birthday {

    background: linear-gradient(
        135deg,
        #fff0f6,
        #ffe3ed
    );

    border-left: 5px solid #ff4b91;

}


/* =====================================================
   MEMBER PHOTO
===================================================== */

.member-photo {

    width: 55px;

    height: 55px;

    border-radius: 50%;

    object-fit: cover;

}


/* =====================================================
   TABLE
===================================================== */

.table {

    vertical-align: middle;

}


.table th {

    white-space: nowrap;

}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width:768px) {

    .container-fluid {

        padding-left: 10px !important;

        padding-right: 10px !important;

    }

}

</style>

</head>


<body>


<div class="container-fluid mt-4 px-4">


<!-- =====================================================
     MAIN CARD
===================================================== -->

<div class="card main-card">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

<div class="d-flex
justify-content-between
align-items-center
flex-wrap">


<div>

<h2 class="mb-1">

🎂 Birthday Reminders

</h2>

<p class="mb-0">

View today's and upcoming member birthdays

</p>

</div>


<div class="mt-2 mt-md-0">

<a
href="view_members.php"
class="btn btn-light">

<i class="bi bi-people-fill"></i>

View Members

</a>


<a
href="../dashboard.php"
class="btn btn-dark">

<i class="bi bi-speedometer2"></i>

Dashboard

</a>

</div>


</div>

</div>


<!-- =====================================================
     BODY
===================================================== -->

<div class="card-body p-4">


<!-- =====================================================
     ERROR MESSAGE
===================================================== -->

<?php

if (
    isset($_GET['msg']) &&
    $_GET['msg'] === "error"
) {

?>

<div class="alert alert-danger alert-dismissible fade show"
     role="alert">

<i class="bi bi-exclamation-triangle-fill"></i>

Something went wrong while loading birthday information.
Please try again.

<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>

</div>

<?php

}

?>


<!-- =====================================================
     TODAY'S BIRTHDAYS
===================================================== -->

<div class="card birthday-card today-birthday mb-4">


<div class="card-body">


<h4 class="mb-3">

🎉 Today's Birthdays

</h4>


<?php

if (mysqli_num_rows($today_result) > 0) {

    while ($row = mysqli_fetch_assoc($today_result)) {

?>


<div class="d-flex
align-items-center
mb-3">


<!-- PHOTO -->

<div class="me-3">

<?php

$photo = $row['photo'] ?? '';

if (!empty($photo)) {

    /*
     * basename() prevents directory traversal
     * through a manipulated database filename.
     */

    $safe_photo = basename($photo);


    $photo_path =
        __DIR__ .
        '/../uploads/' .
        $safe_photo;


    if (is_file($photo_path)) {

?>

<img
src="../uploads/<?php
echo htmlspecialchars(
    $safe_photo,
    ENT_QUOTES,
    'UTF-8'
);
?>"
class="member-photo"
alt="Member Photo">

<?php

    } else {

?>

<i
class="bi bi-person-circle text-secondary"
style="font-size:55px;">
</i>

<?php

    }

} else {

?>

<i
class="bi bi-person-circle text-secondary"
style="font-size:55px;">
</i>

<?php

}

?>

</div>


<!-- DETAILS -->

<div>


<h5 class="mb-1">

<?php

echo htmlspecialchars(
    $row['fullname'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

?>

</h5>


<div class="text-muted">

Member ID:

<strong>

<?php

echo htmlspecialchars(
    $row['member_id'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>

</div>


<?php

if (!empty($row['phone'])) {

?>

<div>

📞

<?php

echo htmlspecialchars(
    $row['phone'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>

<?php

}

?>

</div>


</div>


<?php

    }

} else {

?>


<div class="text-muted">

🎂 No birthdays today.

</div>


<?php

}

?>


</div>

</div>


<!-- =====================================================
     UPCOMING BIRTHDAYS
===================================================== -->

<div class="card birthday-card">


<div class="card-header bg-primary text-white">

<h4 class="mb-0">

📅 Upcoming Birthdays

</h4>

</div>


<div class="card-body">


<div class="table-responsive">


<table class="table table-bordered table-striped table-hover">


<thead class="table-dark">

<tr>

<th>#</th>

<th>Photo</th>

<th>Member ID</th>

<th>Name</th>

<th>Date of Birth</th>

<th>Birthday</th>

<th>Phone</th>

</tr>

</thead>


<tbody>


<?php

$count = 1;

$found = false;


while (
    $row = mysqli_fetch_assoc($upcoming_result)
) {


    /* =================================================
       SKIP BIRTHDAYS THAT ALREADY PASSED
    ================================================= */

    if (
        date("m-d", strtotime($row['dob']))
        < $today
    ) {

        continue;

    }


    $found = true;

?>


<tr>


<!-- NUMBER -->

<td>

<?php

echo $count++;

?>

</td>


<!-- PHOTO -->

<td>

<?php

$photo = $row['photo'] ?? '';

if (!empty($photo)) {

    /*
     * basename() prevents directory traversal
     * through a manipulated database filename.
     */

    $safe_photo = basename($photo);


    $photo_path =
        __DIR__ .
        '/../uploads/' .
        $safe_photo;


    if (is_file($photo_path)) {

?>

<img
src="../uploads/<?php
echo htmlspecialchars(
    $safe_photo,
    ENT_QUOTES,
    'UTF-8'
);
?>"
class="member-photo"
alt="Member Photo">

<?php

    } else {

?>

<i
class="bi bi-person-circle text-secondary"
style="font-size:45px;">
</i>

<?php

    }

} else {

?>

<i
class="bi bi-person-circle text-secondary"
style="font-size:45px;">
</i>

<?php

}

?>

</td>


<!-- MEMBER ID -->

<td>

<strong>

<?php

echo htmlspecialchars(
    $row['member_id'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>

</td>


<!-- NAME -->

<td>

<?php

echo htmlspecialchars(
    $row['fullname'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- DOB -->

<td>

<?php

$dob_timestamp =
    strtotime($row['dob']);

if ($dob_timestamp !== false) {

    echo htmlspecialchars(
        date("d-m-Y", $dob_timestamp),
        ENT_QUOTES,
        'UTF-8'
    );

} else {

    echo "Not specified";

}

?>

</td>


<!-- BIRTHDAY -->

<td>

<span class="badge bg-warning text-dark">

🎂

<?php

if ($dob_timestamp !== false) {

    echo htmlspecialchars(
        date("d M", $dob_timestamp),
        ENT_QUOTES,
        'UTF-8'
    );

} else {

    echo "N/A";

}

?>

</span>

</td>


<!-- PHONE -->

<td>

<?php

if (!empty($row['phone'])) {

    echo htmlspecialchars(
        $row['phone'],
        ENT_QUOTES,
        'UTF-8'
    );

} else {

    echo "Not specified";

}

?>

</td>


</tr>


<?php

}


if (!$found) {

?>


<tr>

<td
colspan="7"
class="text-center text-muted py-4">

🎂 No upcoming birthdays.

</td>

</tr>


<?php

}

?>


</tbody>

</table>

</div>


</div>

</div>


</div>

</div>

</div>


<!-- =====================================================
     BOOTSTRAP JS
===================================================== -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<!-- =====================================================
     BF CACHE PROTECTION
===================================================== -->

<script>

window.addEventListener(
    "pageshow",
    function(event) {

        if (event.persisted) {

            window.location.reload();

        }

    }
);

</script>


</body>

</html>


<?php

/* =====================================================
   CLOSE PREPARED STATEMENTS
===================================================== */

mysqli_stmt_close($today_stmt);

mysqli_stmt_close($upcoming_stmt);

?>