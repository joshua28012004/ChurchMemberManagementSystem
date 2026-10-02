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
   SESSION SECURITY CHECK
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
   SEARCH DONATIONS
===================================================== */

$search = "";

if (isset($_GET['search'])) {

    $search = trim($_GET['search']);

    /* Limit search input length */

    if (strlen($search) > 100) {

        $search = substr($search, 0, 100);

    }

}


/* =====================================================
   GET DONATIONS
===================================================== */

$donations_list = [];
$total_amount = 0.0;

if ($search !== "") {

    $search_value = "%" . $search . "%";


    $stmt = mysqli_prepare(
        $conn,
        "SELECT d.*, m.member_id AS member_code
         FROM donations d
         LEFT JOIN members m ON d.member_id = m.id
         WHERE d.donation_id LIKE ?
            OR d.donor_name LIKE ?
            OR d.donation_type LIKE ?
            OR d.donation_date LIKE ?
            OR m.member_id LIKE ?
         ORDER BY d.id DESC"
    );


    if (!$stmt) {

        header("Location: view_donations.php?msg=error");
        exit();

    }


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

        mysqli_stmt_close($stmt);

        header("Location: view_donations.php?msg=error");
        exit();

    }


    $result = mysqli_stmt_get_result($stmt);


    if (!$result) {

        mysqli_stmt_close($stmt);

        header("Location: view_donations.php?msg=error");
        exit();

    }

    while ($r = mysqli_fetch_assoc($result)) {
        $donations_list[] = $r;
        $total_amount += (float)$r['amount'];
    }

    mysqli_stmt_close($stmt);

} else {


    $stmt = mysqli_prepare(
        $conn,
        "SELECT d.*, m.member_id AS member_code
         FROM donations d
         LEFT JOIN members m ON d.member_id = m.id
         ORDER BY d.id DESC"
    );


    if (!$stmt) {

        header("Location: view_donations.php?msg=error");
        exit();

    }


    if (!mysqli_stmt_execute($stmt)) {

        mysqli_stmt_close($stmt);

        header("Location: view_donations.php?msg=error");
        exit();

    }


    $result = mysqli_stmt_get_result($stmt);


    if (!$result) {

        mysqli_stmt_close($stmt);

        header("Location: view_donations.php?msg=error");
        exit();

    }

    while ($r = mysqli_fetch_assoc($result)) {
        $donations_list[] = $r;
        $total_amount += (float)$r['amount'];
    }

    mysqli_stmt_close($stmt);

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Donation Management</title>


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
   PAGE
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
    0 4px 15px rgba(0,0,0,0.10);

}


/* =====================================================
   HEADER
===================================================== */

.page-header {

    background: linear-gradient(
        135deg,
        #198754,
        #0f5132
    );

    color: white;

    border-radius: 15px 15px 0 0;

    padding: 20px;

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


.table td {

    white-space: nowrap;

}


/* =====================================================
   BUTTONS
===================================================== */

.btn {

    border-radius: 7px;

}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width:768px){

    .container {

        padding-left: 10px;

        padding-right: 10px;

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
     PAGE HEADER
===================================================== -->

<div class="page-header">


<div
class="d-flex justify-content-between align-items-center flex-wrap">


<div>

<h2 class="mb-1">

<i class="bi bi-cash-stack"></i>

Donation Management

</h2>


<p class="mb-0">

Manage church donations and payment records

</p>

</div>


<div class="mt-2 mt-md-0">


<a
href="../dashboard.php"
class="btn btn-light me-2">

<i class="bi bi-speedometer2"></i>

Dashboard

</a>


<a
href="add_donation.php"
class="btn btn-warning">

<i class="bi bi-plus-circle"></i>

Add Donation

</a>


</div>


</div>


</div>


<!-- =====================================================
     CARD BODY
===================================================== -->

<div class="card-body">


<!-- =====================================================
     SUCCESS / ERROR MESSAGE
===================================================== -->

<?php

if (isset($_GET['msg'])) {

    if ($_GET['msg'] === "deleted") {

        echo '
        <div class="alert alert-success alert-dismissible fade show"
             role="alert">

            <i class="bi bi-check-circle-fill"></i>

            Donation deleted successfully.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>';

    } elseif ($_GET['msg'] === "updated") {

        echo '
        <div class="alert alert-success alert-dismissible fade show"
             role="alert">

            <i class="bi bi-check-circle-fill"></i>

            Donation updated successfully.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>';

    } elseif ($_GET['msg'] === "added") {

        echo '
        <div class="alert alert-success alert-dismissible fade show"
             role="alert">

            <i class="bi bi-check-circle-fill"></i>

            Donation added successfully.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>';

    } elseif ($_GET['msg'] === "invalid") {

        echo '
        <div class="alert alert-danger alert-dismissible fade show" role="alert">

            <i class="bi bi-exclamation-triangle-fill"></i>

            Invalid security token. Please try again.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>';

    } elseif ($_GET['msg'] === "error") {

        echo '
        <div class="alert alert-danger alert-dismissible fade show"
             role="alert">

            <i class="bi bi-exclamation-triangle-fill"></i>

            Something went wrong. Please try again.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>';

    }

}

?>


<!-- =====================================================
     SEARCH
===================================================== -->

<form
method="GET"
class="mb-4">


<div class="row g-2">


<div class="col-md-9">


<div class="input-group">


<span class="input-group-text">

<i class="bi bi-search"></i>

</span>


<input
type="text"
name="search"
class="form-control"
placeholder="Search Donation ID, Member Code, Donor, Type or Date"
value="<?php
echo htmlspecialchars(
    $search,
    ENT_QUOTES,
    'UTF-8'
);
?>"
maxlength="100">


<button
type="submit"
class="btn btn-primary">

Search

</button>


<a
href="view_donations.php"
class="btn btn-secondary">

Reset

</a>


</div>


</div>


<div class="col-md-3">


<a
href="add_donation.php"
class="btn btn-success w-100">

<i class="bi bi-plus-circle"></i>

+ Add Donation

</a>


</div>


</div>


</form>


<!-- =====================================================
     SUMMARY TOTALS
===================================================== -->

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">

<div>

<span class="badge bg-success fs-6 px-3 py-2">

<i class="bi bi-cash me-1"></i> Total: ₹<?php echo number_format($total_amount, 2); ?>

</span>

<span class="badge bg-secondary fs-6 px-3 py-2 ms-1">

<i class="bi bi-list-ol me-1"></i> Records: <?php echo count($donations_list); ?>

</span>

</div>

<?php if ($search !== ""): ?>

<div>

<small class="text-muted">Showing results for "<strong><?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?></strong>"</small>

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     DONATION TABLE
===================================================== -->

<div class="table-responsive">


<table
class="table table-bordered table-striped table-hover">


<thead class="table-dark">


<tr>

<th>ID</th>

<th>Donation ID</th>

<th>Member Code</th>

<th>Donor</th>

<th>Type</th>

<th>Amount</th>

<th>Payment</th>

<th>Date</th>

<th>Purpose</th>

<th>Actions</th>

</tr>


</thead>


<tbody>


<?php

if (count($donations_list) > 0) {


    foreach ($donations_list as $row) {

?>


<tr>


<!-- ID -->

<td>

<?php

echo htmlspecialchars(
    $row['id'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- DONATION ID -->

<td>

<?php

echo htmlspecialchars(
    $row['donation_id'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- MEMBER CODE -->

<td>

<?php

if (!empty($row['member_code'])) {

    echo '<span class="badge bg-primary">' . htmlspecialchars((string)$row['member_code'], ENT_QUOTES, 'UTF-8') . '</span>';

} else {

    echo '<span class="text-muted">—</span>';

}

?>

</td>


<!-- DONOR -->

<td>

<strong>

<?php

echo htmlspecialchars(
    $row['donor_name'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>

</td>


<!-- TYPE -->

<td>

<?php

echo htmlspecialchars(
    $row['donation_type'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- AMOUNT -->

<td>

<strong>

₹<?php

echo number_format(
    (float)$row['amount'],
    2
);

?>

</strong>

</td>


<!-- PAYMENT -->

<td>

<?php

echo htmlspecialchars(
    $row['payment_method'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- DATE -->

<td>

<?php

echo htmlspecialchars(
    $row['donation_date'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- PURPOSE -->

<td>

<?php

echo htmlspecialchars(
    $row['purpose'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- ACTIONS -->

<td>


<a
href="edit_donation.php?id=<?php
echo urlencode($row['id']);
?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil-square"></i>

Edit

</a>


<form method="POST" action="delete_donation.php" style="display:inline-block; margin:0;" onsubmit="return confirm('Are you sure you want to delete this donation?');">
<input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit" class="btn btn-danger btn-sm">
<i class="bi bi-trash"></i> Delete
</button>
</form>


<a
href="receipt.php?id=<?php
echo urlencode($row['id']);
?>"
class="btn btn-info btn-sm">

<i class="bi bi-receipt"></i>

Receipt

</a>


</td>


</tr>


<?php

    }

} else {

?>


<tr>

<td
colspan="10"
class="text-center text-danger py-4">


<i
class="bi bi-cash-coin fs-2 d-block mb-2">
</i>


<strong>

No Donations Found

</strong>


</td>

</tr>


<?php

}

?>


</tbody>


<tfoot>

<tr class="table-light fw-bold">

<td colspan="5" class="text-end">Total Amount:</td>

<td>₹<?php echo number_format($total_amount, 2); ?></td>

<td colspan="4"></td>

</tr>

</tfoot>


</table>


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
   CLOSE PREPARED STATEMENT
===================================================== */

mysqli_stmt_close($stmt);

?>