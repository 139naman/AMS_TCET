<?php
session_start();
require 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: admin.php');
    exit();
}

$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM timetable WHERE id = ?");
$stmt->execute([$id]);
$tt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tt) {
    header('Location: admin.php');
    exit();
}

$divisions = $pdo->query("SELECT * FROM divisions ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $division_id = intval($_POST['division_id']);
    $day_of_week = $_POST['day_of_week'];
    $period = intval($_POST['period']);
    $subject_id = intval($_POST['subject_id']);
    $teacher_id = intval($_POST['teacher_id']);

    // Corrected conflict check excludes current timetable entry by ID
    $check = $pdo->prepare("SELECT id FROM timetable WHERE division_id = ? AND day_of_week = ? AND period = ? AND id != ?");
    $check->execute([$division_id, $day_of_week, $period, $id]);

    if ($check->fetch()) {
        $message = "Conflict: Timetable entry already exists for this division, day, and period.";
    } else {
        $update = $pdo->prepare("UPDATE timetable SET division_id = ?, day_of_week = ?, period = ?, subject_id = ?, teacher_id = ? WHERE id = ?");
        $update->execute([$division_id, $day_of_week, $period, $subject_id, $teacher_id, $id]);
        $message = "Timetable entry updated successfully.";
        // Refresh timetable data after update
        $stmt->execute([$id]);
        $tt = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Edit Timetable Entry</title>
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f2f5f7;
        padding: 2rem;
        color: #333;
    }
    .container {
        max-width: 480px;
        margin: 0 auto;
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgb(0 0 0 / 0.1);
    }
    h1 {
        color: #1976d2;
        margin-bottom: 1.5rem;
        font-weight: 700;
        text-align: center;
    }
    label {
        display: block;
        margin-top: 1rem;
        font-weight: 600;
    }
    input[type=number], select {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 1rem;
        box-sizing: border-box;
        margin-top: 0.2rem;
    }
    button {
        margin-top: 1.5rem;
        background: #1976d2;
        color: white;
        border: none;
        padding: 0.75rem 1.2rem;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        width: 100%;
        font-size: 1.1rem;
        transition: background 0.25s ease;
    }
    button:hover {
        background: #1565c0;
    }
    .message {
        margin-top: 1rem;
        font-weight: 700;
        color: green;
        text-align: center;
    }
    a.back-link {
        display: inline-block;
        margin-top: 1rem;
        text-decoration: none;
        color: #1976d2;
        font-weight: 700;
    }
</style>
</head>
<body>
<div class="container">
    <h1>Edit Timetable Entry</h1>
    <form method="post">
        <label for="division_id">Division</label>
        <select name="division_id" id="division_id" required>
            <?php foreach ($divisions as $div): ?>
                <option value="<?= $div['id'] ?>" <?= $tt['division_id'] == $div['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($div['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="day_of_week">Day of Week</label>
        <select name="day_of_week" id="day_of_week" required>
            <?php 
            $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            foreach ($days as $d):
            ?>
                <option value="<?= $d ?>" <?= $tt['day_of_week'] === $d ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
        </select>

        <label for="period">Period (1 to 6)</label>
        <input type="number" name="period" id="period" min="1" max="6" required value="<?= htmlspecialchars($tt['period']) ?>" />

        <label for="subject_id">Subject</label>
        <select name="subject_id" id="subject_id" required>
            <?php foreach ($subjects as $sub): ?>
                <option value="<?= $sub['id'] ?>" <?= $tt['subject_id'] == $sub['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sub['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="teacher_id">Teacher</label>
        <select name="teacher_id" id="teacher_id" required>
            <?php foreach ($teachers as $teacher): ?>
                <option value="<?= $teacher['id'] ?>" <?= $tt['teacher_id'] == $teacher['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($teacher['fullname']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Save Changes</button>

        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </form>
    <a href="admin.php" class="back-link">&larr; Back to Admin Dashboard</a>
</div>
</body>
</html>
