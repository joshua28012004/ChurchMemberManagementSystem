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
   MESSAGE
===================================================== */

$message = "";


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
   CHECK EVENT ID
===================================================== */

if (
    !isset($_GET['id']) ||
    !is_string($_GET['id']) ||
    !ctype_digit($_GET['id'])
) {

    header("Location: view_events.php");
    exit();

}


$id = (int)$_GET['id'];


if ($id <= 0) {

    header("Location: view_events.php");
    exit();

}


/* =====================================================
   GET EVENT DETAILS
===================================================== */

$select_stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM events WHERE id = ? LIMIT 1"
);


if (!$select_stmt) {

    header("Location: view_events.php?msg=error");
    exit();

}


mysqli_stmt_bind_param(
    $select_stmt,
    "i",
    $id
);


if (!mysqli_stmt_execute($select_stmt)) {

    mysqli_stmt_close($select_stmt);

    header("Location: view_events.php?msg=error");
    exit();

}


$result = mysqli_stmt_get_result($select_stmt);


if (!$result) {

    mysqli_stmt_close($select_stmt);

    header("Location: view_events.php?msg=error");
    exit();

}


if (mysqli_num_rows($result) === 0) {

    mysqli_stmt_close($select_stmt);

    header("Location: view_events.php?msg=notfound");
    exit();

}


$row = mysqli_fetch_assoc($result);

mysqli_stmt_close($select_stmt);


/* =====================================================
   UPDATE EVENT
===================================================== */

if (isset($_POST['update'])) {


    /* =================================================
       CSRF VALIDATION
    ================================================= */

    $submitted_token = $_POST['csrf_token'] ?? "";

    if (
        !is_string($submitted_token) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {

        $message =
            "Invalid security token. Please refresh the page and try again.";

    } else {


        /* =================================================
           GET FORM DATA
        ================================================= */

        $event_id = trim(
            (string)($_POST['event_id'] ?? "")
        );

        $event_name = trim(
            (string)($_POST['event_name'] ?? "")
        );

        $event_type = trim(
            (string)($_POST['event_type'] ?? "")
        );

        $other_event_type = trim(
            (string)($_POST['other_event_type'] ?? "")
        );

        $event_date = trim(
            (string)($_POST['event_date'] ?? "")
        );

        $event_time = trim(
            (string)($_POST['event_time'] ?? "")
        );

        $venue = trim(
            (string)($_POST['venue'] ?? "")
        );

        $organizer = trim(
            (string)($_POST['organizer'] ?? "")
        );

        $description = trim(
            (string)($_POST['description'] ?? "")
        );


        /* =================================================
           BASIC REQUIRED VALIDATION
        ================================================= */

        if (
            $event_id === "" ||
            $event_name === "" ||
            $event_type === "" ||
            $event_date === ""
        ) {

            $message =
                "Please fill all required fields.";

        }


        /* =================================================
           LENGTH VALIDATION
        ================================================= */

        elseif (
            strlen($event_id) > 100 ||
            strlen($event_name) > 150 ||
            strlen($other_event_type) > 100 ||
            strlen($venue) > 150 ||
            strlen($organizer) > 150 ||
            strlen($description) > 2000
        ) {

            $message =
                "One or more fields exceed the allowed length.";

        }


        /* =================================================
           EVENT TYPE VALIDATION
        ================================================= */

        elseif (
            !in_array(
                $event_type,
                $allowed_event_types,
                true
            )
        ) {

            $message =
                "Please select a valid event type.";

        }


        /* =================================================
           OTHER EVENT TYPE
        ================================================= */

        elseif ($event_type === "Other") {

            if ($other_event_type === "") {

                $message =
                    "Please enter the event type.";

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

            $message =
                "Please enter a valid event date.";

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

                $message =
                    "Please enter a valid event date.";

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

            $message =
                "Please enter a valid event time.";

        }


        /* =================================================
           UPDATE DATABASE
        ================================================= */

        if ($message === "") {


            $update_stmt = mysqli_prepare(
                $conn,
                "UPDATE events
                 SET
                    event_id = ?,
                    event_name = ?,
                    event_type = ?,
                    event_date = ?,
                    event_time = ?,
                    venue = ?,
                    organizer = ?,
                    description = ?
                 WHERE id = ?"
            );


            if (!$update_stmt) {

                $message =
                    "Unable to update the event. Please try again.";

            } else {


                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssssssssi",
                    $event_id,
                    $event_name,
                    $event_type,
                    $event_date,
                    $event_time,
                    $venue,
                    $organizer,
                    $description,
                    $id
                );


                if (mysqli_stmt_execute($update_stmt)) {

                    mysqli_stmt_close($update_stmt);


                    /* -----------------------------------------
                       REGENERATE CSRF TOKEN
                    ----------------------------------------- */

                    $_SESSION['csrf_token'] =
                        bin2hex(random_bytes(32));


                    echo "<script>

                        alert('Event Updated Successfully!');

                        window.location.href =
                            'view_events.php?msg=updated';

                    </script>";

                    exit();

                } else {

                    mysqli_stmt_close($update_stmt);


                    /*
                     * Do not expose the actual database
                     * error to the user.
                     */

                    $message =
                        "Unable to update the event. Please check the Event ID and try again.";

                }

            }

        }

    }

}


/* =====================================================
   FORM VALUES
   Preserve submitted values after validation errors.
===================================================== */

$form_event_id =
    isset($_POST['update'])
        ? ($_POST['event_id'] ?? '')
        : ($row['event_id'] ?? '');

$form_event_name =
    isset($_POST['update'])
        ? ($_POST['event_name'] ?? '')
        : ($row['event_name'] ?? '');

$form_event_type =
    isset($_POST['update'])
        ? ($_POST['event_type'] ?? '')
        : ($row['event_type'] ?? '');

$form_other_event_type =
    isset($_POST['update'])
        ? ($_POST['other_event_type'] ?? '')
        : '';

$form_event_date =
    isset($_POST['update'])
        ? ($_POST['event_date'] ?? '')
        : ($row['event_date'] ?? '');

$form_event_time =
    isset($_POST['update'])
        ? ($_POST['event_time'] ?? '')
        : ($row['event_time'] ?? '');

$form_venue =
    isset($_POST['update'])
        ? ($_POST['venue'] ?? '')
        : ($row['venue'] ?? '');

$form_organizer =
    isset($_POST['update'])
        ? ($_POST['organizer'] ?? '')
        : ($row['organizer'] ?? '');

$form_description =
    isset($_POST['update'])
        ? ($_POST['description'] ?? '')
        : ($row['description'] ?? '');


/* =====================================================
   HEADER AND SIDEBAR
===================================================== */

include '../includes/header.php';
include '../includes/sidebar.php';

?>


<style>

/* =========================================
   EDIT EVENT PAGE
========================================= */

.edit-event-page {

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

.edit-event-card {

    max-width: 1000px;

    margin: auto;

    background: #ffffff;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 10px 35px rgba(50, 70, 120, 0.15);

}


/* =========================================
   HEADER
========================================= */

.edit-event-header {

    background: linear-gradient(
        135deg,
        #ffc107,
        #fd7e14
    );

    color: #212529;

    padding: 25px 30px;

}


.edit-event-header h2 {

    margin: 0;

    font-weight: 700;

    font-size: 28px;

}


.edit-event-header p {

    margin: 6px 0 0;

    font-size: 14px;

}


/* =========================================
   BODY
========================================= */

.edit-event-body {

    padding: 35px;

}


/* =========================================
   LABEL
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
   FIELD
========================================= */

.event-field {

    margin-bottom: 22px;

}


/* =========================================
   OTHER EVENT TYPE
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
   UPDATE BUTTON
========================================= */

.update-event-btn {

    background: linear-gradient(
        135deg,
        #0d6efd,
        #6610f2
    );

    border: none;

    color: white;

    padding: 11px 24px;

    border-radius: 9px;

    font-weight: 600;

    transition: all 0.25s ease;

}


.update-event-btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 5px 15px rgba(13,110,253,0.25);

    color: white;

}


/* =========================================
   BACK BUTTON
========================================= */

.back-event-btn {

    background: #6c757d;

    border: none;

    color: white;

    padding: 11px 24px;

    border-radius: 9px;

    font-weight: 600;

    margin-left: 8px;

}


.back-event-btn:hover {

    background: #495057;

    color: white;

}


/* =========================================
   ALERT
========================================= */

.event-alert {

    margin-bottom: 25px;

}


/* =========================================
   REQUIRED
========================================= */

.required {

    color: #dc3545;

}


/* =========================================
   RESPONSIVE
========================================= */

@media(max-width:768px){

    .edit-event-page {

        padding: 20px 10px 40px;

    }


    .edit-event-body {

        padding: 22px;

    }


    .edit-event-header {

        padding: 22px;

    }


    .edit-event-header h2 {

        font-size: 23px;

    }

}

</style>


<!-- =====================================================
     PAGE
===================================================== -->

<div class="content">

<div class="edit-event-page">

<div class="edit-event-card">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="edit-event-header">

<h2>
✏️ Edit Church Event
</h2>

<p>
Update the details of the selected church event
</p>

</div>


<!-- =====================================================
     FORM BODY
===================================================== -->

<div class="edit-event-body">


<?php

if ($message !== '') {

?>

<div class="alert alert-danger event-alert">

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
value="<?php
echo htmlspecialchars(
    $form_event_id,
    ENT_QUOTES,
    'UTF-8'
);
?>"
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
value="<?php
echo htmlspecialchars(
    $form_event_name,
    ENT_QUOTES,
    'UTF-8'
);
?>"
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


<option
value="Prayer Meeting"
<?php
echo ($form_event_type === 'Prayer Meeting')
    ? 'selected'
    : '';
?>>

Prayer Meeting

</option>


<option
value="Sunday Service"
<?php
echo ($form_event_type === 'Sunday Service')
    ? 'selected'
    : '';
?>>

Sunday Service

</option>


<option
value="Youth Fellowship"
<?php
echo ($form_event_type === 'Youth Fellowship')
    ? 'selected'
    : '';
?>>

Youth Fellowship

</option>


<option
value="Bible Study"
<?php
echo ($form_event_type === 'Bible Study')
    ? 'selected'
    : '';
?>>

Bible Study

</option>


<option
value="Christmas"
<?php
echo ($form_event_type === 'Christmas')
    ? 'selected'
    : '';
?>>

Christmas

</option>


<option
value="Easter"
<?php
echo ($form_event_type === 'Easter')
    ? 'selected'
    : '';
?>>

Easter

</option>


<option
value="Wedding"
<?php
echo ($form_event_type === 'Wedding')
    ? 'selected'
    : '';
?>>

Wedding

</option>


<option
value="Other"
<?php
echo (
    !in_array(
        $form_event_type,
        [
            "Prayer Meeting",
            "Sunday Service",
            "Youth Fellowship",
            "Bible Study",
            "Christmas",
            "Easter",
            "Wedding",
            "Other"
        ],
        true
    ) ||
    $form_event_type === "Other"
)
    ? 'selected'
    : '';
?>>

Other

</option>


</select>


<!-- OTHER EVENT TYPE -->

<div
id="other_event_box"
style="display:none;">


<label class="event-label">

✏️ Enter Event Type

</label>


<input
type="text"
name="other_event_type"
id="other_event_type"
class="event-input"
placeholder="Enter your event type"
value="<?php
echo htmlspecialchars(
    $form_other_event_type,
    ENT_QUOTES,
    'UTF-8'
);
?>"
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
value="<?php
echo htmlspecialchars(
    $form_event_date,
    ENT_QUOTES,
    'UTF-8'
);
?>"
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
value="<?php
echo htmlspecialchars(
    $form_event_time,
    ENT_QUOTES,
    'UTF-8'
);
?>">


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
value="<?php
echo htmlspecialchars(
    $form_venue,
    ENT_QUOTES,
    'UTF-8'
);
?>"
maxlength="150"
placeholder="Enter event venue">


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
value="<?php
echo htmlspecialchars(
    $form_organizer,
    ENT_QUOTES,
    'UTF-8'
);
?>"
maxlength="150"
placeholder="Enter organizer name">


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
placeholder="Enter event description..."><?php
echo htmlspecialchars(
    $form_description,
    ENT_QUOTES,
    'UTF-8'
);
?></textarea>


</div>

</div>


</div>


<!-- =================================================
     BUTTONS
===================================================== -->

<div class="event-buttons">


<button
type="submit"
name="update"
class="update-event-btn">

💾 Update Event

</button>


<a
href="view_events.php"
class="back-event-btn">

↩️ Back to Events

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
   SET CURRENT EVENT TYPE
===================================================== */

let currentEventType =
<?php echo json_encode($form_event_type); ?>;


let standardTypes = [

    "Prayer Meeting",
    "Sunday Service",
    "Youth Fellowship",
    "Bible Study",
    "Christmas",
    "Easter",
    "Wedding"

];


let eventTypeSelect =
document.getElementById("event_type");


let otherBox =
document.getElementById("other_event_box");


let otherInput =
document.getElementById("other_event_type");


if (standardTypes.includes(currentEventType)) {

    eventTypeSelect.value =
        currentEventType;

} else {

    eventTypeSelect.value =
        "Other";

    otherBox.style.display =
        "block";

    otherInput.required =
        true;

    if (otherInput.value === "") {

        otherInput.value =
            currentEventType;

    }

}


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