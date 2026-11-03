<?php
session_start();
include '../db.php';
if($_SESSION['role'] != 'admin') die("Access denied");

$id = $_GET['id'] ?? null;
if(!$id) die("Invalid result ID");

// Fetch result details for confirmation
$stmt = $db->prepare("SELECT r.*, s.name AS student FROM results r JOIN students s ON r.student_id = s.id WHERE r.id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$result) die("Result not found");

// Perform deletion on POST
if($_SERVER['REQUEST_METHOD'] === 'POST'){
        try {
                $delete = $db->prepare("DELETE FROM results WHERE id = ?");
                $delete->execute([$id]);
                header("Location: manage_results.php");
                exit;
        } catch(Exception $e) {
                $error = 'Error deleting result: ' . htmlspecialchars($e->getMessage());
        }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete Result</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .confirm-card{max-width:720px;margin:40px auto;padding:24px;background:#fff;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06);text-align:center}
        .danger{background:#f8d7da;border-left:4px solid #dc3545;color:#721c24;padding:12px;border-radius:6px;margin-bottom:16px}
        .btn{padding:10px 18px;border-radius:8px;border:none;cursor:pointer}
        .btn-danger{background:#dc3545;color:#fff}
        .btn-muted{background:#f0f0f0;color:#333}
    </style>
</head>
<body>
    <header style="background:linear-gradient(135deg,#7f5af0,#00e7ff);color:white;padding:20px 0;text-align:center;">Delete Result</header>
    <nav style="padding:12px 20px;background:#fff;border-bottom:1px solid #eee;">
        <a href="manage_results.php">← Back to Results</a>
    </nav>

    <div class="container">
        <div class="confirm-card">
            <?php if(isset($error)): ?>
                <div class="danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this result?</p>
            <p style="margin:12px 0;"><strong><?php echo htmlspecialchars($result['student']); ?> — <?php echo htmlspecialchars($result['subject']); ?> (<?php echo htmlspecialchars($result['score']); ?>)</strong></p>

            <form method="POST" style="display:inline-block;">
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
            </form>
            <a href="manage_results.php" class="btn btn-muted" style="display:inline-block;margin-left:12px;padding:10px 18px;text-decoration:none;">Cancel</a>
        </div>
    </div>

</body>
</html>
