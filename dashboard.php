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

    header("Location: login.php");

    exit();

}


include 'includes/db.php';


/* =====================================================
   TOTAL MEMBERS
===================================================== */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total FROM members"
);

if (!$stmt) {

    die("Unable to load dashboard data.");

}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (!$result) {

    mysqli_stmt_close($stmt);

    die("Unable to load dashboard data.");

}

$totalMembers = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =====================================================
   TOTAL DONATIONS
===================================================== */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM donations"
);

if (!$stmt) {

    die("Unable to load dashboard data.");

}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (!$result) {

    mysqli_stmt_close($stmt);

    die("Unable to load dashboard data.");

}

$totalDonations = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =====================================================
   TODAY'S BIRTHDAYS
===================================================== */

$today = date("m-d");


$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM members
     WHERE DATE_FORMAT(dob, '%m-%d') = ?"
);

if (!$stmt) {

    die("Unable to load dashboard data.");

}

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $today
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (!$result) {

    mysqli_stmt_close($stmt);

    die("Unable to load dashboard data.");

}

$todayBirthdays = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =====================================================
   UPCOMING EVENTS COUNT
===================================================== */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM events
     WHERE event_date >= CURDATE()"
);

if (!$stmt) {

    die("Unable to load dashboard data.");

}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (!$result) {

    mysqli_stmt_close($stmt);

    die("Unable to load dashboard data.");

}

$upcomingEvents = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =====================================================
   UPCOMING EVENTS LIST
===================================================== */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        event_name,
        event_type,
        event_date,
        event_time,
        venue
     FROM events
     WHERE event_date >= CURDATE()
     ORDER BY event_date ASC, event_time ASC
     LIMIT 5"
);

if (!$stmt) {

    die("Unable to load dashboard data.");

}

mysqli_stmt_execute($stmt);

$upcoming_events = mysqli_stmt_get_result($stmt);

if (!$upcoming_events) {

    mysqli_stmt_close($stmt);

    die("Unable to load dashboard data.");

}


include 'includes/header.php';

include 'includes/sidebar.php';

?>


<div class="content">


<!-- ===============================
     WELCOME MESSAGE
=============================== -->

<h2>

Welcome,

<?php

echo htmlspecialchars(
    (string)($_SESSION['admin'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

?>

</h2>


<hr>


<!-- ===============================
     DASHBOARD CARDS
=============================== -->

<div class="row g-3">


<!-- ===============================
     TOTAL MEMBERS
=============================== -->

<div class="col-md-3">

<a
href="members/view_members.php"
class="text-decoration-none">

<div class="card bg-primary text-white p-3 h-100">

<h2>

<?php

echo (int)($totalMembers['total'] ?? 0);

?>

</h2>

<p class="mb-0">

Total Members

</p>

<small>

Click to view members

</small>

</div>

</a>

</div>


<!-- ===============================
     TOTAL DONATIONS
=============================== -->

<div class="col-md-3">

<div class="card bg-success text-white p-3 h-100">

<h2>

₹<?php

echo number_format(
    (float)($totalDonations['total'] ?? 0),
    2
);

?>

</h2>

<p class="mb-0">

Total Donations

</p>

</div>

</div>


<!-- ===============================
     TODAY'S BIRTHDAYS
=============================== -->

<div class="col-md-3">

<a
href="members/birthday_reminders.php"
class="text-decoration-none">

<div class="card bg-warning text-dark p-3 h-100">

<h2>

<?php

echo (int)($todayBirthdays['total'] ?? 0);

?>

</h2>

<p class="mb-0">

Today's Birthdays 🎂

</p>

<small>

Click to view birthday reminders

</small>

</div>

</a>

</div>


<!-- ===============================
     UPCOMING EVENTS
=============================== -->

<div class="col-md-3">

<div class="card bg-danger text-white p-3 h-100">

<h2>

<?php

echo (int)($upcomingEvents['total'] ?? 0);

?>

</h2>

<p class="mb-0">

Upcoming Events

</p>

</div>

</div>


</div>


<br>


<!-- ===============================
     CHURCH ANNOUNCEMENTS
=============================== -->

<div class="card p-4">

<h3>

Church Announcements

</h3>


<hr>


<p>

Welcome to the Church Member Management System.

This dashboard helps administrators manage members,
donations, birthdays, and church events efficiently.

</p>

</div>


<!-- ===============================
     QUICK ACTIONS
=============================== -->

<div class="card shadow mt-4">


<div class="card-header bg-dark text-white">

<h4 class="mb-0">

⚡ Quick Actions

</h4>

</div>


<div class="card-body">


<div class="row g-3">


<!-- ===============================
     ADD MEMBER
=============================== -->

<div class="col-md-3">

<a
href="members/add_member.php"
class="btn btn-primary w-100 p-3">

👤

<br>

<strong>

Add Member

</strong>

</a>

</div>


<!-- ===============================
     ADD DONATION
=============================== -->

<div class="col-md-3">

<a
href="donations/add_donation.php"
class="btn btn-success w-100 p-3">

💰

<br>

<strong>

Add Donation

</strong>

</a>

</div>


<!-- ===============================
     ADD EVENT
=============================== -->

<div class="col-md-3">

<a
href="events/add_event.php"
class="btn btn-warning w-100 p-3">

📅

<br>

<strong>

Add Event

</strong>

</a>

</div>


<!-- ===============================
     BIRTHDAY REMINDERS
=============================== -->

<div class="col-md-3">

<a
href="members/birthday_reminders.php"
class="btn btn-danger w-100 p-3">

🎂

<br>

<strong>

Birthday Reminders

</strong>

</a>

</div>


<!-- ===============================
     VIEW MEMBERS
=============================== -->

<div class="col-md-3">

<a
href="members/view_members.php"
class="btn btn-secondary w-100 p-3">

👥

<br>

<strong>

View Members

</strong>

</a>

</div>


<!-- ===============================
     VIEW REPORTS
=============================== -->

<div class="col-md-3">

<a
href="reports/reports.php"
class="btn btn-info w-100 p-3">

📊

<br>

<strong>

View Reports

</strong>

</a>

</div>


</div>

</div>

</div>


<!-- ===============================
     UPCOMING EVENTS
=============================== -->

<div class="card shadow mt-4">


<div class="card-header bg-primary text-white">

<h4 class="mb-0">

📅 Upcoming Events

</h4>

</div>


<div class="card-body">


<?php

if (mysqli_num_rows($upcoming_events) > 0) {

?>


<div class="table-responsive">


<table class="table table-bordered table-hover">


<thead class="table-dark">


<tr>

<th>Event</th>

<th>Type</th>

<th>Date</th>

<th>Time</th>

<th>Venue</th>

</tr>


</thead>


<tbody>


<?php

while (
    $event = mysqli_fetch_assoc(
        $upcoming_events
    )
) {

?>


<tr>


<!-- EVENT -->

<td>

<?php

echo htmlspecialchars(
    (string)($event['event_name'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- TYPE -->

<td>

<?php

echo htmlspecialchars(
    (string)($event['event_type'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- DATE -->

<td>

<?php

if (!empty($event['event_date'])) {

    $event_date = strtotime(
        $event['event_date']
    );

    if ($event_date !== false) {

        echo htmlspecialchars(
            date("d-m-Y", $event_date),
            ENT_QUOTES,
            'UTF-8'
        );

    } else {

        echo "Not specified";

    }

} else {

    echo "Not specified";

}

?>

</td>


<!-- TIME -->

<td>

<?php

if (!empty($event['event_time'])) {

    $event_time = strtotime(
        $event['event_time']
    );

    if ($event_time !== false) {

        echo htmlspecialchars(
            date("h:i A", $event_time),
            ENT_QUOTES,
            'UTF-8'
        );

    } else {

        echo "Not specified";

    }

} else {

    echo "Not specified";

}

?>

</td>


<!-- VENUE -->

<td>

<?php

echo htmlspecialchars(
    (string)($event['venue'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


</tr>


<?php

}

?>


</tbody>

</table>

</div>


<?php

} else {

?>


<p class="text-center text-muted">

No upcoming events.

</p>


<?php

}

?>


</div>

</div>


</div>


<!-- ===============================
     CLOSE EVENTS STATEMENT
=============================== -->

<?php

mysqli_stmt_close($stmt);

?>


<!-- ===============================
     BOOTSTRAP
=============================== -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<!-- =====================================================
     BACK BUTTON / BROWSER BFCache PROTECTION
===================================================== -->

<script>

window.addEventListener("pageshow", function(event) {

    if (event.persisted) {

        window.location.reload();

    }

});

</script>


</body>

</html>