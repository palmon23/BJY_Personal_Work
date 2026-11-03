<?php
session_start();
include '../db.php';

// Handle logout
if(isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

$error = '';
$student = null;
$results = [];
$student_id = $_GET['student_id'] ?? null;
$search_query = $_GET['search'] ?? '';
$students_search = [];

// If search query is provided, search for students
if($search_query && strlen($search_query) >= 2) {
    try {
        $stmt = $db->prepare("SELECT id, name, class FROM students WHERE LOWER(name) LIKE LOWER(?) ORDER BY name LIMIT 10");
        $stmt->execute(['%' . $search_query . '%']);
        $students_search = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        $error = 'Error searching students';
    }
}

// If student ID is provided, fetch their performance
if($student_id) {
    try {
        $stmt = $db->prepare("SELECT id, name, class FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$student) {
            $error = "Student not found.";
        } else {
            // Fetch student's results grouped by term
            $results_stmt = $db->prepare("SELECT term, subject, score FROM results WHERE student_id = ? ORDER BY term, subject");
            $results_stmt->execute([$student_id]);
            $all_results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group results by term
            $results_by_term = ['1st' => [], '2nd' => [], '3rd' => []];
            foreach($all_results as $result) {
                $term = $result['term'] ?? '1st';
                if(!isset($results_by_term[$term])) {
                    $results_by_term[$term] = [];
                }
                $results_by_term[$term][] = $result;
            }
            
            // Fetch student's absences
            $absences_stmt = $db->prepare("SELECT id, date FROM absences WHERE student_id = ? ORDER BY date DESC");
            $absences_stmt->execute([$student_id]);
            $all_absences = $absences_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter to only working days (Monday-Friday)
            $absences = [];
            foreach($all_absences as $absence) {
                $dayOfWeek = date('N', strtotime($absence['date'])); // 1=Monday, 7=Sunday
                if($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Monday to Friday
                    $absences[] = $absence;
                }
            }
        }
    } catch(Exception $e) {
        $error = 'Error loading student information';
    }
}
?>
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Performance</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
        }

        header {
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            padding: 30px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-content h1 {
            font-size: 28px;
            font-weight: 700;
        }

        .header-content span {
            font-size: 14px;
            opacity: 0.9;
        }

        nav {
            background: #fff;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            gap: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        nav a {
            color: #7f5af0;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
            font-size: 14px;
        }

        nav a:hover {
            color: #00e7ff;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .search-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .search-section h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-form input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .search-form input:focus {
            outline: none;
            border-color: #7f5af0;
            box-shadow: 0 0 5px rgba(127, 90, 240, 0.3);
        }

        .search-form button {
            padding: 12px 30px;
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .search-form button:hover {
            transform: translateY(-2px);
        }

        .search-results {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .student-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid #7f5af0;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .student-card:hover {
            box-shadow: 0 4px 16px rgba(127, 90, 240, 0.2);
            transform: translateY(-2px);
        }

        .student-card h4 {
            color: #7f5af0;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .student-card p {
            color: #666;
            font-size: 13px;
            margin: 3px 0;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .student-header {
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .student-header h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .student-header p {
            font-size: 14px;
            opacity: 0.9;
            margin: 5px 0;
        }

        .card h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table thead tr {
            background: linear-gradient(135deg, #7f5af0 0%, #5f3db0 100%);
            color: white;
        }

        table thead th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        table tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.3s ease;
        }

        table tbody tr:hover {
            background-color: #f9f9f9;
        }

        table tbody td {
            padding: 12px;
            color: #333;
            font-size: 14px;
        }

        .score-cell {
            font-weight: 600;
            font-size: 16px;
            color: #7f5af0;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }

        .error-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .average-section {
            border-top: 2px solid #e0e0e0;
            margin-top: 20px;
            padding-top: 20px;
        }

        .average-box {
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
        }

        .average-box p {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #f0f0f0;
            color: #7f5af0;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #e0e0e0;
        }

        .no-results {
            text-align: center;
            padding: 30px;
            color: #999;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 10px;
            }

            nav {
                flex-wrap: wrap;
            }

            .search-form {
                flex-direction: column;
            }

            .search-results {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            table tbody td,
            table thead th {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <h1>🎓 Student Performance Portal</h1>
            <span>View Academic Results</span>
        </div>
    </header>
    
    <nav>
        <a href="parent.php">← Back to Dashboard</a>
        <a href="../index.php">Home</a>
    </nav>

    <div class="container">
        <?php if($error): ?>
            <div class="error-box">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Search Section -->
        <div class="search-section">
            <h2>🔍 Search Student Performance</h2>
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Enter student name..." value="<?php echo htmlspecialchars($search_query); ?>" required>
                <button type="submit">Search</button>
            </form>

            <?php if($search_query && !$students_search && !$student_id): ?>
                <div class="no-results">
                    <p>No students found matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
                </div>
            <?php endif; ?>

            <?php if(!$student_id && $students_search): ?>
                <div class="search-results" style="margin-top: 20px;">
                    <?php foreach($students_search as $s): ?>
                        <a href="?student_id=<?php echo urlencode($s['id']); ?>" class="student-card">
                            <h4>📚 <?php echo htmlspecialchars($s['name']); ?></h4>
                            <p><strong>Class:</strong> <?php echo htmlspecialchars($s['class']); ?></p>
                            <p style="color: #7f5af0; margin-top: 10px;">View Performance →</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Student Performance Section -->
        <?php if($student): ?>
            <a href="view_performance.php" class="back-btn">← New Search</a>
            
            <div class="student-header">
                <h3>📚 <?php echo htmlspecialchars($student['name']); ?></h3>
                <p><strong>Class:</strong> <?php echo htmlspecialchars($student['class']); ?></p>
            </div>

            <div class="card" id="results-card">
                <h3>📊 Academic Results by Term</h3>

                <div style="margin:12px 0 18px;">
                    <div class="term-tabs" style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="term-tab active" data-term="all" style="padding:8px 12px;border-radius:6px;border:1px solid #e0e0e0;background:#fff;cursor:pointer">All</button>
                        <button class="term-tab" data-term="1st" style="padding:8px 12px;border-radius:6px;border:1px solid #e0e0e0;background:#fff;cursor:pointer">1st Term</button>
                        <button class="term-tab" data-term="2nd" style="padding:8px 12px;border-radius:6px;border:1px solid #e0e0e0;background:#fff;cursor:pointer">2nd Term</button>
                        <button class="term-tab" data-term="3rd" style="padding:8px 12px;border-radius:6px;border:1px solid #e0e0e0;background:#fff;cursor:pointer">3rd Term</button>
                        <button class="term-tab" data-term="final" style="padding:8px 12px;border-radius:6px;border:1px solid #e0e0e0;background:#fff;cursor:pointer">Final</button>
                    </div>
                </div>
                <?php 
                $hasResults = false;
                foreach(['1st', '2nd', '3rd'] as $termName) {
                    if(!empty($results_by_term[$termName])) {
                        $hasResults = true;
                        $results = $results_by_term[$termName];
                ?>
                    <div class="term-block" data-term="<?php echo $termName; ?>" style="margin-bottom: 30px;">
                        <h4 style="color: #7f5af0; margin-bottom: 15px; font-size: 16px; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                            📋 <?php echo $termName; ?> Term Results
                        </h4>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th style="text-align: center;">Score</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalScore = 0;
                                $count = 0;
                                foreach($results as $r): 
                                    $score = (int)$r['score'];
                                    $totalScore += $score;
                                    $count++;
                                    
                                    if($score >= 80) {
                                        $status = 'Excellent';
                                        $color = '#28a745';
                                        $bgColor = '#d4edda';
                                    } elseif($score >= 60) {
                                        $status = 'Good';
                                        $color = '#007bff';
                                        $bgColor = '#d1ecf1';
                                    } elseif($score >= 40) {
                                        $status = 'Average';
                                        $color = '#ff9800';
                                        $bgColor = '#fff3cd';
                                    } else {
                                        $status = 'Needs Improvement';
                                        $color = '#dc3545';
                                        $bgColor = '#f8d7da';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['subject']); ?></td>
                                        <td style="text-align: center;" class="score-cell"><?php echo htmlspecialchars($r['score']); ?>/100</td>
                                        <td style="text-align: center;">
                                            <span class="status-badge" style="background-color: <?php echo $bgColor; ?>; color: <?php echo $color; ?>;"><?php echo $status; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if($count > 0): 
                            // Compute average on 0-100 then convert to 0-20 scale
                            $avgScore = round($totalScore / $count, 2);
                            $avg20 = round(($avgScore / 100) * 20, 2);
                            // Status thresholds on 20-point scale: 16,12,8
                            if($avg20 >= 16) {
                                $avgStatus = 'Excellent';
                                $avgColor = '#28a745';
                                $avgBg = '#d4edda';
                            } elseif($avg20 >= 12) {
                                $avgStatus = 'Good';
                                $avgColor = '#007bff';
                                $avgBg = '#d1ecf1';
                            } elseif($avg20 >= 8) {
                                $avgStatus = 'Average';
                                $avgColor = '#ff9800';
                                $avgBg = '#fff3cd';
                            } else {
                                $avgStatus = 'Needs Improvement';
                                $avgColor = '#dc3545';
                                $avgBg = '#f8d7da';
                            }
                        ?>
                            <div class="average-section">
                                <div class="average-box" style="background-color: <?php echo $avgBg; ?>; border-left-color: <?php echo $avgColor; ?>;">
                                    <p style="color: <?php echo $avgColor; ?>;">
                                        <?php echo $termName; ?> Term Average: <strong><?php echo $avg20; ?>/20</strong>
                                        <span style="margin-left: 10px; padding: 4px 10px; background-color: <?php echo $avgColor; ?>; color: white; border-radius: 15px; font-size: 12px;"><?php echo $avgStatus; ?></span>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php 
                    }
                }
                if(!$hasResults):
                ?>
                    <div class="no-results">
                        <p>No results recorded yet for this student.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Final Results Summary -->
            <div class="card term-block" data-term="final">
                <h3>🏁 Final Results (Average of 1st, 2nd & 3rd)</h3>
                <?php
                // Build list of all subjects across terms
                $subjects = [];
                foreach(['1st','2nd','3rd'] as $t) {
                    if(!empty($results_by_term[$t])) {
                        foreach($results_by_term[$t] as $r) {
                            $subjects[$r['subject']] = true;
                        }
                    }
                }
                $subjects = array_keys($subjects);

                if(empty($subjects)) {
                    echo '<div class="no-results"><p>No final results yet.</p></div>';
                } else {
                    // Prepare lookup maps for quick access
                    $scores = ['1st'=>[], '2nd'=>[], '3rd'=>[]];
                    foreach(['1st','2nd','3rd'] as $t) {
                        if(!empty($results_by_term[$t])) {
                            foreach($results_by_term[$t] as $r) {
                                $scores[$t][$r['subject']] = (int)$r['score'];
                            }
                        }
                    }

                    $finalTotal = 0; $finalCount = 0;
                    ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th style="text-align: center;">1st</th>
                                <th style="text-align: center;">2nd</th>
                                <th style="text-align: center;">3rd</th>
                                <th style="text-align: center;">Final Avg</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                    <?php
                    foreach($subjects as $sub) {
                        $s1 = $scores['1st'][$sub] ?? 0;
                        $s2 = $scores['2nd'][$sub] ?? 0;
                        $s3 = $scores['3rd'][$sub] ?? 0;
                        $final = round((($s1 + $s2 + $s3) / 3), 2);
                        // convert to 0-20 scale
                        $final20 = round(($final / 100) * 20, 2);
                        $finalTotal += $final20; $finalCount++;

                        // status thresholds on 20-point scale
                        if($final20 >= 16) { $status='Excellent'; $color='#28a745'; $bg='#d4edda'; }
                        elseif($final20 >= 12) { $status='Good'; $color='#007bff'; $bg='#d1ecf1'; }
                        elseif($final20 >= 8) { $status='Average'; $color='#ff9800'; $bg='#fff3cd'; }
                        else { $status='Needs Improvement'; $color='#dc3545'; $bg='#f8d7da'; }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub); ?></td>
                            <td style="text-align:center"><?php echo $s1; ?>/100</td>
                            <td style="text-align:center"><?php echo $s2; ?>/100</td>
                            <td style="text-align:center"><?php echo $s3; ?>/100</td>
                            <td style="text-align:center"><strong><?php echo $final20; ?>/20</strong></td>
                            <td style="text-align:center"><span class="status-badge" style="background-color: <?php echo $bg; ?>; color: <?php echo $color; ?>;"><?php echo $status; ?></span></td>
                        </tr>
                    <?php }
                    ?>
                        </tbody>
                    </table>
                    <?php
                    if($finalCount > 0) {
                        // overall is calculated from final20 entries
                        $overall = round($finalTotal / $finalCount, 2);
                        // status thresholds on 20-point scale
                        if($overall >= 16) { $oStatus='Excellent'; $oColor='#28a745'; $oBg='#d4edda'; }
                        elseif($overall >= 12) { $oStatus='Good'; $oColor='#007bff'; $oBg='#d1ecf1'; }
                        elseif($overall >= 8) { $oStatus='Average'; $oColor='#ff9800'; $oBg='#fff3cd'; }
                        else { $oStatus='Needs Improvement'; $oColor='#dc3545'; $oBg='#f8d7da'; }
                        ?>
                        <div class="average-section" style="margin-top:12px;">
                            <div class="average-box" style="background-color: <?php echo $oBg; ?>; border-left-color: <?php echo $oColor; ?>;">
                                <p style="color: <?php echo $oColor; ?>;">Final Average Across Subjects: <strong><?php echo $overall; ?>/20</strong>
                                    <span style="margin-left:10px; padding:4px 10px; background-color: <?php echo $oColor; ?>; color:#fff; border-radius:12px; font-size:12px;"><?php echo $oStatus; ?></span>
                                </p>
                            </div>
                        </div>
                    <?php }
                }
                ?>
            </div>

            <div class="card">
                <h3>🛑 Absence Records</h3>
                <?php if(empty($absences)): ?>
                    <div class="no-results">
                        <p>No absence records for this student.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($absences as $absence): 
                                $date = date('M d, Y', strtotime($absence['date']));
                                $dayName = date('l', strtotime($absence['date'])); // Get day name (Monday, Tuesday, etc.)
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dayName . ', ' . $date); ?></td>
                                    <td style="text-align: center;">
                                        <span class="status-badge" style="background-color: #f8d7da; color: #dc3545;">Absent</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="../js/dashboard.js"></script>
        <script>
        // Term tab toggling for parent view
        (function(){
            function showTerm(term){
                var blocks = document.querySelectorAll('.term-block');
                blocks.forEach(function(b){
                    if(term === 'all') b.style.display = '';
                    else b.style.display = (b.getAttribute('data-term') === term) ? '' : 'none';
                });
                // final block uses data-term="final"
                var tabs = document.querySelectorAll('.term-tab');
                tabs.forEach(function(t){ t.classList.remove('active'); });
                var active = document.querySelector('.term-tab[data-term="'+term+'"]');
                if(active) active.classList.add('active');
            }

            document.addEventListener('DOMContentLoaded', function(){
                var tabs = document.querySelectorAll('.term-tab');
                tabs.forEach(function(tab){
                    tab.addEventListener('click', function(){
                        var term = this.getAttribute('data-term');
                        showTerm(term);
                    });
                });
                // default: show all
                showTerm('all');
            });
        })();
        </script>
</body>
</html>
