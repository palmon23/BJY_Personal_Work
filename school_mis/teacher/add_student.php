<?php
session_start();
include '../db.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
  $name = trim($_POST['name'] ?? '');
  $class = trim($_POST['class'] ?? '');

  if($name === '' || $class === ''){
    $error = 'Please provide both student name and class.';
  } else {
    try {
      $stmt = $db->prepare("INSERT INTO students (name, class) VALUES (?, ?)");
      $stmt->execute([$name, $class]);
      $success = 'Student added successfully.';
      $_POST = [];
    } catch (Exception $e) {
      $error = 'Error adding student. Please try again.';
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Student</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .form-card { max-width: 600px; margin: 40px auto; padding: 24px; background: #fff; border-radius: 10px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
    .form-group { margin-bottom: 14px; }
    label { display:block; margin-bottom:6px; font-weight:600; color:#333; }
    input[type=text] { width:100%; padding:10px 12px; border:1px solid #e0e0e0; border-radius:8px; }
    .btn { display:inline-block; padding:10px 18px; background:linear-gradient(135deg,#7f5af0,#00e7ff); color:#fff; border-radius:8px; border:none; cursor:pointer; }
    .muted { color:#666; font-size:13px; }
  </style>
</head>
<body>
  <div class="form-card">
    <h2>Add Student</h2>
    <p class="muted">Create a new student record.</p>

    <?php if($error): ?>
      <div style="background:#f8d7da;padding:10px;border-left:4px solid #dc3545;border-radius:6px;margin:12px 0;color:#721c24;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if($success): ?>
      <div style="background:#d4edda;padding:10px;border-left:4px solid #28a745;border-radius:6px;margin:12px 0;color:#155724;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="form-group">
        <label for="name">Student Name</label>
        <input id="name" type="text" name="name" placeholder="e.g. John Doe" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
      </div>
      <div class="form-group">
        <label for="class">Class</label>
        <input id="class" type="text" name="class" placeholder="e.g. Grade 5" value="<?php echo htmlspecialchars($_POST['class'] ?? ''); ?>" required>
      </div>
      <div style="display:flex;gap:12px;align-items:center;">
        <button class="btn" type="submit">Add Student</button>
        <a href="teacher.php" style="color:#7f5af0;text-decoration:none;font-weight:600;">← Back</a>
      </div>
    </form>
  </div>
</body>
</html>
