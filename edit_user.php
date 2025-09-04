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

$userId = intval($_GET['id']);

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: admin.php');
    exit();
}

// Fetch divisions for dropdown
$divisions = $pdo->query("SELECT * FROM divisions ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $divisionId = ($role === 'student') ? intval($_POST['division_id']) : null;

    if ($fullname && $role) {
        if ($password) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET fullname = ?, role = ?, division_id = ?, password = ? WHERE id = ?");
            $stmtUpdate->execute([$fullname, $role, $divisionId, $passwordHash, $userId]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE users SET fullname = ?, role = ?, division_id = ? WHERE id = ?");
            $stmtUpdate->execute([$fullname, $role, $divisionId, $userId]);
        }
        $message = "User updated successfully.";
        // Refresh user data
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $message = "Please fill all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Edit User</title>
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
    input[type=text], input[type=password], select {
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
document.addEventListener('DOMContentLoaded', function() {
    toggleDivision('<?= $user['role'] ?>');
});
</script>
</head>
<body>
<div class="container">
    <h1>Edit User - <?= htmlspecialchars($user['username']) ?></h1>

    <form method="post">
        <label for="fullname">Full Name</label>
        <input type="text" name="fullname" id="fullname" required value="<?= htmlspecialchars($user['fullname']) ?>" />

        <label for="password">Password (leave blank to keep unchanged)</label>
        <input type="password" name="password" id="password" />

        <label for="role">Role</label>
        <select name="role" id="role" onchange="toggleDivision(this.value)" required>
            <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>

        <label for="division_id" id="division-label" style="display:none;">Division</label>
        <select name="division_id" id="division_id" style="display:none;">
            <?php foreach ($divisions as $div): ?>
                <option value="<?= $div['id'] ?>" <?= $user['division_id'] == $div['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($div['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Save Changes</button>
    </form>
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <a href="admin.php" class="back-link">&larr; Back to Admin Dashboard</a>
</div>
</body>
</html>
