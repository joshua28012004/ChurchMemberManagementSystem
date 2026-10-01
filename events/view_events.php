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

include '../includes/db.php';


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

$csrf_token = $_SESSION['csrf_token'];


/* =====================================================
   HEADER + SIDEBAR
===================================================== */

include '../includes/header.php';
include '../includes/sidebar.php';


/* =====================================================
   SUCCESS / ERROR MESSAGE
===================================================== */

$message = "";
$message_type = "success";

if (isset($_GET['msg'])) {

    if ($_GET['msg'] === "deleted") {

        $message = "Event deleted successfully.";
        $message_type = "success";

    } elseif ($_GET['msg'] === "updated") {

        $message = "Event updated successfully.";
        $message_type = "success";

    } elseif ($_GET['msg'] === "added") {

        $message = "Event added successfully.";
        $message_type = "success";

    } elseif ($_GET['msg'] === "invalid") {

        $message = "Invalid security token. Please try again.";
        $message_type = "danger";

    } elseif ($_GET['msg'] === "error") {

        $message = "Unable to process the event request. Please try again.";
        $message_type = "danger";

    } elseif ($_GET['msg'] === "notfound") {

        $message = "Event not found.";
        $message_type = "warning";

    }

}


/* =====================================================
   SEARCH
===================================================== */

$search = "";

if (isset($_GET['search']) && is_string($_GET['search'])) {

    $search = trim($_GET['search']);

}


/* =====================================================
   GET EVENTS
===================================================== */

if ($search !== "") {

    $search_value = "%" . $search . "%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM events
         WHERE event_id LIKE ?
            OR event_name LIKE ?
            OR event_type LIKE ?
            OR venue LIKE ?
            OR organizer LIKE ?
         ORDER BY event_date ASC, event_time ASC"
    );

    if (!$stmt) {

        $result = false;

    } else {

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $search_value,
            $search_value,
            $search_value,
            $search_value,
            $search_value
        );

        if (!mysqli_stmt_execute($stmt)) {

            $result = false;

        } else {

            $result = mysqli_stmt_get_result($stmt);

        }

    }

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM events
         ORDER BY event_date ASC, event_time ASC"
    );

    if (!$stmt) {

        $result = false;

    } else {

        if (!mysqli_stmt_execute($stmt)) {

            $result = false;

        } else {

            $result = mysqli_stmt_get_result($stmt);

        }

    }

}


/* =====================================================
   DATABASE ERROR CHECK
===================================================== */

if (!$result) {

    $message = "Unable to load events. Please try again.";
    $message_type = "danger";

}

?>


<style>

/* =====================================================
   EVENTS PAGE
===================================================== */

.events-page {

    padding: 30px 15px 50px;

}


/* =====================================================
   EVENT CARD
===================================================== */

.events-card {

    border: none;

    border-radius: 15px;

    overflow: hidden;

    box-shadow:
        0 8px 25px rgba(0,0,0,0.10);

}


/* =====================================================
   CARD HEADER
===================================================== */

.events-header {

    background: linear-gradient(
        135deg,
        #198754,
        #20c997
    );

    color: white;

    padding: 20px 25px;

}


.events-header h3 {

    margin: 0;

    font-weight: 700;

}


/* =====================================================
   SEARCH BOX
===================================================== */

.search-box {

    background: #f8f9fa;

    padding: 18px;

    border-radius: 10px;

    margin-bottom: 20px;

}


/* =====================================================
   TABLE
===================================================== */

.events-table th {

    white-space: nowrap;

    vertical-align: middle;

}


.events-table td {

    vertical-align: middle;

}


.events-table tbody tr:hover {

    background-color: #f8f9fa;

}


/* =====================================================
   ACTION BUTTONS
===================================================== */

.action-btn {

    margin: 2px;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:768px) {

    .events-page {

        padding: 20px 10px 40px;

    }

    .events-header h3 {

        font-size: 22px;

    }

}

</style>


<!-- =====================================================
     PAGE
===================================================== -->

<div class="content">

<div class="events-page">

<div class="card events-card">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="events-header">

<h3>

📅 Church Events

</h3>

</div>


<div class="card-body">


<!-- =====================================================
     SUCCESS / ERROR MESSAGE
===================================================== -->

<?php if ($message != "") { ?>

<div
class="alert alert-<?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show"
role="alert">

<?php

if ($message_type === "success") {

    echo "✅";

} elseif ($message_type === "danger") {

    echo "⚠️";

} elseif ($message_type === "warning") {

    echo "⚠️";

}

?>

<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>

<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>

</div>

<?php } ?>


<!-- =====================================================
     SEARCH
===================================================== -->

<div class="search-box">

<form method="GET">

<div class="row g-2">


<!-- SEARCH INPUT -->

<div class="col-md-7">

<input
type="text"
name="search"
class="form-control"
placeholder="🔍 Search Event ID, Name, Type, Venue or Organizer"
value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">

</div>


<!-- SEARCH BUTTON -->

<div class="col-md-2">

<button
type="submit"
class="btn btn-primary w-100">

🔍 Search

</button>

</div>


<!-- CLEAR BUTTON -->

<div class="col-md-1">

<a
href="view_events.php"
class="btn btn-secondary w-100">

✖

</a>

</div>


<!-- ADD EVENT -->

<div class="col-md-2">

<a
href="add_event.php"
class="btn btn-success w-100">

➕ Add Event

</a>

</div>


</div>

</form>

</div>


<!-- =====================================================
     EVENTS TABLE
===================================================== -->

<div class="table-responsive">

<table
class="table table-bordered table-striped table-hover events-table">


<thead class="table-dark">

<tr>

<th>#</th>

<th>Event ID</th>

<th>Event Name</th>

<th>Type</th>

<th>Date</th>

<th>Time</th>

<th>Venue</th>

<th>Organizer</th>

<th>Actions</th>

</tr>

</thead>


<tbody>


<?php

$count = 1;


if ($result && mysqli_num_rows($result) > 0) {


    while ($row = mysqli_fetch_assoc($result)) {

?>


<tr>


<!-- =================================================
     SERIAL NUMBER
===================================================== -->

<td>

<?php echo $count++; ?>

</td>


<!-- =================================================
     EVENT ID
===================================================== -->

<td>

<strong>

<?php

echo htmlspecialchars(
    $row['event_id'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>

</td>


<!-- =================================================
     EVENT NAME
===================================================== -->

<td>

<?php

echo htmlspecialchars(
    $row['event_name'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- =================================================
     EVENT TYPE
===================================================== -->

<td>

<span class="badge bg-info text-dark">

<?php

echo htmlspecialchars(
    $row['event_type'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</span>

</td>


<!-- =================================================
     DATE
===================================================== -->

<td>

<?php

if (!empty($row['event_date'])) {

    $event_timestamp = strtotime($row['event_date']);

    if ($event_timestamp !== false) {

        echo date(
            "d-m-Y",
            $event_timestamp
        );

    } else {

        echo "Not specified";

    }

} else {

    echo "Not specified";

}

?>

</td>


<!-- =================================================
     TIME
===================================================== -->

<td>

<?php

if (!empty($row['event_time'])) {

    $event_time_timestamp = strtotime($row['event_time']);

    if ($event_time_timestamp !== false) {

        echo date(
            "h:i A",
            $event_time_timestamp
        );

    } else {

        echo "Not specified";

    }

} else {

    echo "Not specified";

}

?>

</td>


<!-- =================================================
     VENUE
===================================================== -->

<td>

<?php

if (!empty($row['venue'])) {

    echo htmlspecialchars(
        $row['venue'],
        ENT_QUOTES,
        'UTF-8'
    );

} else {

    echo "Not specified";

}

?>

</td>


<!-- =================================================
     ORGANIZER
===================================================== -->

<td>

<?php

if (!empty($row['organizer'])) {

    echo htmlspecialchars(
        $row['organizer'],
        ENT_QUOTES,
        'UTF-8'
    );

} else {

    echo "Not specified";

}

?>

</td>


<!-- =================================================
     ACTIONS
===================================================== -->

<td>


<!-- EDIT -->

<a
href="edit_event.php?id=<?php
echo urlencode((string)$row['id']);
?>"
class="btn btn-warning btn-sm action-btn">

✏️ Edit

</a>


<!-- DELETE -->

<form method="POST" action="delete_event.php" style="display:inline-block; margin:0;" onsubmit="return confirm('Are you sure you want to delete this event?');">
<input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit" class="btn btn-danger btn-sm action-btn">
🗑️ Delete
</button>
</form>


</td>


</tr>


<?php

    }


} else {

?>


<!-- =================================================
     NO EVENTS
===================================================== -->

<tr>

<td
colspan="9"
class="text-center py-5">


<div class="text-danger">

<h5>

📭 No Events Found

</h5>


<?php

if ($search !== "") {

?>

<p class="mb-0">

No events matched:

<strong>

<?php

echo htmlspecialchars(
    $search,
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>

</p>

<?php

} else {

?>

<p class="mb-0">

There are currently no events.

</p>

<?php

}

?>

</div>

</td>

</tr>


<?php

}

?>


</tbody>

</table>

</div>


<!-- =====================================================
     EVENT COUNT
===================================================== -->

<div class="mt-3 text-muted">

<?php

$total_events = $result
    ? mysqli_num_rows($result)
    : 0;

?>

<strong>

Total Events:

</strong>

<?php echo $total_events; ?>

</div>


</div>

</div>

</div>

</div>


<!-- =====================================================
     BROWSER BACK / BF CACHE PROTECTION
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