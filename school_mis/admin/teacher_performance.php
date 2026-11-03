<?php
session_start();
include '../db.php';
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
  header("Location: ../login.php");
  exit;
}

$id = $_GET['id'] ?? null;
if(!$id) die('Invalid teacher ID');

// Fetch teacher
$stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$teacher) die('Teacher not found');

// best-effort: ensure results.created_by exists
try { $db->exec("ALTER TABLE results ADD COLUMN created_by INT NULL"); } catch(Exception $e) {}

$metrics = ['count'=>0,'avg'=>null];
try {
    $cntStmt = $db->prepare("SELECT COUNT(*) as c, AVG(score) as a FROM results r LEFT JOIN users u ON r.created_by = u.id WHERE r.created_by = ? OR u.username = ?");
    $cntStmt->execute([$id, $teacher['name']]);
    $metrics = $cntStmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

$recent = [];
try {
    $resStmt = $db->prepare("SELECT r.subject, r.score, s.name AS student, COALESCE(u.username, '-') AS added_by FROM results r JOIN students s ON r.student_id = s.id LEFT JOIN users u ON r.created_by = u.id WHERE r.created_by = ? OR u.username = ? ORDER BY r.id DESC LIMIT 20");
    $resStmt->execute([$id, $teacher['name']]);
    $recent = $resStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Teacher Performance</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .container { max-width:1100px;margin:30px auto;padding:0 20px }
    .header { background:linear-gradient(135deg,#7f5af0,#00e7ff);color:white;padding:24px;border-radius:8px;text-align:center;margin-bottom:20px }
    .metrics { display:flex;gap:20px;margin-bottom:20px }
    .metric { background:white;padding:18px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.06);flex:1;text-align:center }
    table { width:100%;border-collapse:collapse }
    table th, table td { padding:12px;border-bottom:1px solid #eee }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Performance — <?php echo htmlspecialchars($teacher['name']); ?></h1>
      <p><?php echo htmlspecialchars($teacher['subject']); ?></p>
    </div>

    <div class="metrics">
      <div class="metric">
        <div style="font-size:28px;font-weight:700"><?php echo (int)$metrics['c']; ?></div>
        <div style="color:#666">Results Recorded</div>
      </div>
      <div class="metric">
        <div style="font-size:28px;font-weight:700"><?php echo $metrics['a'] ? round($metrics['a'],2) : '-'; ?></div>
        <div style="color:#666">Average Score</div>
      </div>
    </div>

    <div class="card">
      <h3>Recent Results</h3>
      <?php if(empty($recent)): ?>
        <p>No results recorded by this teacher yet.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>Student</th><th>Subject</th><th>Score</th><th>Added By</th></tr>
          </thead>
          <tbody>
            <?php foreach($recent as $r): ?>
              <tr>
                <td><?php echo htmlspecialchars($r['student']); ?></td>
                <td><?php echo htmlspecialchars($r['subject']); ?></td>
                <td><?php echo htmlspecialchars($r['score']); ?></td>
                <td><?php echo htmlspecialchars($r['added_by']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div style="margin-top:20px">
      <a href="manage_teachers.php">← Back to Teachers</a>
    </div>
  </div>
</body>
</html>
