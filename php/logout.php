<?php
session_start(); // Must start session before destroying it!
$_SESSION = [];  // Clear all session variables
session_destroy(); // Destroy the session

header("Location: ../index.html");
exit();
?>