
<?php
require_once __DIR__.'/db.php';

function redirectBack() {
    header("Location: students.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = $conn->prepare("INSERT INTO students (name, age, email, address, allowance) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sissd",
            $_POST['name'],
            $_POST['age'],
            $_POST['email'],
            $_POST['address'],
            $_POST['allowance']
        );
        $stmt->execute();
        $stmt->close();
        redirectBack();
    }

    if ($action === 'update') {
        $stmt = $conn->prepare("UPDATE students SET name=?, age=?, email=?, address=?, allowance=? WHERE student_id=?");
        $stmt->bind_param("sissdi",
            $_POST['name'],
            $_POST['age'],
            $_POST['email'],
            $_POST['address'],
            $_POST['allowance'],
            $_POST['student_id']
        );
        $stmt->execute();
        $stmt->close();
        redirectBack();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    // If you want to prevent deleting students with enrollments, you can check first.
    $stmt = $conn->prepare("DELETE FROM students WHERE student_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    redirectBack();
}

redirectBack();