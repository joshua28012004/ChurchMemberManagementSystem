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
   SEARCH
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
   GET MEMBERS
===================================================== */

if ($search !== "") {

    $search_value = "%" . $search . "%";


    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM members
         WHERE member_id LIKE ?
            OR fullname LIKE ?
            OR phone LIKE ?
            OR email LIKE ?
         ORDER BY id DESC"
    );


    if (!$stmt) {

        header("Location: view_members.php?msg=error");
        exit();

    }


    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value
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

} else {


    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM members
         ORDER BY id DESC"
    );


    if (!$stmt) {

        header("Location: view_members.php?msg=error");
        exit();

    }


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

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Church Members</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">


<style>

body {
    background: #f5f6fa;
}

.main-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.10);
}

.page-header {
    background: linear-gradient(
        135deg,
        #0d6efd,
        #084298
    );
    color: white;
    border-radius: 15px 15px 0 0;
    padding: 20px;
}

.member-photo {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #dee2e6;
}

.table {
    vertical-align: middle;
}

.table th {
    white-space: nowrap;
}

.table td {
    white-space: nowrap;
}

.btn {
    border-radius: 7px;
}

@media(max-width:768px){

    .container-fluid {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }

}

</style>

</head>


<body>


<div class="container-fluid mt-4 px-4">

<div class="card main-card">


<div class="page-header">

<div class="d-flex justify-content-between align-items-center flex-wrap">


<div>

<h2 class="mb-1">

<i class="bi bi-people-fill"></i>

Church Members

</h2>

<p class="mb-0">
Manage registered church members
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
href="add_member.php"
class="btn btn-success">

<i class="bi bi-person-plus-fill"></i>

Add Member

</a>


</div>

</div>

</div>


<div class="card-body">


<!--
 =====================================================
     SUCCESS / ERROR MESSAGE
=====================================================
-->

<?php

if (isset($_GET['msg'])) {

    if ($_GET['msg'] === "deleted") {

        echo '
        <div class="alert alert-success alert-dismissible fade show" role="alert">

            <i class="bi bi-check-circle-fill"></i>

            Member deleted successfully.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>';

    } elseif ($_GET['msg'] === "updated") {

        echo '
        <div class="alert alert-success alert-dismissible fade show" role="alert">

            <i class="bi bi-check-circle-fill"></i>

            Member updated successfully.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>';

    } elseif ($_GET['msg'] === "error") {

        echo '
        <div class="alert alert-danger alert-dismissible fade show" role="alert">

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


<!--
 =====================================================
     SEARCH FORM
=====================================================
-->

<form method="GET" class="mb-4">


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
placeholder="Search by Member ID, Name, Phone or Email"
value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
maxlength="100">


<button
type="submit"
class="btn btn-primary">

Search

</button>


<a
href="view_members.php"
class="btn btn-secondary">

Reset

</a>


</div>

</div>


<div class="col-md-3">


<a
href="add_member.php"
class="btn btn-success w-100">

<i class="bi bi-person-plus"></i>

+ Add Member

</a>


</div>


</div>


</form>


<!--
 =====================================================
     MEMBERS TABLE
=====================================================
-->

<div class="table-responsive">


<table class="table table-bordered table-striped table-hover">


<thead class="table-dark">


<tr>

<th>ID</th>
<th>Photo</th>
<th>Member ID</th>
<th>Name</th>
<th>Gender</th>
<th>DOB</th>
<th>Phone</th>
<th>Email</th>
<th>Marital Status</th>
<th>Actions</th>

</tr>


</thead>


<tbody>


<?php

if (mysqli_num_rows($result) > 0) {


    while ($row = mysqli_fetch_assoc($result)) {

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
src="../uploads/<?php echo htmlspecialchars($safe_photo, ENT_QUOTES, 'UTF-8'); ?>"
class="member-photo"
alt="Member Photo">


<?php

    } else {

?>


<i class="bi bi-person-circle fs-2 text-secondary"></i>


<?php

    }

} else {

?>


<i class="bi bi-person-circle fs-2 text-secondary"></i>


<?php

}

?>


</td>


<!-- MEMBER ID -->

<td>


<strong>

<?php

echo htmlspecialchars(
    $row['member_id'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>


</td>


<!-- NAME -->

<td>


<strong>

<?php

echo htmlspecialchars(
    $row['fullname'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>


</td>


<!-- GENDER -->

<td>

<?php

echo htmlspecialchars(
    $row['gender'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- DOB -->

<td>

<?php

echo htmlspecialchars(
    $row['dob'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- PHONE -->

<td>

<?php

echo htmlspecialchars(
    $row['phone'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- EMAIL -->

<td>

<?php

echo htmlspecialchars(
    $row['email'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- MARITAL STATUS -->

<td>

<?php

echo htmlspecialchars(
    $row['marital_status'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<!-- ACTIONS -->

<td>


<a
href="edit_member.php?id=<?php echo urlencode($row['id']); ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil-square"></i>

Edit

</a>


<a
href="delete_member.php?id=<?php echo urlencode($row['id']); ?>"
class="btn btn-danger btn-sm"

onclick="return confirm('Are you sure you want to delete this member?');">

<i class="bi bi-trash"></i>

Delete

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
class="text-center text-danger py-5">


<i class="bi bi-person-x fs-2 d-block mb-2"></i>


<strong>
No Members Found
</strong>


<?php

if ($search !== "") {

?>


<p class="mb-0">

No members matched:

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

}

?>


</td>


</tr>


<?php

}

?>


</tbody>


</table>


</div>


<!--
 =====================================================
     TOTAL MEMBERS
=====================================================
-->

<div class="mt-3 text-muted">


<strong>
Total Members:
</strong>


<?php

echo mysqli_num_rows($result);

?>


</div>


</div>


</div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<!--
 =====================================================
     BFCache PROTECTION
=====================================================
-->

<script>

window.addEventListener("pageshow", function(event) {

    if (event.persisted) {

        window.location.reload();

    }

});

</script>


</body>

</html>


<?php

/*
 * Close the prepared statement after
 * the result has been fully used.
 */

mysqli_stmt_close($stmt);

?>