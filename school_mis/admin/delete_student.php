<?php
session_start();
include '../db.php';
if($_SESSION['role'] != 'admin') die("Access denied");

$id = $_GET['id'];
$stmt = $db->prepare("DELETE FROM students WHERE id=?");
$stmt->execute([$id]);

echo "Student deleted!";
header("Location: manage_students.php");
?>

<?php include '../db.php'; ?>
