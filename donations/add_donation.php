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
   DATABASE CONNECTION
===================================================== */

include '../includes/db.php';


$message = "";
$message_type = "success";


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
   GENERATE NEXT DONATION ID
===================================================== */

$donation_id = "DON0001";


$id_stmt = mysqli_prepare(
    $conn,
    "SELECT donation_id
     FROM donations
     WHERE donation_id LIKE 'DON%'
     ORDER BY CAST(
         SUBSTRING(donation_id, 4)
         AS UNSIGNED
     ) DESC
     LIMIT 1"
);


if ($id_stmt) {

    if (mysqli_stmt_execute($id_stmt)) {

        $id_result =
            mysqli_stmt_get_result($id_stmt);


        if (
            $id_result &&
            mysqli_num_rows($id_result) > 0
        ) {

            $id_row =
                mysqli_fetch_assoc($id_result);


            $last_donation_id =
                $id_row['donation_id'] ?? "";


            if (
                preg_match(
                    '/^DON([0-9]+)$/',
                    $last_donation_id,
                    $matches
                )
            ) {

                $number =
                    (int)$matches[1];


                $next_number =
                    $number + 1;


                $donation_id =
                    "DON" .
                    str_pad(
                        $next_number,
                        4,
                        "0",
                        STR_PAD_LEFT
                    );

            }

        }

    }

    mysqli_stmt_close($id_stmt);

}


/* =====================================================
   SAVE DONATION
===================================================== */

if (isset($_POST['save'])) {


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

        $member_id =
            trim(
                (string)(
                    $_POST['member_id'] ?? ""
                )
            );


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
           VALIDATION
        ============================================= */

        if (
            $member_id === "" ||
            !ctype_digit($member_id) ||
            (int)$member_id <= 0
        ) {

            $message =
                "Please select a valid member.";

            $message_type = "danger";

        }


        elseif (
            $donor_name !== "" &&
            strlen($donor_name) > 100
        ) {

            $message =
                "Donor name is too long.";

            $message_type = "danger";

        }


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


        elseif (
            $amount_input === "" ||
            !is_numeric($amount_input)
        ) {

            $message =
                "Please enter a valid donation amount.";

            $message_type = "danger";

        }


        elseif (
            (float)$amount_input <= 0
        ) {

            $message =
                "Donation amount must be greater than zero.";

            $message_type = "danger";

        }


        elseif (
            (float)$amount_input > 100000000
        ) {

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


            else {


                /* =====================================
                   VERIFY MEMBER EXISTS
                ===================================== */

                $member_id_int =
                    (int)$member_id;


                $member_stmt =
                    mysqli_prepare(
                        $conn,
                        "SELECT id, fullname
                         FROM members
                         WHERE id = ?
                         LIMIT 1"
                    );


                if (!$member_stmt) {

                    $message =
                        "Unable to verify the selected member.";

                    $message_type = "danger";

                }


                else {


                    mysqli_stmt_bind_param(
                        $member_stmt,
                        "i",
                        $member_id_int
                    );


                    if (
                        !mysqli_stmt_execute(
                            $member_stmt
                        )
                    ) {

                        $message =
                            "Unable to verify the selected member.";

                        $message_type = "danger";

                        mysqli_stmt_close(
                            $member_stmt
                        );

                    }


                    else {


                        $member_result =
                            mysqli_stmt_get_result(
                                $member_stmt
                            );


                        if (
                            !$member_result ||
                            mysqli_num_rows(
                                $member_result
                            ) === 0
                        ) {

                            $message =
                                "Selected member does not exist.";

                            $message_type = "danger";

                            mysqli_stmt_close(
                                $member_stmt
                            );

                        }


                        else {


                            /* =============================
                               GET ACTUAL MEMBER NAME
                            ============================= */

                            $member_row =
                                mysqli_fetch_assoc(
                                    $member_result
                                );


                            $donor_name =
                                trim(
                                    $member_row['fullname'] ?? ""
                                );


                            mysqli_stmt_close(
                                $member_stmt
                            );


                            /* =============================
                               CONVERT AMOUNT
                            ============================= */

                            $amount =
                                number_format(
                                    (float)$amount_input,
                                    2,
                                    ".",
                                    ""
                                );


                            /* =============================
                               CHECK FIELD LENGTHS
                            ============================= */

                            if (
                                strlen($purpose) > 255
                            ) {

                                $message =
                                    "Purpose is too long.";

                                $message_type = "danger";

                            }


                            elseif (
                                strlen($remarks) > 1000
                            ) {

                                $message =
                                    "Remarks are too long.";

                                $message_type = "danger";

                            }


                            else {


                                /* =========================
                                   INSERT DONATION
                                ========================= */

                                $insert_stmt =
                                    mysqli_prepare(
                                        $conn,
                                        "INSERT INTO donations
                                        (
                                            donation_id,
                                            member_id,
                                            donor_name,
                                            donation_type,
                                            amount,
                                            payment_method,
                                            donation_date,
                                            purpose,
                                            remarks
                                        )
                                        VALUES
                                        (
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?
                                        )"
                                    );


                                if (!$insert_stmt) {

                                    $message =
                                        "Unable to save the donation.";

                                    $message_type =
                                        "danger";

                                }


                                else {


                                    mysqli_stmt_bind_param(
                                        $insert_stmt,
                                        "sissdssss",
                                        $donation_id,
                                        $member_id_int,
                                        $donor_name,
                                        $donation_type,
                                        $amount,
                                        $payment_method,
                                        $donation_date,
                                        $purpose,
                                        $remarks
                                    );


                                    if (
                                        mysqli_stmt_execute(
                                            $insert_stmt
                                        )
                                    ) {


                                        $message =
                                            "Donation Added Successfully! Donation ID: "
                                            . $donation_id;

                                        $message_type =
                                            "success";


                                        /* =====================
                                           GENERATE NEXT ID
                                        ===================== */

                                        $number =
                                            (int)substr(
                                                $donation_id,
                                                3
                                            );


                                        $next_number =
                                            $number + 1;


                                        $donation_id =
                                            "DON" .
                                            str_pad(
                                                $next_number,
                                                4,
                                                "0",
                                                STR_PAD_LEFT
                                            );


                                        /*
                                         * Regenerate the CSRF token
                                         * after successful submission.
                                         */

                                        $_SESSION['csrf_token'] =
                                            bin2hex(
                                                random_bytes(32)
                                            );

                                        $csrf_token =
                                            $_SESSION['csrf_token'];

                                    }


                                    else {


                                        /*
                                         * Do not expose the
                                         * database error.
                                         */

                                        $message =
                                            "Unable to save the donation. Please try again.";

                                        $message_type =
                                            "danger";

                                    }


                                    mysqli_stmt_close(
                                        $insert_stmt
                                    );

                                }

                            }

                        }

                    }

                }

            }

        }

    }

}


/* =====================================================
   GET MEMBERS
===================================================== */

$members_result = false;

$members_stmt = mysqli_prepare(
    $conn,
    "SELECT id, member_id, fullname
     FROM members
     ORDER BY fullname ASC"
);


if ($members_stmt) {

    if (mysqli_stmt_execute($members_stmt)) {

        $members_result =
            mysqli_stmt_get_result(
                $members_stmt
            );

    }

}

if (!$members_result && $message === "") {

    $message =
        "Unable to load members list. Please refresh the page.";

    $message_type = "warning";

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<title>Add Donation</title>

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

    background-color: #f5f6fa;

}


.card {

    border-radius: 12px;

}


.form-label {

    font-weight: 600;

}

</style>

</head>


<body>


<div class="container mt-4 mb-5">


<div class="card shadow">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="card-header bg-success text-white">

<h3 class="mb-0">

💰 Add Donation

</h3>

</div>


<div class="card-body">


<!-- =====================================================
     MESSAGE
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


<!-- =====================================================
     DONATION FORM
===================================================== -->

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
name="donation_id"
class="form-control"
value="<?php
echo htmlspecialchars(
    $donation_id,
    ENT_QUOTES,
    'UTF-8'
);
?>"
readonly>

</div>


<!-- =================================================
     SELECT MEMBER
================================================= -->

<div class="col-md-6 mb-3">

<label class="form-label">

Select Member

</label>

<select
name="member_id"
id="member"
class="form-control"
required>

<option value="">

Select Member

</option>


<?php

if ($members_result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $members_result
        )
    ) {

?>


<option
value="<?php
echo htmlspecialchars(
    $row['id'],
    ENT_QUOTES,
    'UTF-8'
);
?>"
data-name="<?php
echo htmlspecialchars(
    $row['fullname'],
    ENT_QUOTES,
    'UTF-8'
);
?>">

<?php

echo htmlspecialchars(
    $row['member_id'],
    ENT_QUOTES,
    'UTF-8'
);

?>

 -

<?php

echo htmlspecialchars(
    $row['fullname'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</option>


<?php

    }

}

?>


</select>

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
id="donor_name"
class="form-control"
readonly
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

<option value="Tithe">

Tithe

</option>

<option value="Offering">

Offering

</option>

<option value="Building Fund">

Building Fund

</option>

<option value="Mission">

Mission

</option>

<option value="Charity">

Charity

</option>

<option value="Special Donation">

Special Donation

</option>

<option value="Thanksgiving">

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
placeholder="Enter amount"
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

<option value="Cash">

Cash

</option>

<option value="UPI">

UPI

</option>

<option value="Bank Transfer">

Bank Transfer

</option>

<option value="Cheque">

Cheque

</option>

<option value="Online">

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
echo date('Y-m-d');
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
placeholder="Example: Church Building"
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
maxlength="1000"
placeholder="Enter any additional remarks"></textarea>

</div>


</div>


<!-- =====================================================
     BUTTONS
===================================================== -->

<button
type="submit"
name="save"
class="btn btn-success">

💰 Save Donation

</button>


<a
href="view_donations.php"
class="btn btn-primary">

View Donations

</a>


<a
href="../dashboard.php"
class="btn btn-secondary">

Dashboard

</a>


</form>


</div>

</div>

</div>


<!-- =====================================================
     AUTO-FILL DONOR NAME
===================================================== -->

<script>

document
.getElementById("member")
.addEventListener(
    "change",
    function () {

        var selectedOption =
            this.options[
                this.selectedIndex
            ];

        var donorName =
            selectedOption.getAttribute(
                "data-name"
            );

        document
        .getElementById("donor_name")
        .value =
            donorName || "";

    }
);

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
   CLOSE MEMBERS STATEMENT
===================================================== */

if ($members_stmt) {
    mysqli_stmt_close($members_stmt);
}

?>