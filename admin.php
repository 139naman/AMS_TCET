<?php
session_start();
require 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Delete student or teacher
if (isset($_GET['delete_student_id'])) {
    $deleteId = intval($_GET['delete_student_id']);
    $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $stmtDel->execute([$deleteId]);
    header('Location: admin.php');
    exit();
}
if (isset($_GET['delete_teacher_id'])) {
    $deleteId = intval($_GET['delete_teacher_id']);
    $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
    $stmtDel->execute([$deleteId]);
    header('Location: admin.php');
    exit();
}
// Delete timetable entry
if (isset($_GET['delete_tt_id'])) {
    $deleteId = intval($_GET['delete_tt_id']);
    $stmtDel = $pdo->prepare("DELETE FROM timetable WHERE id = ?");
    $stmtDel->execute([$deleteId]);
    header('Location: admin.php');
    exit();
}

// Fetch Divisions
$divisions = $pdo->query("SELECT * FROM divisions ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle add user form
$userMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    $divisionId = ($role === 'student') ? intval($_POST['division_id']) : null;

    if ($username && $fullname && $password && $role) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $userMessage = "Username already exists.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, fullname, password, role, division_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $fullname, password_hash($password, PASSWORD_DEFAULT), $role, $divisionId]);
            $userMessage = "User added successfully.";
        }
    } else {
        $userMessage = "Please fill all required user fields.";
    }
}

// Handle add timetable form
$ttMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_timetable'])) {
    $divisionId = intval($_POST['tt_division_id']);
    $dayOfWeek = $_POST['tt_day'];
    $period = intval($_POST['tt_period']);
    $subjectId = intval($_POST['tt_subject_id']);
    $teacherId = intval($_POST['tt_teacher_id']);

    if ($divisionId && $dayOfWeek && $period && $subjectId && $teacherId) {
        $checkTT = $pdo->prepare("SELECT id FROM timetable WHERE division_id = ? AND day_of_week = ? AND period = ?");
        $checkTT->execute([$divisionId, $dayOfWeek, $period]);
        if ($checkTT->fetch()) {
            $ttMessage = "Timetable entry already exists for this division, day, and period.";
        } else {
            $insertTT = $pdo->prepare("INSERT INTO timetable (division_id, day_of_week, period, subject_id, teacher_id) VALUES (?, ?, ?, ?, ?)");
            $insertTT->execute([$divisionId, $dayOfWeek, $period, $subjectId, $teacherId]);
            $ttMessage = "Timetable entry added.";
        }
    } else {
        $ttMessage = "Please fill all timetable fields.";
    }
}

// Fetch users and timetable entries
$students = $pdo->query("SELECT u.*, d.name AS division_name FROM users u LEFT JOIN divisions d ON u.division_id = d.id WHERE role = 'student' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$timetables = $pdo->query("SELECT tt.*, d.name AS division_name, s.name AS subject_name, u.fullname AS teacher_name 
    FROM timetable tt 
    JOIN divisions d ON tt.division_id = d.id 
    JOIN subjects s ON tt.subject_id = s.id 
    JOIN users u ON tt.teacher_id = u.id 
    ORDER BY division_name, FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), period")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin Dashboard</title>
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        background: linear-gradient(135deg, #f9f6ee, #fffefa);
        color: #333;
    }
    .header {
        position: sticky;
        top: 0;
        background: rgba(255, 255, 255, 0.95);
        z-index: 1000;
        padding: 0.75rem 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .logo {
        width: 140px;
    }
    .logo img {
        width: 100%;
        user-select: none;
    }
    h1 {
        font-weight: 700;
        font-size: 1.9rem;
        color: #4a4a4a;
        text-align: center;
        flex-grow: 1;
        margin: 0 2rem;
    }
    .logout-btn {
        background: linear-gradient(135deg, #d0ba77 0%, #a9987c 100%);
        border: none;
        color: #3b3b3b;
        font-weight: 700;
        font-size: 1rem;
        padding: 8px 20px;
        border-radius: 22px;
        cursor: pointer;
        box-shadow: 0 6px 10px rgba(154,138,110,0.4);
        transition: background 0.3s ease;
        user-select: none;
        white-space: nowrap;
    }
    .logout-btn:hover {
        background: linear-gradient(135deg, #a9987c 0%, #d0ba77 100%);
    }
    .container {
        max-width: 1200px;
        margin: 3rem auto 3rem;
        padding: 0 1rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(540px, 1fr));
        gap: 2rem;
    }
    section {
        background: rgba(255,255,255,0.85);
        border-radius: 20px;
        box-shadow: 0 15px 30px rgba(0,0,0,0.12);
        padding: 2rem 2.5rem;
        max-height: 600px;
        overflow-y: auto;
    }
    h2 {
        margin-top: 0;
        color: #4a4a4a;
        margin-bottom: 1.5rem;
        font-weight: 700;
    }
    form label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    form input[type=text], form input[type=password], form select, form input[type=number] {
        width: 100%;
        padding: 0.5rem 0.8rem;
        border-radius: 8px;
        border: 1px solid #ccc;
        box-sizing: border-box;
        font-size: 1rem;
        margin-bottom: 1rem;
    }
    form button {
        background: #a9987c;
        color: #3b3b3b;
        padding: 0.75rem 15px;
        border: none;
        border-radius: 15px;
        font-weight: 700;
        cursor: pointer;
        font-size: 1.1rem;
        transition: background 0.25s ease;
    }
    form button:hover {
        background: #8b7e6a;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
    }
    th, td {
        border-bottom: 1px solid #dcdcdc;
        padding: 0.5rem 0.8rem;
        text-align: left;
    }
    th {
        background-color: #f1f1f1;
        color: #a9987c;
        font-weight: 700;
    }
    tr:hover {
        background-color: #f0e9d2;
    }
    .message {
        color: green;
        font-weight: 700;
        margin-top: 0.5rem;
    }
    a.action-link {
        color: #a9987c;
        cursor: pointer;
        text-decoration: underline;
        margin-right: 1rem;
    }
    @media (max-width: 1000px) {
        .container {
            grid-template-columns: 1fr;
        }
    }
</style>
<script>
function toggleDivision(role) {
    const label = document.getElementById('division-label');
    const select = document.getElementById('division_id');
    if (role === 'student') {
        label.style.display = 'block';
        select.style.display = 'block';
    } else {
        label.style.display = 'none';
        select.style.display = 'none';
    }
}
</script>
</head>
<body>
<header class="header">
    <div class="logo" aria-label="College Logo">
        <img src="assets/logo.png" alt="TCET College Logo" />
    </div>
    <h1>Admin Dashboard</h1>
    <form action="logout.php" method="post" style="margin:0;">
        <button type="submit" class="logout-btn" aria-label="Logout">Logout</button>
    </form>
</header>

<div class="container">

    <section>
        <h2>Add / Manage Users</h2>
        <form method="post">
            <input type="hidden" name="add_user" value="1" />
            <label for="username">Username (Email)</label>
            <input type="text" name="username" id="username" required />
            <label for="fullname">Full Name</label>
            <input type="text" name="fullname" id="fullname" required />
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required />
            <label for="role">Role</label>
            <select name="role" id="role" required onchange="toggleDivision(this.value)">
                <option value="">Select role</option>
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
                <option value="admin">Admin</option>
            </select>
            <label for="division_id" id="division-label" style="display:none;">Division</label>
            <select name="division_id" id="division_id" style="display:none;">
                <?php foreach ($divisions as $div): ?>
                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Add User</button>
            <?php if ($userMessage): ?>
                <div class="message"><?= htmlspecialchars($userMessage) ?></div>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>Add Timetable Entry</h2>
        <form method="post">
            <input type="hidden" name="add_timetable" value="1" />
            <label for="tt_division_id">Division</label>
            <select name="tt_division_id" id="tt_division_id" required>
                <?php foreach ($divisions as $div): ?>
                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="tt_day">Day of Week</label>
            <select name="tt_day" id="tt_day" required>
                <option value="">Select Day</option>
                <option>Monday</option><option>Tuesday</option><option>Wednesday</option>
                <option>Thursday</option><option>Friday</option><option>Saturday</option>
            </select>
            <label for="tt_period">Period (1 to 6)</label>
            <input type="number" name="tt_period" id="tt_period" min="1" max="6" required />
            <label for="tt_subject_id">Subject</label>
            <select name="tt_subject_id" id="tt_subject_id" required>
                <?php foreach ($subjects as $sub): ?>
                    <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="tt_teacher_id">Teacher</label>
            <select name="tt_teacher_id" id="tt_teacher_id" required>
                <?php foreach ($teachers as $teacher): ?>
                    <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['fullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Add to Timetable</button>
            <?php if ($ttMessage): ?>
                <div class="message"><?= htmlspecialchars($ttMessage) ?></div>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>Students (Division Assigned)</h2>
        <div style="max-height: 280px; overflow-y: auto; border: 1px solid #ccc; border-radius: 8px;">
            <table>
                <thead><tr><th>Name</th><th>Username</th><th>Division</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($students as $stu): ?>
                        <tr>
                            <td><?= htmlspecialchars($stu['fullname']) ?></td>
                            <td><?= htmlspecialchars($stu['username']) ?></td>
                            <td><?= htmlspecialchars($stu['division_name'] ?: '-') ?></td>
                            <td>
                                <a href="edit_user.php?id=<?= $stu['id'] ?>" class="action-link">Edit</a> |
                                <a href="admin.php?delete_student_id=<?= $stu['id'] ?>" onclick="return confirm('Are you sure you want to delete this student?');" class="action-link">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2>Teachers</h2>
        <div style="max-height: 280px; overflow-y: auto; border: 1px solid #ccc; border-radius: 8px;">
            <table>
                <thead><tr><th>Name</th><th>Username</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?= htmlspecialchars($teacher['fullname']) ?></td>
                            <td><?= htmlspecialchars($teacher['username']) ?></td>
                            <td>
                                <a href="edit_user.php?id=<?= $teacher['id'] ?>" class="action-link">Edit</a> |
                                <a href="admin.php?delete_teacher_id=<?= $teacher['id'] ?>" onclick="return confirm('Are you sure you want to delete this teacher?');" class="action-link">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section style="grid-column: 1 / -1;">
        <h2>Timetable Entries</h2>
        <div style="max-height: 280px; overflow-y: auto; border: 1px solid #ccc; border-radius: 8px;">
            <table>
                <thead><tr><th>Division</th><th>Day</th><th>Period</th><th>Subject</th><th>Teacher</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($timetables as $tt): ?>
                        <tr>
                            <td><?= htmlspecialchars($tt['division_name']) ?></td>
                            <td><?= htmlspecialchars($tt['day_of_week']) ?></td>
                            <td><?= htmlspecialchars($tt['period']) ?></td>
                            <td><?= htmlspecialchars($tt['subject_name']) ?></td>
                            <td><?= htmlspecialchars($tt['teacher_name']) ?></td>
                            <td>
                                <a href="edit_timetable.php?id=<?= $tt['id'] ?>" class="action-link">Edit</a> |
                                <a href="admin.php?delete_tt_id=<?= $tt['id'] ?>" onclick="return confirm('Are you sure you want to delete this timetable entry?');" class="action-link">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
