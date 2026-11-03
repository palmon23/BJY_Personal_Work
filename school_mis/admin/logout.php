<?php
session_start();
session_unset();   // remove all session variables
session_destroy(); // destroy the session

// Redirect back to login page
header("Location: index.php");
exit();
?>
