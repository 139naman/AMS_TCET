<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit();
}

require 'config.php';

$student_id = $_SESSION['user_id'];
$division_id = $_SESSION['division_id'];

// Get selected date from GET, default to today
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$timestamp = strtotime($selectedDate);
if ($timestamp === false) {
    $selectedDate = date('Y-m-d');
    $timestamp = strtotime($selectedDate);
}
$dayOfWeek = date('l', $timestamp);

// Calculate previous and next days for navigation
$prevDate = date('Y-m-d', strtotime('-1 day', $timestamp));
$nextDate = date('Y-m-d', strtotime('+1 day', $timestamp));

// Fetch timetable for the division filtered by day_of_week
$stmt = $pdo->prepare("
    SELECT tt.id AS timetable_id, tt.period, s.name AS subject_name
    FROM timetable tt
    JOIN subjects s ON s.id = tt.subject_id
    WHERE tt.division_id = ? AND tt.day_of_week = ?
    ORDER BY tt.period ASC
");
$stmt->execute([$division_id, $dayOfWeek]);
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each timetable entry, get attendance status for the selected date
function getAttendanceStatus($pdo, $student_id, $timetable_id, $date) {
    $stmt = $pdo->prepare("SELECT status FROM attendance WHERE student_id = ? AND timetable_id = ? AND attendance_date = ?");
    $stmt->execute([$student_id, $timetable_id, $date]);
    $status = $stmt->fetchColumn();
    if ($status === false) return 'Not Marked';
    return ucfirst($status); // Present or Absent
}

// Overall attendance summary till selected date
$stmtTotal = $pdo->prepare("
    SELECT COUNT(*) AS total_lectures,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_lectures
    FROM attendance a
    JOIN timetable tt ON a.timetable_id = tt.id
    WHERE a.student_id = ? AND a.attendance_date <= ? AND tt.division_id = ?
");
$stmtTotal->execute([$student_id, $selectedDate, $division_id]);
$totalData = $stmtTotal->fetch(PDO::FETCH_ASSOC);

$totalLectures = $totalData['total_lectures'] ?? 0;
$presentLectures = $totalData['present_lectures'] ?? 0;
$overallPct = $totalLectures > 0 ? round(($presentLectures / $totalLectures) * 100, 2) : '-';

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Student Dashboard - Attendance Management System</title>
<style>
  * {
    box-sizing: border-box;
  }
  body, html {
    height: 100%;
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f9f6ee, #fffefa);
    color: #2e2e2e;
  }
  .container {
    max-width: 900px;
    margin: 3rem auto 5rem auto;
    background: rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    box-shadow: 0 15px 30px rgba(0,0,0,0.12);
    padding: 2.5rem 3rem;
  }
  header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
  }
  .logo {
    width: 120px;
  }
  .logo img {
    max-width: 100%;
    height: auto;
    user-select: none;
  }
  h1 {
    font-weight: 700;
    font-size: 1.8rem;
    color: #4a4a4a;
    flex-grow: 1;
    text-align: center;
    margin: 0;
  }
  .date-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    font-weight: 600;
    color: #7e6b4f;
    user-select: none;
  }
  .date-nav button {
    background: linear-gradient(135deg, #d0ba77 0%, #a9987c 100%);
    border: none;
    color: #3b3b3b;
    font-weight: 700;
    font-size: 1rem;
    padding: 6px 12px;
    margin: 0 1rem;
    border-radius: 20px;
    cursor: pointer;
    box-shadow: 0 4px 8px rgba(154,138,110,0.3);
    transition: background 0.3s ease;
  }
  .date-nav button:hover {
    background: linear-gradient(135deg, #a9987c 0%, #d0ba77 100%);
  }
  table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 12px;
    margin-bottom: 1rem;
  }
  th, td {
    padding: 12px 15px;
    text-align: left;
  }
  th {
    background: #f1f1f1;
    border-radius: 12px;
    color: #666;
    font-weight: 600;
  }
  tr {
    background: #fafafa;
    border-radius: 12px;
  }
  tr:hover {
    background: #f0e9d2;
  }
  td:first-child {
    font-weight: 600;
    color: #7e6b4f;
  }
  .status-present {
    color: #4caf50; /* green */
    font-weight: 700;
  }
  .status-absent {
    color: #e53935; /* red */
    font-weight: 700;
  }
  .status-not {
    color: #a9987c; /* muted */
    font-style: italic;
  }
  .summary {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 1rem;
    color: #5a5a5a;
    text-align: center;
  }
  .logout-btn {
    background: linear-gradient(135deg, #d0ba77 0%, #a9987c 100%);
    border: none;
    color: #3b3b3b;
    font-weight: 700;
    font-size: 1rem;
    padding: 12px 32px;
    border-radius: 25px;
    cursor: pointer;
    box-shadow: 0 6px 10px rgba(154,138,110,0.4);
    transition: background 0.3s ease;
    user-select: none;
    display: block;
    margin: 0 auto 2rem auto;
    width: max-content;
  }
  .logout-btn:hover {
    background: linear-gradient(135deg, #a9987c 0%, #d0ba77 100%);
  }
</style>
</head>
<body>
  <div class="container" role="main" aria-labelledby="pageTitle">
    <header>
      <div class="logo" aria-label="College Logo">
        <img src="assets/logo.png" alt="TCET College Logo" />
      </div>
      <h1 id="pageTitle">Student Dashboard</h1>
      <div style="width:120px;"><!-- spacing --></div>
    </header>

    <nav class="date-nav" aria-label="Date navigation">
      <form method="GET" style="display:inline;">
        <button type="submit" name="date" value="<?php echo htmlspecialchars($prevDate); ?>" aria-label="Previous Day">&#9664;</button>
      </form>
      <div aria-live="polite" aria-atomic="true" style="padding:0 1rem">
        <strong><?php echo date('l, F j, Y', $timestamp); ?></strong>
      </div>
      <form method="GET" style="display:inline;">
        <button type="submit" name="date" value="<?php echo htmlspecialchars($nextDate); ?>" aria-label="Next Day">&#9654;</button>
      </form>
    </nav>

    <section aria-labelledby="timetableTitle">
      <h2 id="timetableTitle" style="color:#4a4a4a; font-weight:600; margin-bottom:1rem;">Timetable & Attendance for <?php echo date('l, F j, Y', $timestamp); ?></h2>
      <?php if (count($periods) === 0): ?>
        <p>No timetable data available for this day.</p>
      <?php else: ?>
        <table aria-describedby="timetableDesc">
          <thead>
            <tr>
              <th>Period</th>
              <th>Subject</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($periods as $row):
              $status = getAttendanceStatus($pdo, $student_id, $row['timetable_id'], $selectedDate);
              $class = $status === 'Present' ? 'status-present' : ($status === 'Absent' ? 'status-absent' : 'status-not');
            ?>
              <tr>
                <td><?php echo htmlspecialchars($row['period']); ?></td>
                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                <td class="<?php echo $class; ?>"><?php echo $status; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <p class="summary">Overall Attendance till <?php echo htmlspecialchars($selectedDate); ?>: 
      <?php echo $overallPct !== '-' ? $overallPct . '%' : '-'; ?>
    </p>

    <form action="logout.php" method="post" style="display:inline;">
      <button type="submit" class="logout-btn" aria-label="Logout">Logout</button>
    </form>
  </div>
</body>
</html>
