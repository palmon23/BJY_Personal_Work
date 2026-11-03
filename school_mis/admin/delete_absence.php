<?php
session_start();
include '../db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access denied");
}

$absence_id = $_GET['id'] ?? null;

if(!$absence_id) {
    header("Location: manage_absences.php");
    exit;
}

try {
    // Verify the absence exists
    $stmt = $db->prepare("SELECT id FROM absences WHERE id = ?");
    $stmt->execute([$absence_id]);
    $absence = $stmt->fetch();
    
    if(!$absence) {
        header("Location: manage_absences.php?error=Absence%20record%20not%20found");
        exit;
    }
    
    // Delete the absence
    $delete_stmt = $db->prepare("DELETE FROM absences WHERE id = ?");
    $delete_stmt->execute([$absence_id]);
    
    header("Location: manage_absences.php?success=Absence%20record%20deleted%20successfully");
    exit;
} catch(Exception $e) {
    header("Location: manage_absences.php?error=Error%20deleting%20absence%20record");
    exit;
}
?>
