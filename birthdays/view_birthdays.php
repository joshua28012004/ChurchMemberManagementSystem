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
   SESSION SECURITY CHECK
===================================================== */

if (!isset($_SESSION['admin'])) {

    header("Location: ../login.php");
    exit();

}


include "../includes/db.php";


/* =====================================================
   TODAY
===================================================== */

$today = date("m-d");


/* =====================================================
   TODAY'S BIRTHDAYS
===================================================== */

$today_sql = "SELECT * FROM members
              WHERE DATE_FORMAT(dob, '%m-%d') = '$today'
              ORDER BY fullname ASC";

$today_result = mysqli_query($conn, $today_sql);

if (!$today_result) {

    die("Today's Birthday Error: " . mysqli_error($conn));

}


/* =====================================================
   UPCOMING BIRTHDAYS
===================================================== */

$upcoming_sql = "SELECT *,
                 DATE_FORMAT(dob, '%m-%d') AS birthday
                 FROM members
                 WHERE DATE_FORMAT(dob, '%m-%d') != '$today'
                 ORDER BY
                 CASE
                    WHEN DATE_FORMAT(dob, '%m-%d') >= '$today'
                    THEN DATE_FORMAT(dob, '%m-%d')
                    ELSE CONCAT('99-', DATE_FORMAT(dob, '%d'))
                 END
                 LIMIT 10";

$upcoming_result = mysqli_query($conn, $upcoming_sql);

if (!$upcoming_result) {

    die("Upcoming Birthday Error: " . mysqli_error($conn));

}

?>

<!DOCTYPE html>

<html>

<head>

<title>Birthdays</title>


<!-- =====================================================
     BOOTSTRAP
===================================================== -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<style>

/* =====================================================
   BODY
===================================================== */

body {

    background-color: #f5f5f5;

}


/* =====================================================
   BIRTHDAY CARD
===================================================== */

.birthday-card {

    border-radius: 10px;

}


/* =====================================================
   MEMBER PHOTO
===================================================== */

.member-photo {

    width: 70px;

    height: 70px;

    object-fit: cover;

    border-radius: 50%;

}

</style>

</head>


<body>


<?php

include "../includes/header.php";

include "../includes/sidebar.php";

?>


<div class="content">


<div class="container mt-4">


<h2 class="mb-4">

🎂 Birthday Reminders

</h2>


<!-- =====================================================
     TODAY'S BIRTHDAYS
===================================================== -->

<div class="card birthday-card mb-4">


<div class="card-header bg-warning">

<h4 class="mb-0">

🎉 Today's Birthdays

</h4>

</div>


<div class="card-body">


<?php

if (mysqli_num_rows($today_result) > 0) {

    while ($member = mysqli_fetch_assoc($today_result)) {

?>


<div class="d-flex align-items-center mb-3">


<?php

if (
    !empty($member['photo']) &&
    file_exists("../uploads/" . $member['photo'])
) {

?>


<img

src="../uploads/<?php
echo htmlspecialchars($member['photo']);
?>"

class="member-photo me-3"

>


<?php

} else {

?>


<div class="member-photo bg-secondary text-white
            d-flex align-items-center
            justify-content-center me-3">

👤

</div>


<?php

}

?>


<div>


<h5 class="mb-1">

<?php

echo htmlspecialchars(
    $member['fullname']
);

?>

</h5>


<p class="mb-0">

Member ID:

<?php

echo htmlspecialchars(
    $member['member_id']
);

?>

</p>


</div>


</div>


<?php

    }

} else {

?>


<div class="alert alert-info mb-0">

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


<table class="table table-bordered table-hover">


<thead class="table-dark">


<tr>

<th>ID</th>

<th>Photo</th>

<th>Member ID</th>

<th>Name</th>

<th>Date of Birth</th>

</tr>


</thead>


<tbody>


<?php

if (mysqli_num_rows($upcoming_result) > 0) {

    while ($member =
           mysqli_fetch_assoc($upcoming_result)) {

?>


<tr>


<td>

<?php

echo htmlspecialchars(
    $member['id']
);

?>

</td>


<td>


<?php

if (
    !empty($member['photo']) &&
    file_exists("../uploads/" . $member['photo'])
) {

?>


<img

src="../uploads/<?php
echo htmlspecialchars($member['photo']);
?>"

class="member-photo"

>


<?php

} else {

?>


<span>

👤

</span>


<?php

}

?>


</td>


<td>

<?php

echo htmlspecialchars(
    $member['member_id']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $member['fullname']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $member['dob']
);

?>

</td>


</tr>


<?php

    }

} else {

?>


<tr>


<td
colspan="5"
class="text-center"
>

No upcoming birthdays found.

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


<!-- =====================================================
     BOOTSTRAP JS
===================================================== -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<!-- =====================================================
     BACK BUTTON / BFCache PROTECTION
===================================================== -->

<script>

window.addEventListener("pageshow", function(event) {

    /*
     * If the page was restored from the browser's
     * Back/Forward cache, reload it.
     */

    if (event.persisted) {

        window.location.reload();

    }

});

</script>


</body>

</html>