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


include '../includes/db.php';


/* =====================================================
   CHECK DONATION ID
===================================================== */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: view_donations.php");
    exit();

}


$id = (int) $_GET['id'];


/* =====================================================
   GET DONATION
===================================================== */

$sql = "SELECT * FROM donations WHERE id='$id'";

$result = mysqli_query($conn, $sql);


if (!$result) {

    die(
        "Database Error: " .
        mysqli_error($conn)
    );

}


if (mysqli_num_rows($result) == 0) {

    header("Location: view_donations.php");
    exit();

}


$row = mysqli_fetch_assoc($result);


/* =====================================================
   FORMAT DATE
===================================================== */

$donation_date = "N/A";

if (!empty($row['donation_date'])) {

    $donation_date = date(
        "d-m-Y",
        strtotime($row['donation_date'])
    );

}


/* =====================================================
   RECEIPT GENERATED DATE
===================================================== */

$generated_date = date("d-m-Y");


?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
Donation Receipt - <?php echo htmlspecialchars($row['donation_id']); ?>
</title>

<!-- =====================================================
     BOOTSTRAP
===================================================== -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<!-- =====================================================
     BOOTSTRAP ICONS
===================================================== -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

/* =====================================================
   PAGE
===================================================== */

body {

    background: #f2f2f2;

    font-family: Arial, sans-serif;

}


/* =====================================================
   RECEIPT
===================================================== */

.receipt {

    width: 750px;

    max-width: 95%;

    margin: 30px auto;

    background: white;

    border: 2px solid #222;

    padding: 30px;

    border-radius: 10px;

    box-shadow:
    0 5px 20px rgba(0,0,0,0.12);

}


/* =====================================================
   CHURCH HEADER
===================================================== */

.church-header {

    text-align: center;

}


.church-header h1 {

    margin: 0;

    font-size: 27px;

    font-weight: bold;

}


.church-header p {

    margin: 5px 0;

    color: #555;

}


/* =====================================================
   RECEIPT NUMBER / DATE
===================================================== */

.receipt-info {

    display: flex;

    justify-content: space-between;

    margin-top: 15px;

    margin-bottom: 10px;

    font-size: 14px;

}


/* =====================================================
   TITLE
===================================================== */

.receipt-title {

    text-align: center;

    font-size: 23px;

    font-weight: bold;

    margin: 20px 0;

    letter-spacing: 1px;

}


/* =====================================================
   TABLE
===================================================== */

.receipt-table {

    width: 100%;

    border-collapse: collapse;

}


.receipt-table th,
.receipt-table td {

    border: 1px solid #444;

    padding: 11px;

}


.receipt-table th {

    width: 40%;

    background: #f1f1f1;

}


.receipt-table td {

    background: white;

}


/* =====================================================
   AMOUNT
===================================================== */

.amount {

    font-size: 22px;

    font-weight: bold;

}


/* =====================================================
   THANK YOU
===================================================== */

.thank-you {

    text-align: center;

    margin-top: 25px;

}


.thank-you h4 {

    font-weight: bold;

}


.thank-you p {

    margin-bottom: 0;

}


/* =====================================================
   SIGNATURE
===================================================== */

.signature {

    display: flex;

    justify-content: space-between;

    gap: 60px;

    margin-top: 55px;

}


.signature div {

    text-align: center;

    width: 40%;

}


.signature hr {

    border-top: 1px solid #000;

}


/* =====================================================
   FOOTER
===================================================== */

.receipt-footer {

    text-align: center;

    margin-top: 20px;

    font-size: 13px;

    color: #666;

}


/* =====================================================
   PRINT
===================================================== */

@media print {

    @page {

        size: A4;

        margin: 15mm;

    }


    body {

        background: white;

    }


    .receipt {

        width: 100%;

        max-width: 100%;

        margin: 0;

        padding: 20px;

        border: 2px solid #000;

        box-shadow: none;

    }


    .print-buttons {

        display: none !important;

    }

}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width:600px) {

    .receipt {

        padding: 15px;

        margin: 15px auto;

    }


    .church-header h1 {

        font-size: 20px;

    }


    .receipt-title {

        font-size: 18px;

    }


    .receipt-info {

        display: block;

        line-height: 1.8;

    }


    .receipt-table th,
    .receipt-table td {

        padding: 8px;

        font-size: 14px;

    }


    .amount {

        font-size: 18px;

    }


    .signature {

        gap: 20px;

        margin-top: 35px;

    }


    .signature div {

        width: 50%;

    }

}

</style>

</head>

<body>

<div class="receipt">

<!-- =================================================
     CHURCH HEADER
================================================= -->

<div class="church-header">

<h1>

CHURCH MEMBER MANAGEMENT SYSTEM

</h1>

<p>

Church Donation Department

</p>

</div>

<hr>

<!-- =================================================
     RECEIPT INFORMATION
================================================= -->

<div class="receipt-info">

<div>

<strong>Receipt No:</strong>

<?php

echo htmlspecialchars(
    $row['donation_id']
);

?>

</div>

<div>

<strong>Generated:</strong>

<?php

echo $generated_date;

?>

</div>

</div>

<!-- =================================================
     RECEIPT TITLE
================================================= -->

<div class="receipt-title">

DONATION RECEIPT

</div>

<!-- =================================================
     DONATION DETAILS
================================================= -->

<table class="receipt-table">

<!-- MEMBER ID -->

<tr>

<th>

Member ID

</th>

<td>

<?php

if (
    isset($row['member_id']) &&
    $row['member_id'] != ""
) {

    echo htmlspecialchars(
        $row['member_id']
    );

} else {

    echo "N/A";

}

?>

</td>

</tr>

<!-- DONOR NAME -->

<tr>

<th>

Donor Name

</th>

<td>

<strong>

<?php

echo htmlspecialchars(
    $row['donor_name']
);

?>

</strong>

</td>

</tr>

<!-- DONATION TYPE -->

<tr>

<th>

Donation Type

</th>

<td>

<?php

echo htmlspecialchars(
    $row['donation_type']
);

?>

</td>

</tr>

<!-- AMOUNT -->

<tr>

<th>

Amount

</th>

<td class="amount">

₹<?php

echo number_format(
(float)$row['amount'],
2
);

?>

</td>

</tr>

<!-- PAYMENT METHOD -->

<tr>

<th>

Payment Method

</th>

<td>

<?php

echo htmlspecialchars(
    $row['payment_method']
);

?>

</td>

</tr>

<!-- DONATION DATE -->

<tr>

<th>

Donation Date

</th>

<td>

<?php

echo htmlspecialchars(
    $donation_date
);

?>

</td>

</tr>

<!-- PURPOSE -->

<tr>

<th>

Purpose

</th>

<td>

<?php

if (!empty($row['purpose'])) {

    echo htmlspecialchars(
        $row['purpose']
    );

} else {

    echo "N/A";

}

?>

</td>

</tr>

<!-- REMARKS -->

<tr>

<th>

Remarks

</th>

<td>

<?php

if (!empty($row['remarks'])) {

    echo htmlspecialchars(
        $row['remarks']
    );

} else {

    echo "N/A";

}

?>

</td>

</tr>

</table>

<!-- =================================================
     THANK YOU MESSAGE
================================================= -->

<div class="thank-you">

<h4>

Thank You for Your Generous Donation!

</h4>

<p>

May God Bless You.

</p>

</div>

<!-- =================================================
     SIGNATURES
================================================= -->

<div class="signature">

<div>

<hr>

<p>

Donor Signature

</p>

</div>

<div>

<hr>

<p>

Authorized Signature

</p>

</div>

</div>

<!-- =================================================
     FOOTER
================================================= -->

<div class="receipt-footer">

<p>

This receipt is generated by the Church Member Management System.

</p>

</div>

<hr>

<!-- =================================================
     BUTTONS
================================================= -->

<div class="text-center print-buttons">

<button
type="button"
class="btn btn-success"
onclick="window.print()">

<i class="bi bi-printer"></i>

Print Receipt

</button>

<a
href="view_donations.php"
class="btn btn-primary">

<i class="bi bi-arrow-left"></i>

Back to Donations

</a>

<a
href="../dashboard.php"
class="btn btn-secondary">

<i class="bi bi-house"></i>

Dashboard

</a>

</div>

</div>

<!-- =====================================================
     BROWSER BACK / BFCache PROTECTION
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