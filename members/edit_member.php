<?php
session_start();

/* =====================================================
   SECURITY HEADERS
===================================================== */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');


/* =====================================================
   ADMIN AUTHENTICATION
===================================================== */

if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
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
   CHECK MEMBER ID
===================================================== */

if (
    !isset($_GET['id']) ||
    !ctype_digit((string)$_GET['id']) ||
    (int)$_GET['id'] <= 0
) {
    header('Location: view_members.php');
    exit();
}

$id = (int)$_GET['id'];


/* =====================================================
   FETCH MEMBER
===================================================== */

$stmt = mysqli_prepare(
    $conn,
    'SELECT * FROM members WHERE id = ? LIMIT 1'
);

if (!$stmt) {
    header('Location: view_members.php');
    exit();
}

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = $result ? mysqli_fetch_assoc($result) : null;

mysqli_stmt_close($stmt);

if (!$row) {
    header('Location: view_members.php');
    exit();
}


/* =====================================================
   DEFAULT VALUES
===================================================== */

$message = '';
$message_type = 'danger';

$member_id = $row['member_id'];
$fullname = $row['fullname'];
$gender = $row['gender'];
$dob = $row['dob'];
$phone = $row['phone'];
$email = $row['email'];
$address = $row['address'];
$baptism_date = $row['baptism_date'];
$marital_status = $row['marital_status'];

$old_photo = $row['photo'];
$photo = $old_photo;

$new_photo_uploaded = false;


/* =====================================================
   UPDATE MEMBER
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update'])
) {

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($submitted_token) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {

        $message = 'Invalid security token. Please try again.';
        $message_type = 'danger';

    } else {

    $member_id = trim($_POST['member_id'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $baptism_date = trim($_POST['baptism_date'] ?? '');
    $marital_status = trim($_POST['marital_status'] ?? '');


    /* =================================================
       BASIC VALIDATION
    ================================================= */

    if (
        $member_id === '' ||
        $fullname === '' ||
        $gender === '' ||
        $dob === ''
    ) {

        $message = 'Please fill all required fields.';

    } elseif (strlen($member_id) > 50) {

        $message = 'Member ID is too long.';

    } elseif (strlen($fullname) > 100) {

        $message = 'Full name is too long.';

    } elseif (strlen($phone) > 20) {

        $message = 'Phone number is too long.';

    } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {

        $message = 'Please enter a valid phone number (digits, spaces, +, -, and parentheses only).';

    } elseif ($phone !== '' && preg_match_all('/[0-9]/', $phone) < 7) {

        $message = 'Phone number must contain at least 7 digits.';

    } elseif (strlen($email) > 150) {

        $message = 'Email address is too long.';

    } elseif (strlen($address) > 500) {

        $message = 'Address is too long.';

    } elseif (
        !in_array(
            $gender,
            ['Male', 'Female'],
            true
        )
    ) {

        $message = 'Invalid gender selected.';

    } elseif (
        !in_array(
            $marital_status,
            ['Single', 'Married'],
            true
        )
    ) {

        $message = 'Invalid marital status selected.';

    } elseif (
        $email !== '' &&
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $message = 'Please enter a valid email address.';
    }


    /* =================================================
       DATE OF BIRTH VALIDATION
    ================================================= */

    $dob_valid = null;

    if ($message === '') {

        $dob_valid = DateTime::createFromFormat(
            '!Y-m-d',
            $dob
        );

        $errors = DateTime::getLastErrors();

        if (
            $dob_valid === false ||
            (
                $errors !== false &&
                (
                    $errors['warning_count'] > 0 ||
                    $errors['error_count'] > 0
                )
            ) ||
            $dob_valid->format('Y-m-d') !== $dob
        ) {

            $message = 'Invalid date of birth.';

        } elseif (
            $dob_valid > new DateTime('today')
        ) {

            $message = 'Date of birth cannot be in the future.';
        }
    }


    /* =================================================
       BAPTISM DATE VALIDATION
    ================================================= */

    if (
        $message === '' &&
        $baptism_date !== ''
    ) {

        $baptism_valid = DateTime::createFromFormat(
            '!Y-m-d',
            $baptism_date
        );

        $errors = DateTime::getLastErrors();

        if (
            $baptism_valid === false ||
            (
                $errors !== false &&
                (
                    $errors['warning_count'] > 0 ||
                    $errors['error_count'] > 0
                )
            ) ||
            $baptism_valid->format('Y-m-d') !== $baptism_date
        ) {

            $message = 'Invalid baptism date.';

        } elseif (
            $baptism_valid > new DateTime('today')
        ) {

            $message = 'Baptism date cannot be in the future.';

        } elseif (
            $dob_valid !== null &&
            $baptism_valid < $dob_valid
        ) {

            $message = 'Baptism date cannot be before date of birth.';
        }
    }


    /* =================================================
       DUPLICATE MEMBER ID
    ================================================= */

    if ($message === '') {

        $duplicate_stmt = mysqli_prepare(
            $conn,
            'SELECT id
             FROM members
             WHERE member_id = ?
             AND id != ?
             LIMIT 1'
        );

        if (!$duplicate_stmt) {

            $message = 'Unable to validate Member ID.';

        } else {

            mysqli_stmt_bind_param(
                $duplicate_stmt,
                'si',
                $member_id,
                $id
            );

            mysqli_stmt_execute($duplicate_stmt);

            $duplicate_result =
                mysqli_stmt_get_result($duplicate_stmt);

            if (
                $duplicate_result &&
                mysqli_num_rows($duplicate_result) > 0
            ) {

                $message = 'This Member ID already exists.';
            }

            mysqli_stmt_close($duplicate_stmt);
        }
    }


    /* =================================================
       PHOTO UPLOAD
       MAXIMUM = 2 MB
    ================================================= */

    if (
        $message === '' &&
        isset($_FILES['photo'])
    ) {

        $upload_error = (int)(
            $_FILES['photo']['error']
            ?? UPLOAD_ERR_NO_FILE
        );


        /* ---------------------------------------------
           NO NEW PHOTO
        --------------------------------------------- */

        if (
            $upload_error === UPLOAD_ERR_NO_FILE
        ) {

            /* Keep existing photo */
        }


        /* ---------------------------------------------
           PHP SIZE LIMIT EXCEEDED
        --------------------------------------------- */

        elseif (
            $upload_error === UPLOAD_ERR_INI_SIZE ||
            $upload_error === UPLOAD_ERR_FORM_SIZE
        ) {

            $message =
                'Photo size must be 2 MB or less.';
        }


        /* ---------------------------------------------
           OTHER UPLOAD ERROR
        --------------------------------------------- */

        elseif (
            $upload_error !== UPLOAD_ERR_OK
        ) {

            $message =
                'There was a problem uploading the photo.';
        }


        /* ---------------------------------------------
           SUCCESSFUL UPLOAD
        --------------------------------------------- */

        else {

            $photo_size = (int)(
                $_FILES['photo']['size']
                ?? 0
            );

            $tmp_name =
                $_FILES['photo']['tmp_name']
                ?? '';


            /* -----------------------------------------
               SERVER-SIDE 2 MB CHECK
            ----------------------------------------- */

            if ($photo_size > 2097152) {

                $message =
                    'Photo size must be 2 MB or less.';
            }


            /* -----------------------------------------
               VALIDATE TEMP FILE
            ----------------------------------------- */

            elseif (
                $photo_size <= 0 ||
                $tmp_name === '' ||
                !is_uploaded_file($tmp_name)
            ) {

                $message =
                    'Invalid photo upload.';
            }


            else {

                /* -------------------------------------
                   ALLOWED MIME TYPES
                ------------------------------------- */

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                $mime =
                    function_exists('mime_content_type')
                    ? mime_content_type($tmp_name)
                    : '';


                /* -------------------------------------
                   MIME VALIDATION
                ------------------------------------- */

                if (
                    !isset($allowed[$mime])
                ) {

                    $message =
                        'Invalid photo format. Please upload JPG, PNG or WEBP.';
                }


                /* -------------------------------------
                   IMAGE VALIDATION
                ------------------------------------- */

                elseif (
                    @getimagesize($tmp_name) === false
                ) {

                    $message =
                        'The uploaded file is not a valid image.';
                }


                else {

                    /* ---------------------------------
                       RANDOM FILE NAME
                    --------------------------------- */

                    try {

                        $random_name =
                            bin2hex(
                                random_bytes(16)
                            );

                    } catch (Exception $e) {

                        $random_name =
                            bin2hex(
                                uniqid('', true)
                            );
                    }


                    $new_photo =
                        $random_name .
                        '.' .
                        $allowed[$mime];


                    $upload_directory =
                        __DIR__ .
                        '/../uploads/';


                    /* ---------------------------------
                       CREATE UPLOAD DIRECTORY
                    --------------------------------- */

                    if (
                        !is_dir($upload_directory)
                    ) {

                        if (
                            !mkdir(
                                $upload_directory,
                                0755,
                                true
                            )
                        ) {

                            $message =
                                'Unable to create photo upload directory.';
                        }
                    }


                    /* ---------------------------------
                       MOVE PHOTO
                    --------------------------------- */

                    if ($message === '') {

                        $upload_path =
                            $upload_directory .
                            $new_photo;


                        if (
                            move_uploaded_file(
                                $tmp_name,
                                $upload_path
                            )
                        ) {

                            $photo =
                                $new_photo;

                            $new_photo_uploaded =
                                true;

                        } else {

                            $message =
                                'Photo upload failed.';
                        }
                    }
                }
            }
        }
    }


    /* =================================================
       UPDATE DATABASE
    ================================================= */

    if ($message === '') {

        $update_sql =
            'UPDATE members SET
                member_id = ?,
                fullname = ?,
                gender = ?,
                dob = ?,
                phone = ?,
                email = ?,
                address = ?,
                baptism_date = ?,
                marital_status = ?,
                photo = ?
             WHERE id = ?';


        $update_stmt =
            mysqli_prepare(
                $conn,
                $update_sql
            );


        if (!$update_stmt) {

            $message =
                'Unable to process member information.';

        } else {

            mysqli_stmt_bind_param(
                $update_stmt,
                'ssssssssssi',
                $member_id,
                $fullname,
                $gender,
                $dob,
                $phone,
                $email,
                $address,
                $baptism_date,
                $marital_status,
                $photo,
                $id
            );


            if (
                mysqli_stmt_execute($update_stmt)
            ) {

                mysqli_stmt_close($update_stmt);


                /* -------------------------------------
                   DELETE OLD PHOTO AFTER SUCCESS
                ------------------------------------- */

                if (
                    $new_photo_uploaded &&
                    $old_photo !== '' &&
                    $photo !== $old_photo
                ) {

                    $old_path =
                        __DIR__ .
                        '/../uploads/' .
                        basename($old_photo);


                    if (
                        is_file($old_path)
                    ) {

                        @unlink($old_path);
                    }
                }


                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header(
                    'Location: view_members.php?msg=updated'
                );

                exit();

            } else {

                mysqli_stmt_close($update_stmt);

                $message =
                    'Update failed. Please try again.';
            }
        }


        /* ---------------------------------------------
           DELETE NEW PHOTO IF DATABASE UPDATE FAILED
        --------------------------------------------- */

        if (
            $new_photo_uploaded &&
            $photo !== ''
        ) {

            $new_path =
                __DIR__ .
                '/../uploads/' .
                basename($photo);


            if (
                is_file($new_path)
            ) {

                @unlink($new_path);
            }
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

<title>Edit Member</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body {
    background: #f5f6fa;
}

.edit-card {
    max-width: 900px;
    margin: auto;
}

.card {
    border: none;
    border-radius: 12px;
}

.current-photo {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #ddd;
}

#photoError {
    display: none;
}

#updateButton:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

</style>


<!-- =====================================================
     PHOTO VALIDATION
===================================================== -->

<script>

const MAX_PHOTO_SIZE = 2097152;


/* =====================================================
   CHECK PHOTO SIZE
===================================================== */

function validatePhotoSize(input) {

    const errorBox =
        document.getElementById('photoError');

    const updateButton =
        document.getElementById('updateButton');


    if (!input || !errorBox) {
        return true;
    }


    /* ---------------------------------------------
       NO FILE SELECTED
    --------------------------------------------- */

    if (
        !input.files ||
        input.files.length === 0
    ) {

        errorBox.style.display = 'none';

        input.classList.remove('is-invalid');

        if (updateButton) {
            updateButton.disabled = false;
        }

        return true;
    }


    /* ---------------------------------------------
       GET SELECTED FILE
    --------------------------------------------- */

    const file =
        input.files[0];


    /* ---------------------------------------------
       CHECK FILE SIZE
    --------------------------------------------- */

    if (
        file.size > MAX_PHOTO_SIZE
    ) {

        /* Show existing error box */

        errorBox.style.display = 'block';

        input.classList.add('is-invalid');


        /* Disable update button */

        if (updateButton) {
            updateButton.disabled = true;
        }


        /* Alert user */

        window.alert(
            'Photo size must be 2 MB or less.'
        );


        return false;
    }


    /* ---------------------------------------------
       VALID PHOTO SIZE
    --------------------------------------------- */

    errorBox.style.display = 'none';

    input.classList.remove('is-invalid');

    if (updateButton) {
        updateButton.disabled = false;
    }

    return true;
}


/* =====================================================
   CHECK PHOTO BEFORE FORM SUBMISSION
===================================================== */

function validatePhotoBeforeSubmit() {

    const photoInput =
        document.getElementById('photo');

    if (!photoInput) {
        return true;
    }

    return validatePhotoSize(photoInput);
}

</script>

</head>

<body>

<div class="container mt-4 edit-card">

<div class="card shadow">

<div class="card-header bg-warning">

<h3 class="mb-0">
✏️ Edit Church Member
</h3>

</div>

<div class="card-body p-4">


<?php if ($message !== ''): ?>

<div class="alert alert-<?php
echo htmlspecialchars($message_type);
?>">

<?php
echo htmlspecialchars($message);
?>

</div>

<?php endif; ?>


<form
method="POST"
enctype="multipart/form-data"
id="editMemberForm"
onsubmit="return validatePhotoBeforeSubmit();">


<input
type="hidden"
name="csrf_token"
value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

<input
type="hidden"
name="MAX_FILE_SIZE"
value="2097152">


<div class="row">


<!-- MEMBER ID -->

<div class="col-md-6 mb-3">

<label class="form-label">
Member ID
</label>

<input
type="text"
name="member_id"
class="form-control"
value="<?php
echo htmlspecialchars($member_id);
?>"
required
maxlength="50">

</div>


<!-- FULL NAME -->

<div class="col-md-6 mb-3">

<label class="form-label">
Full Name
</label>

<input
type="text"
name="fullname"
class="form-control"
value="<?php
echo htmlspecialchars($fullname);
?>"
required
maxlength="100">

</div>


<!-- GENDER -->

<div class="col-md-6 mb-3">

<label class="form-label">
Gender
</label>

<select
name="gender"
class="form-select"
required>

<option
value="Male"
<?php
echo $gender === 'Male'
    ? 'selected'
    : '';
?>>

Male

</option>

<option
value="Female"
<?php
echo $gender === 'Female'
    ? 'selected'
    : '';
?>>

Female

</option>

</select>

</div>


<!-- DOB -->

<div class="col-md-6 mb-3">

<label class="form-label">
Date of Birth
</label>

<input
type="date"
name="dob"
class="form-control"
value="<?php
echo htmlspecialchars($dob);
?>"
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
value="<?php
echo htmlspecialchars($phone);
?>"
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
value="<?php
echo htmlspecialchars($email);
?>"
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
maxlength="500"><?php
echo htmlspecialchars($address);
?></textarea>

</div>


<!-- BAPTISM DATE -->

<div class="col-md-6 mb-3">

<label class="form-label">
Baptism Date
</label>

<input
type="date"
name="baptism_date"
class="form-control"
value="<?php
echo htmlspecialchars($baptism_date);
?>">

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

<option
value="Single"
<?php
echo $marital_status === 'Single'
    ? 'selected'
    : '';
?>>

Single

</option>

<option
value="Married"
<?php
echo $marital_status === 'Married'
    ? 'selected'
    : '';
?>>

Married

</option>

</select>

</div>


<!-- CURRENT PHOTO -->

<div class="col-md-6 mb-3">

<label class="form-label">
Current Photo
</label>

<br><br>

<?php if (
    !empty($row['photo']) &&
    is_file(
        __DIR__ .
        '/../uploads/' .
        basename($row['photo'])
    )
): ?>

<img
src="../uploads/<?php
echo htmlspecialchars(
    basename($row['photo'])
);
?>"
class="current-photo"
alt="Current Photo">

<?php else: ?>

<div
class="bg-secondary text-white
d-flex align-items-center
justify-content-center"
style="
width:120px;
height:120px;
border-radius:10px;
">

👤 No Photo

</div>

<?php endif; ?>

</div>


<!-- CHANGE PHOTO -->

<div class="col-md-6 mb-3">

<label
for="photo"
class="form-label">

Change Photo

</label>

<input
type="file"
name="photo"
id="photo"
class="form-control"
accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
onchange="validatePhotoSize(this)">


<div
id="photoError"
class="alert alert-danger mt-2 mb-0"
style="display:none;">

<strong>
⚠️ Photo size must be 2 MB or less.
</strong>

</div>


<small
class="text-muted d-block mt-2">

Leave empty to keep the existing photo.
Maximum 2 MB.

</small>

</div>

</div>


<!-- BUTTONS -->

<div class="mt-3">

<button
type="submit"
name="update"
id="updateButton"
class="btn btn-primary">

💾 Update Member

</button>

<a
href="view_members.php"
class="btn btn-secondary">

↩️ Back

</a>

<a
href="../dashboard.php"
class="btn btn-outline-primary ms-1">

🏠 Dashboard

</a>

</div>


</form>

</div>

</div>

</div>


<script>

/* =====================================================
   BFCache PROTECTION
===================================================== */

window.addEventListener(
    'pageshow',
    function (event) {

        if (event.persisted) {

            window.location.reload();
        }

    }
);

</script>

</body>
</html>