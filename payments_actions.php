<?php
require_once __DIR__.'/db.php';

function redirectBack() {
    header("Location: payments.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = $conn->prepare("INSERT INTO payments (student_id, course_id, amount, payment_date) VALUES (?,?,?,?)");
        $stmt->bind_param("iids",
            $_POST['student_id'],
            $_POST['course_id'],
            $_POST['amount'],
            $_POST['payment_date']
        );
        
        if ($stmt->execute()) {
            // Update student's allowance if payment exceeds course fee
            $course_fee = $conn->query("SELECT course_fee FROM courses WHERE course_id = " . (int)$_POST['course_id'])->fetch_assoc()['course_fee'];
            $payment_amount = (float)$_POST['amount'];
            
            if ($payment_amount > $course_fee) {
                $excess = $payment_amount - $course_fee;
                $update_stmt = $conn->prepare("UPDATE students SET allowance = allowance + ? WHERE student_id = ?");
                $update_stmt->bind_param("di", $excess, $_POST['student_id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            $stmt->close();
            redirectBack();
        } else {
            $stmt->close();
            redirectBack();
        }
    }

    if ($action === 'update') {
        $stmt = $conn->prepare("UPDATE payments SET student_id=?, course_id=?, amount=?, payment_date=? WHERE payment_id=?");
        $stmt->bind_param("iidsi",
            $_POST['student_id'],
            $_POST['course_id'],
            $_POST['amount'],
            $_POST['payment_date'],
            $_POST['payment_id']
        );
        $stmt->execute();
        $stmt->close();
        redirectBack();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM payments WHERE payment_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    redirectBack();
}

redirectBack();