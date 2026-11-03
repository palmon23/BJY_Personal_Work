<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "school_mis";

try {
    $db = new PDO("mysql:host=localhost;dbname=school_mis", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
