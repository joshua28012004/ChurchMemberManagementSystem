<?php

session_start();

/* =====================================================
   SECURITY HEADERS
===================================================== */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");


/* =====================================================
   ADMIN AUTHENTICATION
===================================================== */

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}


/* =====================================================
   DATABASE
===================================================== */

include '../includes/db.php';


$message = "";
$message_type = "success";


/* =====================================================
   GENERATE NEXT MEMBER ID
===================================================== */

$next_member_id = "CH001";

$result = mysqli_query(
    $conn,
    "SELECT member_id
     FROM members
     WHERE member_id LIKE 'CH%'
     ORDER BY CAST(SUBSTRING(member_id, 3) AS UNSIGNED) DESC
     LIMIT 1"
);

if ($result && mysqli_num_rows($result) > 0) {

    $row = mysqli_fetch_assoc($result);

    $last_id = $row['member_id'];

    $number = intval(substr($last_id, 2));

    $next_member_id =
        "CH" .
        str_pad(
            $number + 1,
            3,
            "0",
            STR_PAD_LEFT
        );
}


/* =====================================================
   SAVE MEMBER
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save'])) {

    $member_id = $next_member_id;

    $fullname = trim($_POST['fullname'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $baptism_date = trim($_POST['baptism_date'] ?? '');
    $marital_status = trim($_POST['marital_status'] ?? '');

    $photo = "";


    /* =================================================
       SERVER-SIDE VALIDATION
    ================================================= */

    if ($fullname === '' || $gender === '' || $dob === '') {

        $message = "Please fill all required fields.";
        $message_type = "danger";

    } elseif (!in_array($gender, ['Male', 'Female'], true)) {

        $message = "Invalid gender selected.";
        $message_type = "danger";

    } elseif (
        $marital_status !== '' &&
        !in_array($marital_status, ['Single', 'Married'], true)
    ) {

        $message = "Invalid marital status selected.";
        $message_type = "danger";

    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "danger";

    } elseif (strlen($fullname) > 100) {

        $message = "Full name is too long.";
        $message_type = "danger";

    } elseif (strlen($phone) > 20) {

        $message = "Phone number is too long.";
        $message_type = "danger";

    } elseif (strlen($address) > 500) {

        $message = "Address is too long.";
        $message_type = "danger";

    } else {


        /* =============================================
           DATE VALIDATION
        ============================================= */

        $dob_valid = DateTime::createFromFormat('Y-m-d', $dob);

        if (
            !$dob_valid ||
            $dob_valid->format('Y-m-d') !== $dob
        ) {

            $message = "Invalid date of birth.";
            $message_type = "danger";

        } else {

            /* Prevent future date of birth */

            $today = new DateTime();

            if ($dob_valid > $today) {

                $message =
                    "Date of birth cannot be in the future.";

                $message_type = "danger";
            }
        }


        /* =============================================
           BAPTISM DATE VALIDATION
        ============================================= */

        if ($message === "" && $baptism_date !== '') {

            $baptism_valid =
                DateTime::createFromFormat(
                    'Y-m-d',
                    $baptism_date
                );

            if (
                !$baptism_valid ||
                $baptism_valid->format('Y-m-d') !== $baptism_date
            ) {

                $message =
                    "Invalid baptism date.";

                $message_type = "danger";

            } elseif ($baptism_valid > new DateTime()) {

                $message =
                    "Baptism date cannot be in the future.";

                $message_type = "danger";

            } elseif ($baptism_valid < $dob_valid) {

                $message =
                    "Baptism date cannot be before date of birth.";

                $message_type = "danger";
            }
        }


        /* =============================================
           PHOTO UPLOAD
        ============================================= */

        if (
            $message === "" &&
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {

                $message =
                    "There was a problem uploading the photo.";

                $message_type = "danger";

            } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {

                /*
                 * Maximum photo size = 2 MB
                 */

                $message =
                    "Photo size must be 2 MB or less.";

                $message_type = "danger";

            } else {

                /*
                 * Validate actual MIME type from file contents.
                 */

                $allowed_types = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                $file_type = mime_content_type(
                    $_FILES['photo']['tmp_name']
                );


                if (!isset($allowed_types[$file_type])) {

                    $message =
                        "Invalid photo format. Please upload JPG, PNG or WEBP.";

                    $message_type = "danger";

                } else {

                    /*
                     * Verify that PHP recognizes the file
                     * as an actual image.
                     */

                    $image_info = @getimagesize(
                        $_FILES['photo']['tmp_name']
                    );

                    if ($image_info === false) {

                        $message =
                            "The uploaded file is not a valid image.";

                        $message_type = "danger";

                    } else {

                        $extension =
                            $allowed_types[$file_type];


                        /*
                         * Generate a random filename.
                         * Do not use the original filename.
                         */

                        try {

                            $random_name =
                                bin2hex(
                                    random_bytes(16)
                                );

                        } catch (Exception $e) {

                            $random_name =
                                uniqid('', true);
                        }


                        $photo =
                            $random_name .
                            "." .
                            $extension;


                        $upload_directory =
                            "../uploads/";


                        /*
                         * Create uploads folder if it
                         * does not already exist.
                         */

                        if (!is_dir($upload_directory)) {

                            mkdir(
                                $upload_directory,
                                0755,
                                true
                            );
                        }


                        $upload_path =
                            $upload_directory .
                            $photo;


                        if (
                            !move_uploaded_file(
                                $_FILES['photo']['tmp_name'],
                                $upload_path
                            )
                        ) {

                            $photo = "";

                            $message =
                                "Photo upload failed.";

                            $message_type = "danger";
                        }
                    }
                }
            }
        }


        /* =============================================
           INSERT MEMBER USING PREPARED STATEMENT
        ============================================= */

        if ($message === "") {

            $sql = "INSERT INTO members
            (
                member_id,
                fullname,
                gender,
                dob,
                phone,
                email,
                address,
                baptism_date,
                marital_status,
                photo
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
                ?,
                ?
            )";


            $stmt = mysqli_prepare(
                $conn,
                $sql
            );


            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssssss",
                    $member_id,
                    $fullname,
                    $gender,
                    $dob,
                    $phone,
                    $email,
                    $address,
                    $baptism_date,
                    $marital_status,
                    $photo
                );


                if (mysqli_stmt_execute($stmt)) {

                    $message =
                        "Member Added Successfully! Member ID: "
                        . $member_id;

                    $message_type = "success";


                    /*
                     * Generate next ID for display.
                     */

                    $number =
                        intval(
                            substr($member_id, 2)
                        );

                    $next_member_id =
                        "CH" .
                        str_pad(
                            $number + 1,
                            3,
                            "0",
                            STR_PAD_LEFT
                        );

                } else {

                    /*
                     * Delete uploaded photo if
                     * database insertion fails.
                     */

                    if (
                        $photo !== "" &&
                        file_exists(
                            "../uploads/" . $photo
                        )
                    ) {

                        unlink(
                            "../uploads/" . $photo
                        );
                    }


                    $message =
                        "Unable to add member. Please try again.";

                    $message_type = "danger";
                }


                mysqli_stmt_close($stmt);

            } else {

                /*
                 * Delete uploaded photo if
                 * statement preparation fails.
                 */

                if (
                    $photo !== "" &&
                    file_exists(
                        "../uploads/" . $photo
                    )
                ) {

                    unlink(
                        "../uploads/" . $photo
                    );
                }


                $message =
                    "Unable to process member information.";

                $message_type = "danger";
            }
        }
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

<title>Add Member</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<style>

body {
    background: #f5f6fa;
}

.member-form {
    max-width: 900px;
    margin: auto;
}

.card {
    border: none;
    border-radius: 12px;
}

.card-header {
    border-radius: 12px 12px 0 0 !important;
}

</style>

</head>


<body>


<div class="container mt-4 member-form">


<div class="card shadow">


<div class="card-header bg-primary text-white">

<h3 class="mb-0">
👤 Add Church Member
</h3>

</div>


<div class="card-body p-4">


<?php if ($message !== "") { ?>

<div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">

<?php echo htmlspecialchars($message); ?>

</div>

<?php } ?>


<form
method="POST"
enctype="multipart/form-data">


<div class="row">


<!-- MEMBER ID -->

<div class="col-md-6 mb-3">

<label class="form-label">
Member ID
</label>

<input
type="text"
class="form-control"
value="<?php echo htmlspecialchars($next_member_id); ?>"
readonly>

</div>


<!-- FULL NAME -->

<div class="col-md-6 mb-3">

<label class="form-label">
Full Name <span class="text-danger">*</span>
</label>

<input
type="text"
name="fullname"
class="form-control"
required
maxlength="100">

</div>


<!-- GENDER -->

<div class="col-md-6 mb-3">

<label class="form-label">
Gender <span class="text-danger">*</span>
</label>

<select
name="gender"
class="form-select"
required>

<option value="">
Select Gender
</option>

<option value="Male">
Male
</option>

<option value="Female">
Female
</option>

</select>

</div>


<!-- DOB -->

<div class="col-md-6 mb-3">

<label class="form-label">
Date of Birth <span class="text-danger">*</span>
</label>

<input
type="date"
name="dob"
class="form-control"
required>

</div>


<!-- PHONE -->

<div class="col-md-6 mb-3">

<label class="form-label">
Phone
</label>

<input
type="text"
name="phone"
class="form-control"
maxlength="20">

</div>


<!-- EMAIL -->

<div class="col-md-6 mb-3">

<label class="form-label">
Email
</label>

<input
type="email"
name="email"
class="form-control"
maxlength="150">

</div>


<!-- ADDRESS -->

<div class="col-md-12 mb-3">

<label class="form-label">
Address
</label>

<textarea
name="address"
class="form-control"
rows="3"
maxlength="500"></textarea>

</div>


<!-- BAPTISM DATE -->

<div class="col-md-6 mb-3">

<label class="form-label">
Baptism Date
</label>

<input
type="date"
name="baptism_date"
class="form-control">

</div>


<!-- MARITAL STATUS -->

<div class="col-md-6 mb-3">

<label class="form-label">
Marital Status
</label>

<select
name="marital_status"
class="form-select"
required>

<option value="Single">
Single
</option>

<option value="Married">
Married
</option>

</select>

</div>


<!-- PHOTO -->

<div class="col-md-6 mb-3">

<label class="form-label">
Member Photo
</label>

<input
type="file"
name="photo"
class="form-control"
accept="image/jpeg,image/png,image/webp">

<small class="text-muted">
JPG, PNG or WEBP only. Maximum 2 MB.
</small>

</div>


</div>


<div class="mt-3">


<button
type="submit"
name="save"
class="btn btn-primary">

💾 Add Member

</button>


<a
href="view_members.php"
class="btn btn-success">

📋 View Members

</a>


</div>


</form>


</div>

</div>

</div>


<script>

window.addEventListener("pageshow", function(event) {

    if (event.persisted) {

        window.location.reload();

    }

});

</script>


</body>

</html>