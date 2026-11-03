<?php
session_start();
if($_SESSION['role'] != 'teacher') die("Access denied");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Teacher Dashboard</title>
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
  <header>Teacher Dashboard</header>
  <nav>
    <a href="add_student.php">Add Student</a>
    <a href="add_result.php">Add Result</a>
    <a href="add_absence.php">Record Absence</a>
    <a href="../change_password.php">Change Password</a>
    <a href="../index.php">Logout</a>    

  </nav>

  <div class="container">
    <div class="card">
      <h3>Quick Actions</h3>
      <p>Use the navigation above to manage students and results.</p>
    </div>
  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>
