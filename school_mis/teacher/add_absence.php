<?php
session_start();
include '../db.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
  $student_id = (int)($_POST['student_id'] ?? 0);
  $date = trim($_POST['date'] ?? '');

  if($student_id <= 0 || $date === ''){
    $error = 'Please provide a valid student ID and date.';
  } else {
    // Validate that date is a working day (Monday-Friday)
    $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
    if($dayOfWeek < 1 || $dayOfWeek > 5) {
      $error = 'Absences can only be recorded for working days (Monday to Friday).';
    } else {
      try {
        // optional: verify student exists
        $chk = $db->prepare("SELECT id FROM students WHERE id = ?");
        $chk->execute([$student_id]);
        if(!$chk->fetch()){
          $error = 'Student not found.';
        } else {
          $stmt = $db->prepare("INSERT INTO absences (student_id, date) VALUES (?, ?)");
          $stmt->execute([$student_id, $date]);
          $success = 'Absence recorded successfully.';
          $_POST = [];
        }
      } catch(Exception $e) {
        $error = 'Error recording absence. Please try again.';
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Record Absence</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .form-card{max-width:560px;margin:40px auto;padding:24px;background:#fff;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06)}
    .form-group{margin-bottom:14px}
    label{display:block;margin-bottom:6px;font-weight:600;color:#333}
    input[type=number],input[type=date]{width:100%;padding:10px 12px;border:1px solid #e0e0e0;border-radius:8px}
    .btn{padding:10px 18px;background:linear-gradient(135deg,#7f5af0,#00e7ff);color:#fff;border-radius:8px;border:none;cursor:pointer}
  </style>
</head>
<body>
  <div class="form-card">
    <h2>Record Absence</h2>
    <p class="muted">Mark a student's absence for a specific working day (Monday to Friday).</p>

    <?php if($error): ?>
      <div style="background:#f8d7da;padding:10px;border-left:4px solid #dc3545;border-radius:6px;margin:12px 0;color:#721c24;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if($success): ?>
      <div style="background:#d4edda;padding:10px;border-left:4px solid #28a745;border-radius:6px;margin:12px 0;color:#155724;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="form-group">
        <label for="student_id">Student</label>
        <select id="student_id" name="student_id" required>
          <option value="">-- Select student --</option>
          <?php
          try {
            $allStudents = $db->query("SELECT id,name,class FROM students ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
          } catch(Exception $e) {
            $allStudents = [];
          }
          foreach($allStudents as $st): ?>
            <option value="<?php echo $st['id']; ?>" <?php echo (isset($_POST['student_id']) && $_POST['student_id']==$st['id'])? 'selected' : ''; ?> ><?php echo htmlspecialchars($st['name'].' ('.$st['class'].')'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="date">Date (Working Days Only)</label>
        <input id="date" type="date" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>" required>
        <small style="color:#999;display:block;margin-top:4px;">ⓘ Only weekdays (Monday-Friday) are allowed</small>
      </div>
      <div style="display:flex;gap:12px;align-items:center;">
        <button class="btn" type="submit">Record Absence</button>
        <a href="teacher.php" style="color:#7f5af0;text-decoration:none;font-weight:600;">← Back</a>
      </div>
    </form>
  </div>

  <script>
    document.getElementById('date').addEventListener('change', function() {
      const selectedDate = new Date(this.value);
      const dayOfWeek = selectedDate.getDay(); // 0=Sunday, 1=Monday, ..., 6=Saturday
      
      if(dayOfWeek === 0 || dayOfWeek === 6) {
        alert('Absences can only be recorded for working days (Monday to Friday). Weekends are not allowed.');
        this.value = '';
      }
    });
    
    // Disable weekends in date picker
    document.getElementById('date').addEventListener('focus', function() {
      const today = new Date();
      let minDate = new Date(today);
      minDate.setDate(minDate.getDate() + 1);
      
      // Set min date to next Monday if today is Friday/Saturday/Sunday
      const dow = minDate.getDay();
      if(dow === 0) minDate.setDate(minDate.getDate() + 1); // Sunday to Monday
      else if(dow === 6) minDate.setDate(minDate.getDate() + 2); // Saturday to Monday
    });
  </script>
</body>
</html>
