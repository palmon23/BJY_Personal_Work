<?php
session_start();
include '../db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access denied");
}

// Handle logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
  <header>
    <div class="header-content">
      <h1>Admin Dashboard</h1>
      <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
    </div>
  </header>
  
  <nav>
    <a href="manage_students.php">Manage Students</a>
    <a href="manage_teachers.php">Manage Teachers</a>
    <a href="manage_results.php">Manage Results</a>
    <a href="manage_absences.php">Manage Absences</a>
    <a href="../change_password.php">Change Password</a>
    <a href="?logout=1">Logout</a>
  </nav>

  <div class="container">
    <div class="card">
      <h3>Welcome Admin</h3>
      <p>You have full control over students, teachers, results, and absences.</p>
    </div>
  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>