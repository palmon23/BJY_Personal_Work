<?php
session_start();
include '../db.php';
if($_SESSION['role'] != 'admin') die("Access denied");

$id = $_GET['id'];
$student = $db->query("SELECT * FROM students WHERE id=$id")->fetch();

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $name = $_POST['name'];
    $class = $_POST['class'];
    $stmt = $db->prepare("UPDATE students SET name=?, class=? WHERE id=?");
    $stmt->execute([$name, $class, $id]);
    echo "Student updated!";
    header("Location: manage_students.php");
}
?>

<form method="POST">
  <h2>Edit Student</h2>
  <input type="text" name="name" value="<?php echo $student['name']; ?>" required>
  <input type="text" name="class" value="<?php echo $student['class']; ?>" required>
  <button type="submit">Update</button>
</form>

<?php include '../db.php'; ?>
