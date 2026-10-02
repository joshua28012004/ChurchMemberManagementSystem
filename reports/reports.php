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
   TOTAL MEMBERS
===================================================== */

$total_members = 0;

$member_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total FROM members"
);

if ($member_stmt) {

    if (mysqli_stmt_execute($member_stmt)) {

        $member_result = mysqli_stmt_get_result($member_stmt);

        if ($member_result) {

            $member_data = mysqli_fetch_assoc($member_result);

            if ($member_data) {

                $total_members = (int)$member_data['total'];

            }

        }

    }

}


/* =====================================================
   DONATION SUMMARY
===================================================== */

$total_donations = 0;
$total_amount = 0;

$donation_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(amount), 0) AS amount
     FROM donations"
);

if ($donation_stmt) {

    if (mysqli_stmt_execute($donation_stmt)) {

        $donation_result = mysqli_stmt_get_result($donation_stmt);

        if ($donation_result) {

            $donation_data = mysqli_fetch_assoc($donation_result);

            if ($donation_data) {

                $total_donations =
                    (int)$donation_data['total'];

                $total_amount =
                    (float)$donation_data['amount'];

            }

        }

    }

}


/* =====================================================
   TOTAL EVENTS
===================================================== */

$total_events = 0;

$event_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total FROM events"
);

if ($event_stmt) {

    if (mysqli_stmt_execute($event_stmt)) {

        $event_result = mysqli_stmt_get_result($event_stmt);

        if ($event_result) {

            $event_data = mysqli_fetch_assoc($event_result);

            if ($event_data) {

                $total_events =
                    (int)$event_data['total'];

            }

        }

    }

}


/* =====================================================
   UPCOMING BIRTHDAYS
   Counts birthday occurrences within 1 year
===================================================== */

$upcoming_birthdays = 0;

$birthday_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM members
     WHERE dob IS NOT NULL
     AND STR_TO_DATE(
         CONCAT(
             YEAR(CURDATE()) +
             (
                 DATE_FORMAT(dob, '%m-%d')
                 < DATE_FORMAT(CURDATE(), '%m-%d')
             ),
             '-',
             DATE_FORMAT(dob, '%m-%d')
         ),
         '%Y-%m-%d'
     )
     BETWEEN CURDATE()
     AND DATE_ADD(CURDATE(), INTERVAL 1 YEAR)"
);

if ($birthday_stmt) {

    if (mysqli_stmt_execute($birthday_stmt)) {

        $birthday_result =
            mysqli_stmt_get_result($birthday_stmt);

        if ($birthday_result) {

            $birthday_data =
                mysqli_fetch_assoc($birthday_result);

            if ($birthday_data) {

                $upcoming_birthdays =
                    (int)$birthday_data['total'];

            }

        }

    }

}


/* =====================================================
   DONATIONS BY TYPE
===================================================== */

$type_result = false;

$type_stmt = mysqli_prepare(
    $conn,
    "SELECT donation_type,
            COUNT(*) AS count,
            COALESCE(SUM(amount), 0) AS total
     FROM donations
     GROUP BY donation_type
     ORDER BY total DESC"
);

if ($type_stmt) {

    if (mysqli_stmt_execute($type_stmt)) {

        $type_result =
            mysqli_stmt_get_result($type_stmt);

    }

}


/* =====================================================
   DONATION CHART DATA
===================================================== */

$chart_labels = [];
$chart_amounts = [];

$chart_stmt = mysqli_prepare(
    $conn,
    "SELECT donation_type,
            COALESCE(SUM(amount), 0) AS total
     FROM donations
     GROUP BY donation_type
     ORDER BY total DESC"
);

if ($chart_stmt) {

    if (mysqli_stmt_execute($chart_stmt)) {

        $chart_result =
            mysqli_stmt_get_result($chart_stmt);

        if ($chart_result) {

            while ($chart = mysqli_fetch_assoc($chart_result)) {

                $chart_labels[] =
                    (string)$chart['donation_type'];

                $chart_amounts[] =
                    (float)$chart['total'];

            }

        }

    }

}


/* =====================================================
   RECENT DONATIONS
===================================================== */

$recent_result = false;

$recent_stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM donations
     ORDER BY id DESC
     LIMIT 5"
);

if ($recent_stmt) {

    if (mysqli_stmt_execute($recent_stmt)) {

        $recent_result =
            mysqli_stmt_get_result($recent_stmt);

    }

}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Reports - Church Member Management System</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        body {
            background: #f5f6fa;
            font-family: Arial, sans-serif;
            color: #333;
        }

        .report-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px 40px;
        }

        .report-header {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .report-header h2 {
            margin: 0;
            font-weight: 700;
            color: #222;
        }

        .report-header p {
            margin: 8px 0 0;
            color: #777;
        }

        /* SUMMARY CARDS */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .summary-card h6 {
            color: #777;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .summary-card h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #222;
        }

        .summary-card p {
            margin: 7px 0 0;
            font-size: 13px;
            color: #888;
        }

        /* SECTIONS */

        .section-box {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #222;
        }

        /* TABLE */

        .table {
            margin-bottom: 0;
        }

        .table th {
            background: #f1f3f5;
            font-weight: 600;
            white-space: nowrap;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        /* CHART */

        .chart-section {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 350px;
        }

        /* BIRTHDAY */

        .birthday-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .birthday-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .birthday-text {
            color: #777;
            margin-bottom: 15px;
        }

        /* QUICK ACCESS */

        .quick-access {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .quick-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* PRINT HEADER */

        .print-header {
            display: none;
        }

        /* RESPONSIVE */

        @media (max-width: 992px) {

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 576px) {

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .report-container {
                padding: 0 12px 30px;
            }

            .section-box,
            .chart-section,
            .birthday-card,
            .quick-access {
                padding: 18px;
            }

            .table {
                font-size: 13px;
            }

        }


        /* =========================
           PRINT STYLES
        ========================= */

        @media print {

            @page {
                size: A4 portrait;
                margin: 12mm;
            }

            body {
                background: #ffffff !important;
                font-size: 12px;
            }

            .no-print {
                display: none !important;
            }

            .print-header {
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }

            .print-header h1 {
                font-size: 22px;
                margin-bottom: 5px;
            }

            .print-header p {
                margin: 0;
                color: #555;
            }

            .report-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            .report-header {
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 15px;
                margin-bottom: 15px;
            }

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 15px;
            }

            .summary-card {
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 12px;
            }

            .summary-card h3 {
                font-size: 20px;
            }

            .birthday-card {
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 12px;
                margin-bottom: 15px;
            }

            .section-box {
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 15px;
                margin-bottom: 15px;
            }

            .chart-section {
                display: none !important;
            }

            .quick-access {
                display: none !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            table th,
            table td {
                border: 1px solid #999 !important;
                padding: 6px !important;
            }

            .page-break-avoid {
                page-break-inside: avoid;
            }

            a {
                text-decoration: none !important;
                color: #000 !important;
            }

        }

    </style>

</head>

<body>


<!-- =====================================================
     PRINT HEADER
===================================================== -->

<div class="print-header">

    <h1>Church Member Management System</h1>

    <p>Administrative Report</p>

    <p>
        Generated on:
        <?php echo date("d-m-Y"); ?>
    </p>

</div>


<div class="report-container">


    <!-- =================================================
         HEADER
    ================================================= -->

    <div class="report-header no-print">

        <h2>Reports & Statistics</h2>

        <p>
            Overview of church members, donations, events and birthdays.
        </p>

        <button
            type="button"
            class="btn btn-dark mt-3"
            onclick="window.print()"
        >
            🖨 Print Report
        </button>

    </div>


    <!-- =================================================
         SUMMARY CARDS
    ================================================= -->

    <div class="summary-grid">


        <!-- MEMBERS -->

        <div class="summary-card">

            <h6>Total Members</h6>

            <h3>
                <?php echo $total_members; ?>
            </h3>

            <p>Registered church members</p>

        </div>


        <!-- DONATIONS -->

        <div class="summary-card">

            <h6>Total Donations</h6>

            <h3>
                <?php echo $total_donations; ?>
            </h3>

            <p>Number of donation records</p>

        </div>


        <!-- TOTAL AMOUNT -->

        <div class="summary-card">

            <h6>Total Amount</h6>

            <h3>
                ₹<?php echo number_format(
                    (float)$total_amount,
                    2
                ); ?>
            </h3>

            <p>Total recorded donations</p>

        </div>


        <!-- EVENTS -->

        <div class="summary-card">

            <h6>Total Events</h6>

            <h3>
                <?php echo $total_events; ?>
            </h3>

            <p>Recorded church events</p>

        </div>

    </div>


    <!-- =================================================
         BIRTHDAY SUMMARY
    ================================================= -->

    <div class="birthday-card page-break-avoid">

        <div class="section-title">
            🎂 Upcoming Birthdays
        </div>

        <div class="birthday-number">
            <?php echo $upcoming_birthdays; ?>
        </div>

        <div class="birthday-text">
            Members with birthdays within the next 12 months.
        </div>

        <a
            href="../birthdays/view_birthdays.php"
            class="btn btn-primary no-print"
        >
            View Birthday Reminders
        </a>

    </div>


    <!-- =================================================
         DONATION SUMMARY BY TYPE
    ================================================= -->

    <div class="section-box page-break-avoid">

        <div class="section-title">
            Donation Summary by Type
        </div>

        <div class="table-responsive">

            <table class="table table-bordered table-hover">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Donation Type</th>

                        <th>Number of Donations</th>

                        <th>Total Amount</th>

                    </tr>

                </thead>

                <tbody>

                <?php

                $type_count = 1;

                if (
                    $type_result &&
                    mysqli_num_rows($type_result) > 0
                ) {

                    while ($type = mysqli_fetch_assoc($type_result)) {

                ?>

                    <tr>

                        <td>
                            <?php echo $type_count++; ?>
                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                (string)$type['donation_type'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </td>

                        <td>
                            <?php
                            echo (int)$type['count'];
                            ?>
                        </td>

                        <td>

                            ₹<?php

                            echo number_format(
                                (float)$type['total'],
                                2
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
                            colspan="4"
                            class="text-center text-muted"
                        >

                            No donation records found.

                        </td>

                    </tr>

                <?php

                }

                ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =================================================
         DONATION CHART
    ================================================= -->

    <div class="chart-section no-print">

        <div class="section-title">
            Donation Statistics
        </div>

        <div class="chart-container">

            <canvas id="donationChart"></canvas>

        </div>

    </div>


    <!-- =================================================
         RECENT DONATIONS
    ================================================= -->

    <div class="section-box page-break-avoid">

        <div class="section-title">
            Recent Donations
        </div>

        <div class="table-responsive">

            <table class="table table-bordered table-hover">

                <thead>

                    <tr>

                        <th>Donation ID</th>

                        <th>Donor</th>

                        <th>Donation Type</th>

                        <th>Amount</th>

                        <th>Payment Method</th>

                        <th>Date</th>

                    </tr>

                </thead>

                <tbody>

                <?php

                if (
                    $recent_result &&
                    mysqli_num_rows($recent_result) > 0
                ) {

                    while (
                        $recent =
                        mysqli_fetch_assoc($recent_result)
                    ) {

                ?>

                    <tr>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                (string)$recent['donation_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                (string)$recent['donor_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                (string)$recent['donation_type'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </td>

                        <td>

                            ₹<?php

                            echo number_format(
                                (float)$recent['amount'],
                                2
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                (string)$recent['payment_method'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            if (!empty($recent['donation_date'])) {

                                $donation_timestamp =
                                    strtotime(
                                        $recent['donation_date']
                                    );

                                if (
                                    $donation_timestamp !== false
                                ) {

                                    echo date(
                                        "d-m-Y",
                                        $donation_timestamp
                                    );

                                } else {

                                    echo "Not specified";

                                }

                            } else {

                                echo "Not specified";

                            }

                            ?>

                        </td>

                    </tr>

                <?php

                    }

                } else {

                ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-muted"
                        >

                            No recent donations found.

                        </td>

                    </tr>

                <?php

                }

                ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =================================================
         QUICK ACCESS
    ================================================= -->

    <div class="quick-access no-print">

        <div class="section-title">
            Quick Access
        </div>

        <div class="quick-buttons">

            <a
                href="../members/view_members.php"
                class="btn btn-outline-primary"
            >
                👥 Members
            </a>

            <a
                href="../donations/view_donations.php"
                class="btn btn-outline-success"
            >
                💰 Donations
            </a>

            <a
                href="../events/view_events.php"
                class="btn btn-outline-warning"
            >
                📅 Events
            </a>

            <a
                href="../birthdays/view_birthdays.php"
                class="btn btn-outline-danger"
            >
                🎂 Birthdays
            </a>

        </div>

    </div>


</div>


<!-- =====================================================
     CHART SCRIPT
===================================================== -->

<script>

    const donationLabels = <?php

        echo json_encode(
            $chart_labels,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );

    ?>;


    const donationAmounts = <?php

        echo json_encode(
            $chart_amounts,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );

    ?>;


    const chartCanvas =
        document.getElementById('donationChart');


    if (
        chartCanvas &&
        donationLabels.length > 0
    ) {

        new Chart(chartCanvas, {

            type: 'bar',

            data: {

                labels: donationLabels,

                datasets: [{

                    label: 'Donation Amount (₹)',

                    data: donationAmounts,

                    borderWidth: 1

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {
                        display: true
                    }

                },

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {

                            callback: function(value) {

                                return '₹' +
                                    Number(value)
                                    .toLocaleString('en-IN');

                            }

                        }

                    }

                }

            }

        });

    }

</script>


<!-- =====================================================
     BOOTSTRAP
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