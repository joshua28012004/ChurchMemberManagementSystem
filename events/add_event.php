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
   MESSAGE VARIABLES
===================================================== */

$message = "";
$message_type = "danger";


/* =====================================================
   ALLOWED EVENT TYPES
===================================================== */

$allowed_event_types = [
    "Prayer Meeting",
    "Sunday Service",
    "Youth Fellowship",
    "Bible Study",
    "Christmas",
    "Easter",
    "Wedding",
    "Other"
];


/* =====================================================
   SAVE EVENT
===================================================== */

if (isset($_POST['save'])) {


    /* =================================================
       CSRF VALIDATION
    ================================================= */

    $submitted_token = $_POST['csrf_token'] ?? "";

    if (
        !is_string($submitted_token) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {

        $message = "Invalid security token. Please refresh the page and try again.";
        $message_type = "danger";

    } else {


        /* =================================================
           GET FORM DATA
        ================================================= */

        $event_id = trim((string)($_POST['event_id'] ?? ""));
        $event_name = trim((string)($_POST['event_name'] ?? ""));
        $event_type = trim((string)($_POST['event_type'] ?? ""));
        $other_event_type = trim((string)($_POST['other_event_type'] ?? ""));
        $event_date = trim((string)($_POST['event_date'] ?? ""));
        $event_time = trim((string)($_POST['event_time'] ?? ""));
        $venue = trim((string)($_POST['venue'] ?? ""));
        $organizer = trim((string)($_POST['organizer'] ?? ""));
        $description = trim((string)($_POST['description'] ?? ""));


        /* =================================================
           BASIC REQUIRED FIELD VALIDATION
        ================================================= */

        if (
            $event_id === "" ||
            $event_name === "" ||
            $event_type === "" ||
            $event_date === ""
        ) {

            $message = "Please fill all required fields.";
            $message_type = "danger";

        }

        /* =================================================
           LENGTH VALIDATION
        ================================================= */

        elseif (
            strlen($event_id) > 100 ||
            strlen($event_name) > 150 ||
            strlen($venue) > 150 ||
            strlen($organizer) > 150 ||
            strlen($description) > 2000 ||
            strlen($other_event_type) > 100
        ) {

            $message = "One or more fields exceed the allowed length.";
            $message_type = "danger";

        }

        /* =================================================
           EVENT TYPE VALIDATION
        ================================================= */

        elseif (!in_array($event_type, $allowed_event_types, true)) {

            $message = "Please select a valid event type.";
            $message_type = "danger";

        }

        /* =================================================
           OTHER EVENT TYPE
        ================================================= */

        elseif ($event_type === "Other") {

            if ($other_event_type === "") {

                $message = "Please enter the event type.";
                $message_type = "danger";

            } else {

                $event_type = $other_event_type;

            }

        }

        /* =================================================
           DATE VALIDATION
        ================================================= */

        elseif (
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $event_date
            )
        ) {

            $message = "Please enter a valid event date.";
            $message_type = "danger";

        } else {

            $date_object = DateTime::createFromFormat(
                'Y-m-d',
                $event_date
            );

            $date_errors = DateTime::getLastErrors();

            $date_is_valid =
                $date_object !== false &&
                (
                    $date_errors === false ||
                    (
                        $date_errors['warning_count'] === 0 &&
                        $date_errors['error_count'] === 0
                    )
                ) &&
                $date_object->format('Y-m-d') === $event_date;


            if (!$date_is_valid) {

                $message = "Please enter a valid event date.";
                $message_type = "danger";

            }

        }


        /* =================================================
           TIME VALIDATION
        ================================================= */

        if (
            $message === "" &&
            $event_time !== "" &&
            !preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $event_time
            )
        ) {

            $message = "Please enter a valid event time.";
            $message_type = "danger";

        }


        /* =================================================
           CHECK DUPLICATE EVENT ID
        ================================================= */

        if ($message === "") {

            $check_stmt = mysqli_prepare(
                $conn,
                "SELECT id FROM events WHERE event_id = ? LIMIT 1"
            );

            if ($check_stmt) {

                mysqli_stmt_bind_param($check_stmt, "s", $event_id);

                if (mysqli_stmt_execute($check_stmt)) {

                    $check_res = mysqli_stmt_get_result($check_stmt);

                    if ($check_res && mysqli_num_rows($check_res) > 0) {

                        $message = "An event with this Event ID already exists. Please choose a unique Event ID.";
                        $message_type = "danger";

                    }

                }

                mysqli_stmt_close($check_stmt);

            }

        }


        /* =================================================
           DATABASE INSERT
        ================================================= */

        if ($message === "") {


            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO events
                (
                    event_id,
                    event_name,
                    event_type,
                    event_date,
                    event_time,
                    venue,
                    organizer,
                    description
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );


            if (!$insert_stmt) {

                $message = "Unable to save the event. Please try again.";
                $message_type = "danger";

            } else {


                mysqli_stmt_bind_param(
                    $insert_stmt,
                    "ssssssss",
                    $event_id,
                    $event_name,
                    $event_type,
                    $event_date,
                    $event_time,
                    $venue,
                    $organizer,
                    $description
                );


                if (mysqli_stmt_execute($insert_stmt)) {

                    mysqli_stmt_close($insert_stmt);

                    /* -----------------------------------------
                       REGENERATE CSRF TOKEN
                    ----------------------------------------- */

                    $_SESSION['csrf_token'] =
                        bin2hex(random_bytes(32));


                    header("Location: view_events.php?msg=added");
                    exit();

                } else {

                    mysqli_stmt_close($insert_stmt);

                    /*
                     * Do not expose the actual database error
                     * to the user.
                     */

                    $message =
                        "Unable to add the event. Please check the Event ID and try again.";

                    $message_type = "danger";

                }

            }

        }

    }

}


/* =====================================================
   HEADER AND SIDEBAR
===================================================== */

include '../includes/header.php';
include '../includes/sidebar.php';

?>


<style>

/* =========================================
   ADD EVENT PAGE
========================================= */

.add-event-page {

    background: linear-gradient(
        135deg,
        #eef5ff,
        #f7f1ff
    );

    min-height: calc(100vh - 70px);

    padding: 30px 15px 50px;

}


/* =========================================
   MAIN CARD
========================================= */

.add-event-card {

    max-width: 1000px;

    margin: auto;

    background: #ffffff;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 10px 35px rgba(50, 70, 120, 0.15);

}


/* =========================================
   CARD HEADER
========================================= */

.add-event-header {

    background: linear-gradient(
        135deg,
        #0d6efd,
        #6f42c1
    );

    color: white;

    padding: 25px 30px;

    position: relative;

}


.add-event-header h2 {

    margin: 0;

    font-weight: 700;

    font-size: 28px;

}


.add-event-header p {

    margin: 6px 0 0;

    opacity: 0.9;

    font-size: 14px;

}


/* =========================================
   DECORATIVE CIRCLE
========================================= */

.add-event-header::after {

    content: "";

    position: absolute;

    width: 130px;

    height: 130px;

    border-radius: 50%;

    background: rgba(255,255,255,0.08);

    right: 30px;

    top: -45px;

}


/* =========================================
   FORM BODY
========================================= */

.add-event-body {

    padding: 35px;

}


/* =========================================
   FIELD LABEL
========================================= */

.event-label {

    font-weight: 600;

    color: #343a40;

    margin-bottom: 8px;

    display: block;

}


/* =========================================
   INPUTS
========================================= */

.event-input,
.event-select,
.event-textarea {

    width: 100%;

    border: 1px solid #d9dee7;

    border-radius: 10px;

    padding: 11px 14px;

    font-size: 15px;

    background: #fafbfe;

    transition: all 0.25s ease;

}


.event-input:hover,
.event-select:hover,
.event-textarea:hover {

    border-color: #9bbcf7;

}


.event-input:focus,
.event-select:focus,
.event-textarea:focus {

    outline: none;

    border-color: #0d6efd;

    background: #ffffff;

    box-shadow:
        0 0 0 3px rgba(13,110,253,0.12);

}


/* =========================================
   TEXTAREA
========================================= */

.event-textarea {

    resize: vertical;

    min-height: 120px;

}


/* =========================================
   FIELD BOX
========================================= */

.event-field {

    margin-bottom: 22px;

}


/* =========================================
   OTHER EVENT BOX
========================================= */

#other_event_box {

    background: #f4f0ff;

    border: 1px solid #d9c9ff;

    padding: 15px;

    border-radius: 10px;

    margin-top: 10px;

}


/* =========================================
   BUTTON AREA
========================================= */

.event-buttons {

    margin-top: 10px;

    padding-top: 25px;

    border-top: 1px solid #eeeeee;

}


/* =========================================
   SAVE BUTTON
========================================= */

.save-event-btn {

    background: linear-gradient(
        135deg,
        #198754,
        #20c997
    );

    border: none;

    color: white;

    padding: 11px 24px;

    border-radius: 9px;

    font-weight: 600;

    transition: all 0.25s ease;

}


.save-event-btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 5px 15px rgba(25,135,84,0.25);

    color: white;

}


/* =========================================
   VIEW EVENTS BUTTON
========================================= */

.view-events-btn {

    background: #6c757d;

    border: none;

    color: white;

    padding: 11px 24px;

    border-radius: 9px;

    font-weight: 600;

    margin-left: 8px;

    transition: all 0.25s ease;

}


.view-events-btn:hover {

    background: #495057;

    transform: translateY(-2px);

    color: white;

}


/* =========================================
   ALERT
========================================= */

.event-alert {

    max-width: 1000px;

    margin: 0 auto 20px;

}


/* =========================================
   REQUIRED STAR
========================================= */

.required {

    color: #dc3545;

}


/* =========================================
   RESPONSIVE
========================================= */

@media(max-width:768px){

    .add-event-page {

        padding: 20px 10px 40px;

    }


    .add-event-body {

        padding: 22px;

    }


    .add-event-header {

        padding: 22px;

    }


    .add-event-header h2 {

        font-size: 23px;

    }


    .view-events-btn {

        margin-left: 5px;

    }

}

</style>


<!-- =====================================================
     PAGE
===================================================== -->

<div class="content">


<div class="add-event-page">


<?php if ($message !== ""): ?>

<div class="alert alert-<?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?> event-alert">

    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>

</div>

<?php endif; ?>


<div class="add-event-card">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="add-event-header">

<h2>

📅 Add Church Event

</h2>


<p>

Create and schedule a new church event

</p>

</div>


<!-- =====================================================
     FORM BODY
===================================================== -->

<div class="add-event-body">


<form method="POST">


<input
type="hidden"
name="csrf_token"
value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">


<div class="row">


<!-- =================================================
     EVENT ID
================================================= -->

<div class="col-md-6">

<div class="event-field">


<label class="event-label">

🆔 Event ID

<span class="required">*</span>

</label>


<input
type="text"
name="event_id"
class="event-input"
placeholder="Enter event ID"
value="<?php echo htmlspecialchars($_POST['event_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
maxlength="100"
required>


</div>

</div>


<!-- =================================================
     EVENT NAME
================================================= -->

<div class="col-md-6">

<div class="event-field">


<label class="event-label">

📌 Event Name

<span class="required">*</span>

</label>


<input
type="text"
name="event_name"
class="event-input"
placeholder="Enter event name"
value="<?php echo htmlspecialchars($_POST['event_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
maxlength="150"
required>


</div>

</div>


<!-- =================================================
     EVENT TYPE
================================================= -->

<div class="col-md-6">

<div class="event-field">


<label class="event-label">

🗂️ Event Type

<span class="required">*</span>

</label>


<select
name="event_type"
id="event_type"
class="event-select"
required>


<option value="">

Select Event Type

</option>


<option value="Prayer Meeting"
<?php echo (($_POST['event_type'] ?? '') === 'Prayer Meeting') ? 'selected' : ''; ?>>

Prayer Meeting

</option>


<option value="Sunday Service"
<?php echo (($_POST['event_type'] ?? '') === 'Sunday Service') ? 'selected' : ''; ?>>

Sunday Service

</option>


<option value="Youth Fellowship"
<?php echo (($_POST['event_type'] ?? '') === 'Youth Fellowship') ? 'selected' : ''; ?>>

Youth Fellowship

</option>


<option value="Bible Study"
<?php echo (($_POST['event_type'] ?? '') === 'Bible Study') ? 'selected' : ''; ?>>

Bible Study

</option>


<option value="Christmas"
<?php echo (($_POST['event_type'] ?? '') === 'Christmas') ? 'selected' : ''; ?>>

Christmas

</option>


<option value="Easter"
<?php echo (($_POST['event_type'] ?? '') === 'Easter') ? 'selected' : ''; ?>>

Easter

</option>


<option value="Wedding"
<?php echo (($_POST['event_type'] ?? '') === 'Wedding') ? 'selected' : ''; ?>>

Wedding

</option>


<option value="Other"
<?php echo (($_POST['event_type'] ?? '') === 'Other') ? 'selected' : ''; ?>>

Other

</option>


</select>


<!-- =================================================
     OTHER EVENT TYPE
================================================= -->

<div
id="other_event_box"
style="display:<?php echo (($_POST['event_type'] ?? '') === 'Other') ? 'block' : 'none'; ?>;">


<label class="event-label">

✏️ Enter Event Type

</label>


<input
type="text"
name="other_event_type"
id="other_event_type"
class="event-input"
placeholder="Enter your event type"
value="<?php echo htmlspecialchars($_POST['other_event_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
maxlength="100">


</div>


</div>

</div>


<!-- =================================================
     DATE
================================================= -->

<div class="col-md-6">

<div class="event-field">


<label class="event-label">

📅 Date

<span class="required">*</span>

</label>


<input
type="date"
name="event_date"
class="event-input"
value="<?php echo htmlspecialchars($_POST['event_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
required>


</div>

</div>


<!-- =================================================
     TIME
================================================= -->

<div class="col-md-6">

<div class="event-field">


<label class="event-label">

⏰ Time

</label>


<input
type="time"
name="event_time"
class="event-input"
value="<?php echo htmlspecialchars($_POST['event_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">


</div>

</div>


<!-- =================================================
     VENUE
================================================= -->

<div class="col-md-6">

<div class="event-field">


<label class="event-label">

📍 Venue

</label>


<input
type="text"
name="venue"
class="event-input"
placeholder="Enter event venue"
value="<?php echo htmlspecialchars($_POST['venue'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
maxlength="150">


</div>

</div>


<!-- =================================================
     ORGANIZER
================================================= -->

<div class="col-md-6">

<div class="event-field">


<label class="event-label">

👤 Organizer

</label>


<input
type="text"
name="organizer"
class="event-input"
placeholder="Enter organizer name"
value="<?php echo htmlspecialchars($_POST['organizer'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
maxlength="150">


</div>

</div>


<!-- =================================================
     DESCRIPTION
================================================= -->

<div class="col-md-12">

<div class="event-field">


<label class="event-label">

📝 Description

</label>


<textarea
name="description"
class="event-textarea"
rows="4"
maxlength="2000"
placeholder="Enter event description..."><?php echo htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>


</div>

</div>


</div>


<!-- =================================================
     BUTTONS
================================================= -->

<div class="event-buttons">


<button
type="submit"
name="save"
class="save-event-btn">

💾 Save Event

</button>


<a
href="view_events.php"
class="view-events-btn">

📋 View Events

</a>


</div>


</form>


</div>

</div>

</div>

</div>


<!-- =====================================================
     OTHER EVENT TYPE SCRIPT
===================================================== -->

<script>

document
.getElementById("event_type")
.addEventListener("change", function(){

    let otherBox =
        document.getElementById("other_event_box");

    let otherInput =
        document.getElementById("other_event_type");


    if(this.value === "Other"){

        otherBox.style.display = "block";

        otherInput.required = true;

    }else{

        otherBox.style.display = "none";

        otherInput.required = false;

        otherInput.value = "";

    }

});


/* =====================================================
   BROWSER BACK-FORWARD CACHE PROTECTION
===================================================== */

window.addEventListener("pageshow", function(event) {

    if (event.persisted) {

        window.location.reload();

    }

});

</script>


</body>

</html>