<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

require 'config.php';

$teacher_id = $_SESSION['user_id'];

// Get selected date from GET or default today
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$timestamp = strtotime($selectedDate);
if ($timestamp === false) {
    $selectedDate = date('Y-m-d');
    $timestamp = strtotime($selectedDate);
}
$dayOfWeek = date('l', $timestamp);

$prevDate = date('Y-m-d', strtotime('-1 day', $timestamp));
$nextDate = date('Y-m-d', strtotime('+1 day', $timestamp));

// Fetch timetable entries for selected day
$stmt = $pdo->prepare("
    SELECT tt.id AS timetable_id, tt.period, s.name AS subject_name, d.id AS division_id, d.name AS division_name
    FROM timetable tt
    JOIN subjects s ON s.id = tt.subject_id
    JOIN divisions d ON d.id = tt.division_id
    WHERE tt.day_of_week = ?
    ORDER BY tt.period ASC, d.name ASC
");
$stmt->execute([$dayOfWeek]);
$lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);

$timetableId = $_POST['timetable_id'] ?? ($lectures[0]['timetable_id'] ?? null);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $timetableId) {
    $attendanceData = $_POST['attendance'] ?? [];
    $dateToRecord = $_POST['attendance_date'] ?? $selectedDate;

    foreach ($attendanceData as $studentId => $status) {
        $stmtInsert = $pdo->prepare("INSERT INTO attendance (student_id, timetable_id, attendance_date, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
        $stmtInsert->execute([$studentId, $timetableId, $dateToRecord, $status]);
    }

    $message = "Attendance saved for " . date('l, F j, Y', strtotime($dateToRecord));
}

// Fetch students for selected timetable division
$students = [];
$selectedDivisionId = null;
foreach ($lectures as $lecture) {
    if ($lecture['timetable_id'] == $timetableId) {
        $selectedDivisionId = $lecture['division_id'];
        break;
    }
}
if ($selectedDivisionId) {
    $stmtStudents = $pdo->prepare("SELECT id, fullname FROM users WHERE role = 'student' AND division_id = ? ORDER BY fullname ASC");
    $stmtStudents->execute([$selectedDivisionId]);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
}

function getAttendanceStatus($pdo, $studentId, $timetableId, $date) {
    $stmt = $pdo->prepare("SELECT status FROM attendance WHERE student_id = ? AND timetable_id = ? AND attendance_date = ?");
    $stmt->execute([$studentId, $timetableId, $date]);
    $status = $stmt->fetchColumn();
    return $status ?: 'not_marked';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Teacher Dashboard - Attendance Management System</title>
<style>
  * {
    box-sizing: border-box;
  }
  body, html {
    margin: 0;
    height: 100%;
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
    width: 100%;
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
  ul.timetable-list {
    list-style: none;
    padding: 0;
    margin: 0 0 1rem 0;
  }
  ul.timetable-list li {
    background: #fafafa;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 0.75rem;
    box-shadow: inset 1px 1px 3px #e7e2ce;
    display: flex;
    justify-content: space-between;
    font-weight: 600;
    color: #7e6b4f;
    cursor: pointer;
  }
  ul.timetable-list li.selected {
    background: #f0e9d2;
  }
  ul.timetable-list li span.period {
    font-weight: 700;
    color: #a9987c;
  }
  ul.timetable-list li span.division {
    font-size: 0.9rem;
    font-weight: 400;
    color: #a9987c;
  }
  .attendance-filter {
    margin-bottom: 0.75rem;
  }
  .attendance-filter input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    border-radius: 20px;
    border: 1.5px solid #dcd5c7;
    outline: none;
    font-size: 1rem;
    box-shadow: inset 0 2px 5px #eee9dc;
    transition: border-color 0.3s ease;
  }
  .attendance-filter input[type="text"]:focus {
    border-color: #a9987c;
    box-shadow: 0 0 8px 1px rgba(169,152,124,0.6);
  }
  table.attendance-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 12px;
    margin-bottom: 2rem;
  }
  table.attendance-table th, table.attendance-table td {
    padding: 12px 15px;
    text-align: left;
  }
  table.attendance-table th {
    background: #f1f1f1;
    border-radius: 12px;
    color: #666;
    font-weight: 600;
  }
  table.attendance-table tr {
    background: #fafafa;
    border-radius: 12px;
  }
  table.attendance-table tr:hover {
    background: #f0e9d2;
  }
  .attendance-radio {
    display: flex;
    gap: 1.5rem;
  }
  .attendance-radio label {
    cursor: pointer;
    font-weight: 600;
    color: #7e6b4f;
  }
  .btn-mark-all {
    background: #a9987c;
    color: white;
    border: none;
    padding: 8px 16px;
    font-weight: 700;
    border-radius: 20px;
    cursor: pointer;
    margin-bottom: 1rem;
    transition: background 0.3s ease;
  }
  .btn-mark-all:hover {
    background: #8b7e6a;
  }
  .message {
    font-weight: 700;
    color: #4caf50;
    margin-bottom: 1rem;
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
<script>
  function selectLecture(element, timetableId) {
    document.querySelectorAll('.timetable-list li').forEach(li => li.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('timetable_id_input').value = timetableId;
    document.getElementById('attendanceForm').submit();
  }

  function markAllPresent() {
    const radios = document.querySelectorAll('input[type="radio"][value="present"]');
    radios.forEach(radio => radio.checked = true);
  }

  function filterStudents() {
    const input = document.getElementById('studentSearch');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('#attendanceTable tbody tr');
    rows.forEach(row => {
      const nameCell = row.querySelector('td:first-child');
      if (nameCell.textContent.toLowerCase().includes(filter)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }
</script>
</head>
<body>
  <div class="container" role="main" aria-labelledby="pageTitle">
    <header>
      <div class="logo" aria-label="College Logo">
        <img src="assets/logo.png" alt="TCET College Logo" />
      </div>
      <h1 id="pageTitle">Teacher Dashboard</h1>
      <div style="width:120px;"></div>
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
      <h2 id="timetableTitle" style="color:#4a4a4a; font-weight:600; margin-bottom:1rem;">Timetable for <?php echo date('l, F j, Y', $timestamp); ?></h2>

      <?php if (empty($lectures)): ?>
        <p class="no-lectures">No lectures scheduled for this day.</p>
      <?php else: ?>
        <ul class="timetable-list" role="list" aria-label="Lectures today">
          <?php foreach ($lectures as $lecture): ?>
            <li
              onclick="selectLecture(this, <?php echo (int)$lecture['timetable_id']; ?>)"
              class="<?php echo ($lecture['timetable_id'] == $timetableId) ? 'selected' : ''; ?>"
              tabindex="0"
              onkeypress="if(event.key==='Enter'){ selectLecture(this, <?php echo (int)$lecture['timetable_id']; ?>); }"
              role="button"
              aria-pressed="<?php echo ($lecture['timetable_id'] == $timetableId) ? 'true' : 'false'; ?>"
            >
              <span class="period">Period <?php echo htmlspecialchars($lecture['period']); ?></span> —
              <span class="subject"><?php echo htmlspecialchars($lecture['subject_name']); ?></span>
              <span class="division"><?php echo htmlspecialchars($lecture['division_name']); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if (!empty($lectures)): ?>
    <section aria-labelledby="attendanceTitle">
      <h2 id="attendanceTitle" style="color:#4a4a4a; font-weight:600; margin-bottom:1rem;">Mark Attendance</h2>

      <?php if ($message): ?>
      <p class="message"><?php echo htmlspecialchars($message); ?></p>
      <?php endif; ?>

      <div class="attendance-filter">
        <input type="text" id="studentSearch" placeholder="Search students..." onkeyup="filterStudents()" aria-label="Search students" />
      </div>
      <button type="button" class="btn-mark-all" onclick="markAllPresent()" aria-label="Mark all students as present">Mark All as Present</button>

      <form method="POST" action="" id="attendanceForm" aria-label="Attendance form">
        <input type="hidden" name="timetable_id" id="timetable_id_input" value="<?php echo htmlspecialchars($timetableId); ?>" />
        <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selectedDate); ?>" />
        <table class="attendance-table" id="attendanceTable">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Present</th>
              <th>Absent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $student):
              $status = getAttendanceStatus($pdo, $student['id'], $timetableId, $selectedDate);
            ?>
            <tr>
              <td><?php echo htmlspecialchars($student['fullname']); ?></td>
              <td>
                <label>
                  <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="present" <?php echo ($status === 'present') ? 'checked' : ''; ?> />
                  Present
                </label>
              </td>
              <td>
                <label>
                  <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="absent" <?php echo ($status === 'absent') ? 'checked' : ''; ?> />
                  Absent
                </label>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="submit" class="logout-btn" aria-label="Save Attendance">Save Attendance</button>
      </form>
    </section>
    <?php endif; ?>

    <form action="logout.php" method="post" style="display:inline;">
      <button type="submit" class="logout-btn" aria-label="Logout">Logout</button>
    </form>
  </div>
</body>
</html>
