<?php

/* =====================================================
   SESSION SECURITY
   Must be configured BEFORE session_start()
===================================================== */

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');

$secure_cookie = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure_cookie,
    'httponly' => true,
    'samesite' => 'Lax'
]);

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
   CSRF TOKEN
===================================================== */

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    strlen($_SESSION['csrf_token']) !== 64
) {

    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );

}

$csrf_token = $_SESSION['csrf_token'];


/* =====================================================
   DATABASE
===================================================== */

include 'includes/db.php';


/* =====================================================
   LOGIN PROCESS
===================================================== */

$error = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* =================================================
       CSRF VALIDATION
    ================================================= */

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submitted_token) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $submitted_token
        )
    ) {

        $error = "Invalid login request. Please try again.";

    } else {


        /* =================================================
           GET USER INPUT
        ================================================= */

        $username = trim(
            $_POST['username'] ?? ''
        );

        $password = $_POST['password'] ?? '';


        /* =================================================
           BASIC VALIDATION
        ================================================= */

        if (
            $username === '' ||
            $password === ''
        ) {

            $error = "Please enter username and password.";

        } elseif (strlen($username) > 50) {

            $error = "Invalid username or password.";

        } else {


            /* =================================================
               PREPARED LOGIN QUERY
            ================================================= */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT username, password
                 FROM admin
                 WHERE username = ?
                 LIMIT 1"
            );


            if ($stmt) {


                mysqli_stmt_bind_param(
                    $stmt,
                    "s",
                    $username
                );


                mysqli_stmt_execute($stmt);


                $result = mysqli_stmt_get_result($stmt);


                if (
                    $result &&
                    mysqli_num_rows($result) === 1
                ) {


                    $row = mysqli_fetch_assoc($result);


                    /* =================================================
                       VERIFY HASHED PASSWORD
                    ================================================= */

                    if (
                        isset($row['password']) &&
                        password_verify(
                            $password,
                            $row['password']
                        )
                    ) {


                        /* =================================================
                           PREVENT SESSION FIXATION
                        ================================================= */

                        session_regenerate_id(true);


                        /* =================================================
                           STORE AUTHENTICATED ADMIN
                        ================================================= */

                        $_SESSION['admin'] =
                            $row['username'];


                        /* =================================================
                           RECORD LOGIN TIME
                        ================================================= */

                        $_SESSION['login_time'] =
                            time();


                        /* =================================================
                           GENERATE FRESH CSRF TOKEN
                        ================================================= */

                        $_SESSION['csrf_token'] =
                            bin2hex(
                                random_bytes(32)
                            );


                        /* =================================================
                           REDIRECT TO DASHBOARD
                        ================================================= */

                        header(
                            "Location: dashboard.php"
                        );

                        exit();

                    }

                }


                /* =================================================
                   GENERIC LOGIN ERROR
                   Prevents username enumeration
                ================================================= */

                $error =
                    "Invalid username or password.";


                mysqli_stmt_close($stmt);


            } else {


                /* =================================================
                   DO NOT EXPOSE DATABASE DETAILS
                ================================================= */

                $error =
                    "Unable to process login. Please try again.";

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
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Login - Church Member Management System
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;

            background: #f5f6fa;

            font-family: Arial, sans-serif;

        }


        .login-container {

            width: 100%;

            max-width: 420px;

            padding: 20px;

        }


        .login-box {

            background: #ffffff;

            padding: 35px;

            border-radius: 12px;

            box-shadow:
                0 4px 20px rgba(0, 0, 0, 0.08);

        }


        .login-title {

            text-align: center;

            margin-bottom: 25px;

        }


        .login-title h2 {

            margin: 0 0 8px;

            color: #222;

        }


        .login-title p {

            margin: 0;

            color: #777;

            font-size: 14px;

        }


        .form-group {

            margin-bottom: 18px;

        }


        label {

            display: block;

            margin-bottom: 7px;

            font-weight: 600;

            color: #333;

        }


        input {

            width: 100%;

            padding: 12px;

            border: 1px solid #ccc;

            border-radius: 7px;

            font-size: 15px;

        }


        input:focus {

            outline: none;

            border-color: #0d6efd;

        }


        button {

            width: 100%;

            padding: 12px;

            border: none;

            border-radius: 7px;

            background: #0d6efd;

            color: #ffffff;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;

        }


        button:hover {

            opacity: 0.9;

        }


        .error {

            background: #f8d7da;

            color: #842029;

            border: 1px solid #f5c2c7;

            padding: 10px;

            border-radius: 7px;

            margin-bottom: 18px;

            font-size: 14px;

        }


        .security-note {

            margin-top: 20px;

            text-align: center;

            font-size: 12px;

            color: #888;

        }

    </style>

</head>


<body>


<div class="login-container">

    <div class="login-box">


        <div class="login-title">

            <h2>Admin Login</h2>

            <p>
                Church Member Management System
            </p>

        </div>


        <?php if ($error !== "") { ?>

            <div class="error">

                <?php

                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </div>

        <?php } ?>


        <form method="POST" action="login.php">


            <!-- CSRF TOKEN -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?php

                echo htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>"
            >


            <!-- USERNAME -->

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    required
                    maxlength="50"
                >

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    maxlength="255"
                >

            </div>


            <!-- LOGIN BUTTON -->

            <button type="submit">
                Login
            </button>


        </form>


        <div class="security-note">

            🔒 Secure administrator authentication

        </div>


    </div>

</div>


</body>

</html>