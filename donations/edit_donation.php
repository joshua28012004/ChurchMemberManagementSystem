<?php

session_start();


/* =====================================================
   SECURITY: PREVENT BROWSER CACHING
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
   CSRF TOKEN
===================================================== */

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    strlen($_SESSION['csrf_token']) !== 64
) {

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

}

$csrf_token =
    $_SESSION['csrf_token'];


/* =====================================================
   DATABASE
===================================================== */

include '../includes/db.php';


$message = "";
$message_type = "danger";


/* =====================================================
   ALLOWED VALUES
===================================================== */

$allowed_donation_types = [

    "Tithe",
    "Offering",
    "Building Fund",
    "Mission",
    "Charity",
    "Special Donation",
    "Thanksgiving"

];


$allowed_payment_methods = [

    "Cash",
    "UPI",
    "Bank Transfer",
    "Cheque",
    "Online"

];


/* =====================================================
   CHECK DONATION ID
===================================================== */

if (
    !isset($_GET['id']) ||
    !ctype_digit((string)$_GET['id']) ||
    (int)$_GET['id'] <= 0
) {

    header("Location: view_donations.php");
    exit();

}

$id = (int)$_GET['id'];


/* =====================================================
   GET DONATION
===================================================== */

$select_stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM donations
     WHERE id = ?
     LIMIT 1"
);


if (!$select_stmt) {

    header("Location: view_donations.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $select_stmt,
    "i",
    $id
);


if (!mysqli_stmt_execute($select_stmt)) {

    mysqli_stmt_close($select_stmt);

    header("Location: view_donations.php?msg=error");
    exit();

}


$result =
    mysqli_stmt_get_result(
        $select_stmt
    );


if (!$result) {

    mysqli_stmt_close($select_stmt);

    header("Location: view_donations.php?msg=error");
    exit();

}


if (mysqli_num_rows($result) === 0) {

    mysqli_stmt_close($select_stmt);

    header("Location: view_donations.php");
    exit();

}


$row =
    mysqli_fetch_assoc($result);


mysqli_stmt_close($select_stmt);


/* =====================================================
   UPDATE DONATION
===================================================== */

if (isset($_POST['update'])) {


    /* =================================================
       CSRF VALIDATION
    ================================================= */

    $submitted_token =
        $_POST['csrf_token'] ?? "";


    if (
        !is_string($submitted_token) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $submitted_token
        )
    ) {

        $message =
            "Invalid security token. Please refresh the page and try again.";

        $message_type = "danger";

    }


    else {


        /* =============================================
           GET FORM DATA
        ============================================= */

        $donor_name =
            trim(
                (string)(
                    $_POST['donor_name'] ?? ""
                )
            );


        $donation_type =
            trim(
                (string)(
                    $_POST['donation_type'] ?? ""
                )
            );


        $amount_input =
            trim(
                (string)(
                    $_POST['amount'] ?? ""
                )
            );


        $payment_method =
            trim(
                (string)(
                    $_POST['payment_method'] ?? ""
                )
            );


        $donation_date =
            trim(
                (string)(
                    $_POST['donation_date'] ?? ""
                )
            );


        $purpose =
            trim(
                (string)(
                    $_POST['purpose'] ?? ""
                )
            );


        $remarks =
            trim(
                (string)(
                    $_POST['remarks'] ?? ""
                )
            );


        /* =============================================
           VALIDATE DONOR NAME
        ============================================= */

        if ($donor_name === "") {

            $message =
                "Donor name is required.";

            $message_type = "danger";

        }


        elseif (strlen($donor_name) > 100) {

            $message =
                "Donor name is too long.";

            $message_type = "danger";

        }


        /* =============================================
           VALIDATE DONATION TYPE
        ============================================= */

        elseif (
            !in_array(
                $donation_type,
                $allowed_donation_types,
                true
            )
        ) {

            $message =
                "Please select a valid donation type.";

            $message_type = "danger";

        }


        /* =============================================
           VALIDATE AMOUNT
        ============================================= */

        elseif (
            $amount_input === "" ||
            !is_numeric($amount_input)
        ) {

            $message =
                "Please enter a valid donation amount.";

            $message_type = "danger";

        }


        elseif ((float)$amount_input <= 0) {

            $message =
                "Donation amount must be greater than zero.";

            $message_type = "danger";

        }


        elseif ((float)$amount_input > 100000000) {

            $message =
                "Donation amount is too large.";

            $message_type = "danger";

        }


        elseif (
            !preg_match(
                '/^\d+(\.\d{1,2})?$/',
                $amount_input
            )
        ) {

            $message =
                "Please enter an amount with a maximum of 2 decimal places.";

            $message_type = "danger";

        }


        /* =============================================
           VALIDATE PAYMENT METHOD
        ============================================= */

        elseif (
            !in_array(
                $payment_method,
                $allowed_payment_methods,
                true
            )
        ) {

            $message =
                "Please select a valid payment method.";

            $message_type = "danger";

        }


        /* =============================================
           VALIDATE DATE FORMAT
        ============================================= */

        elseif (
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $donation_date
            )
        ) {

            $message =
                "Please enter a valid donation date.";

            $message_type = "danger";

        }


        else {


            /* =========================================
               VERIFY DATE
            ========================================= */

            $date_object =
                DateTime::createFromFormat(
                    "Y-m-d",
                    $donation_date
                );


            $date_errors =
                DateTime::getLastErrors();


            $valid_date =
                $date_object !== false &&
                (
                    $date_errors === false ||
                    (
                        $date_errors['warning_count'] === 0 &&
                        $date_errors['error_count'] === 0
                    )
                );


            if (!$valid_date) {

                $message =
                    "Please enter a valid donation date.";

                $message_type = "danger";

            }


            elseif (strlen($purpose) > 255) {

                $message =
                    "Purpose is too long.";

                $message_type = "danger";

            }


            elseif (strlen($remarks) > 1000) {

                $message =
                    "Remarks are too long.";

                $message_type = "danger";

            }


            else {


                /* =====================================
                   CONVERT AMOUNT
                ===================================== */

                $amount =
                    (float)$amount_input;


                /* =====================================
                   UPDATE DATABASE
                ===================================== */

                $update_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE donations
                     SET
                        donor_name = ?,
                        donation_type = ?,
                        amount = ?,
                        payment_method = ?,
                        donation_date = ?,
                        purpose = ?,
                        remarks = ?
                     WHERE id = ?"
                );


                if (!$update_stmt) {

                    $message =
                        "Unable to update the donation.";

                    $message_type = "danger";

                }


                else {


                    mysqli_stmt_bind_param(
                        $update_stmt,
                        "ssdssssi",
                        $donor_name,
                        $donation_type,
                        $amount,
                        $payment_method,
                        $donation_date,
                        $purpose,
                        $remarks,
                        $id
                    );


                    if (
                        mysqli_stmt_execute(
                            $update_stmt
                        )
                    ) {


                        mysqli_stmt_close(
                            $update_stmt
                        );


                        /* =============================
                           REGENERATE CSRF TOKEN
                        ============================= */

                        $_SESSION['csrf_token'] =
                            bin2hex(
                                random_bytes(32)
                            );


                        header(
                            "Location: view_donations.php?msg=updated"
                        );

                        exit();

                    }


                    else {


                        $message =
                            "Unable to update the donation. Please try again.";

                        $message_type =
                            "danger";


                        mysqli_stmt_close(
                            $update_stmt
                        );

                    }

                }

            }

        }

    }

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<title>Edit Donation</title>

<meta
name="viewport"
content="width=device-width, initial-scale=1">


<!-- =====================================================
     BOOTSTRAP
===================================================== -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<style>

body {

    background: #f5f6fa;

}


.card {

    border: none;

    border-radius: 12px;

}


.card-header {

    border-radius: 12px 12px 0 0 !important;

}


.form-label {

    font-weight: 600;

}


.btn {

    border-radius: 7px;

}

</style>

</head>


<body>


<div class="container mt-4 mb-5">


<div class="card shadow">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="card-header bg-warning text-dark">

<h3 class="mb-0">

✏️ Edit Donation

</h3>

</div>


<div class="card-body p-4">


<!-- =====================================================
     ERROR MESSAGE
===================================================== -->

<?php

if ($message !== "") {

?>

<div
class="alert alert-<?php
echo htmlspecialchars(
    $message_type,
    ENT_QUOTES,
    'UTF-8'
);
?>">

<?php

echo htmlspecialchars(
    $message,
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>

<?php

}

?>


<form method="POST">


<!-- =====================================================
     CSRF TOKEN
===================================================== -->

<input
type="hidden"
name="csrf_token"
value="<?php
echo htmlspecialchars(
    $csrf_token,
    ENT_QUOTES,
    'UTF-8'
);
?>">


<div class="row">


<!-- =================================================
     DONATION ID
================================================= -->

<div class="col-md-6 mb-3">

<label class="form-label">

Donation ID

</label>

<input
type="text"
class="form-control"
value="<?php
echo htmlspecialchars(
    $row['donation_id'],
    ENT_QUOTES,
    'UTF-8'
);
?>"
readonly>

</div>


<!-- =================================================
     DONOR NAME
================================================= -->

<div class="col-md-6 mb-3">

<label class="form-label">

Donor Name

</label>

<input
type="text"
name="donor_name"
class="form-control"
value="<?php
echo htmlspecialchars(
    $row['donor_name'],
    ENT_QUOTES,
    'UTF-8'
);
?>"
maxlength="100"
required>

</div>


<!-- =================================================
     DONATION TYPE
================================================= -->

<div class="col-md-6 mb-3">

<label class="form-label">

Donation Type

</label>

<select
name="donation_type"
class="form-control"
required>

<option
value="Tithe"
<?php
if ($row['donation_type'] === "Tithe")
    echo "selected";
?>>

Tithe

</option>


<option
value="Offering"
<?php
if ($row['donation_type'] === "Offering")
    echo "selected";
?>>

Offering

</option>


<option
value="Building Fund"
<?php
if ($row['donation_type'] === "Building Fund")
    echo "selected";
?>>

Building Fund

</option>


<option
value="Mission"
<?php
if ($row['donation_type'] === "Mission")
    echo "selected";
?>>

Mission

</option>


<option
value="Charity"
<?php
if ($row['donation_type'] === "Charity")
    echo "selected";
?>>

Charity

</option>


<option
value="Special Donation"
<?php
if ($row['donation_type'] === "Special Donation")
    echo "selected";
?>>

Special Donation

</option>


<option
value="Thanksgiving"
<?php
if ($row['donation_type'] === "Thanksgiving")
    echo "selected";
?>>

Thanksgiving

</option>

</select>

</div>


<!-- =================================================
     AMOUNT
================================================= -->

<div class="col-md-6 mb-3">

<label class="form-label">

Amount (₹)

</label>

<input
type="number"
name="amount"
class="form-control"
value="<?php
echo htmlspecialchars(
    $row['amount'],
    ENT_QUOTES,
    'UTF-8'
);
?>"
min="1"
max="100000000"
step="0.01"
required>

</div>


<!-- =================================================
     PAYMENT METHOD
================================================= -->

<div class="col-md-6 mb-3">

<label class="form-label">

Payment Method

</label>

<select
name="payment_method"
class="form-control"
required>


<option
value="Cash"
<?php
if ($row['payment_method'] === "Cash")
    echo "selected";
?>>

Cash

</option>


<option
value="UPI"
<?php
if ($row['payment_method'] === "UPI")
    echo "selected";
?>>

UPI

</option>


<option
value="Bank Transfer"
<?php
if ($row['payment_method'] === "Bank Transfer")
    echo "selected";
?>>

Bank Transfer

</option>


<option
value="Cheque"
<?php
if ($row['payment_method'] === "Cheque")
    echo "selected";
?>>

Cheque

</option>


<option
value="Online"
<?php
if ($row['payment_method'] === "Online")
    echo "selected";
?>>

Online

</option>

</select>

</div>


<!-- =================================================
     DONATION DATE
================================================= -->

<div class="col-md-6 mb-3">

<label class="form-label">

Donation Date

</label>

<input
type="date"
name="donation_date"
class="form-control"
value="<?php
echo htmlspecialchars(
    $row['donation_date'],
    ENT_QUOTES,
    'UTF-8'
);
?>"
required>

</div>


<!-- =================================================
     PURPOSE
================================================= -->

<div class="col-md-6 mb-3">

<label class="form-label">

Purpose

</label>

<input
type="text"
name="purpose"
class="form-control"
value="<?php
echo htmlspecialchars(
    $row['purpose'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
?>"
maxlength="255">

</div>


<!-- =================================================
     REMARKS
================================================= -->

<div class="col-md-12 mb-3">

<label class="form-label">

Remarks

</label>

<textarea
name="remarks"
class="form-control"
rows="3"
maxlength="1000"><?php
echo htmlspecialchars(
    $row['remarks'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
?></textarea>

</div>


</div>


<!-- =====================================================
     BUTTONS
===================================================== -->

<button
type="submit"
name="update"
class="btn btn-success">

💾 Update Donation

</button>


<a
href="view_donations.php"
class="btn btn-secondary">

⬅ Back

</a>


<a
href="../dashboard.php"
class="btn btn-primary">

🏠 Dashboard

</a>


</form>


</div>

</div>

</div>


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