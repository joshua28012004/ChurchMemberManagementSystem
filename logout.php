<?php

/* =====================================================
   SESSION SECURITY
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
   REMOVE ALL SESSION VARIABLES
===================================================== */

$_SESSION = array();


/* =====================================================
   DELETE SESSION COOKIE
===================================================== */

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"] ?? '',
        $params["secure"],
        $params["httponly"]
    );

}


/* =====================================================
   DESTROY SESSION
===================================================== */

session_destroy();


/* =====================================================
   REDIRECT TO LOGIN
===================================================== */

header("Location: login.php");

exit();

?>