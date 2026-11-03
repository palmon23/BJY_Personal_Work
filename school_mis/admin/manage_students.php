<?php
session_start();
include '../db.php';
if($_SESSION['role'] != 'admin') die("Access denied");

// Add student
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $name = $_POST['name'];
    $class = $_POST['class'];
    $stmt = $db->prepare("INSERT INTO students (name, class) VALUES (?, ?)");
    $stmt->execute([$name, $class]);
    echo "Student added!";
}

// Fetch all students
$students = $db->query("SELECT * FROM students")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Students</title>
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
  <header>Manage Students</header>
  <nav>
    <a href="admin.php">Back to Dashboard</a>
  </nav>

  <div class="container">
    <div class="card">
      <h3>Add Student</h3>
      <form method="POST">
        <input type="text" name="name" placeholder="Student Name" required>
        <input type="text" name="class" placeholder="Class" required>
        <button type="submit">Add Student</button>
      </form>
    </div>

    <div class="card">
      <h3>Student List</h3>
      <table>
        <tr><th>ID</th><th>Name</th><th>Class</th><th>Actions</th></tr>
        <?php foreach($students as $s): ?>
          <tr>
            <td><?php echo $s['id']; ?></td>
            <td><?php echo $s['name']; ?></td>
            <td><?php echo $s['class']; ?></td>
            <td>
              <a href="edit_student.php?id=<?php echo $s['id']; ?>">Edit</a> |
              <a href="delete_student.php?id=<?php echo $s['id']; ?>" class="delete-btn">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <?php include '../db.php'; ?>
  
  <script src="../js/dashboard.js"></script>
</body>
</html>
