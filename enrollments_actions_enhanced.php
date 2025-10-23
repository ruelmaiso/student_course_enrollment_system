<?php
require_once __DIR__.'/db.php';

function redirectBack() {
    header("Location: enrollments.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $student_id = (int)$_POST['student_id'];
        $course_id = (int)$_POST['course_id'];
        $enrollment_date = $_POST['enrollment_date'];
        
        try {
            // Get course fee and student allowance
            $course_stmt = $conn->prepare("SELECT course_fee FROM courses WHERE course_id = ?");
            $course_stmt->bind_param("i", $course_id);
            $course_stmt->execute();
            $course_fee = $course_stmt->get_result()->fetch_assoc()['course_fee'];
            $course_stmt->close();
            
            $student_stmt = $conn->prepare("SELECT allowance FROM students WHERE student_id = ?");
            $student_stmt->bind_param("i", $student_id);
            $student_stmt->execute();
            $student_allowance = $student_stmt->get_result()->fetch_assoc()['allowance'];
            $student_stmt->close();
            
            // Use stored procedure to enroll student
            $stmt = $conn->prepare("CALL enroll_student(?, ?)");
            $stmt->bind_param("ii", $student_id, $course_id);
            $stmt->execute();
            $stmt->close();
            
            // Handle payment logic
            if ($student_allowance >= $course_fee) {
                // Student has sufficient allowance - deduct course fee
                $new_allowance = $student_allowance - $course_fee;
                $update_stmt = $conn->prepare("UPDATE students SET allowance = ? WHERE student_id = ?");
                $update_stmt->bind_param("di", $new_allowance, $student_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Record payment
                $payment_stmt = $conn->prepare("INSERT INTO payments (student_id, course_id, amount, payment_date) VALUES (?, ?, ?, ?)");
                $payment_date = date('Y-m-d H:i:s');
                $payment_stmt->bind_param("iids", $student_id, $course_id, $course_fee, $payment_date);
                $payment_stmt->execute();
                $payment_stmt->close();
            } else {
                // Student doesn't have sufficient allowance - record partial payment
                $payment_stmt = $conn->prepare("INSERT INTO payments (student_id, course_id, amount, payment_date) VALUES (?, ?, ?, ?)");
                $payment_date = date('Y-m-d H:i:s');
                $payment_stmt->bind_param("iids", $student_id, $course_id, $student_allowance, $payment_date);
                $payment_stmt->execute();
                $payment_stmt->close();
                
                // Set student allowance to 0
                $update_stmt = $conn->prepare("UPDATE students SET allowance = 0 WHERE student_id = ?");
                $update_stmt->bind_param("i", $student_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            redirectBack();
            
        } catch (Exception $e) {
            // Handle duplicate enrollment error
            if (strpos($e->getMessage(), 'Duplicate enrollment') !== false) {
                redirectBack();
            } else {
                throw $e;
            }
        }
    }

    if ($action === 'update') {
        $student_id = (int)$_POST['student_id'];
        $course_id = (int)$_POST['course_id'];
        $enrollment_date = $_POST['enrollment_date'];
        
        $stmt = $conn->prepare("UPDATE enrollments SET enrollment_date=? WHERE student_id=? AND course_id=?");
        $stmt->bind_param("sii", $enrollment_date, $student_id, $course_id);
        $stmt->execute();
        $stmt->close();
        redirectBack();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'delete') {
    $sid = (int)($_GET['sid'] ?? 0);
    $cid = (int)($_GET['cid'] ?? 0);
    
    // Get course fee for refund calculation
    $course_stmt = $conn->prepare("SELECT course_fee FROM courses WHERE course_id = ?");
    $course_stmt->bind_param("i", $cid);
    $course_stmt->execute();
    $course_fee = $course_stmt->get_result()->fetch_assoc()['course_fee'];
    $course_stmt->close();
    
    // Refund the course fee to student's allowance
    $refund_stmt = $conn->prepare("UPDATE students SET allowance = allowance + ? WHERE student_id = ?");
    $refund_stmt->bind_param("di", $course_fee, $sid);
    $refund_stmt->execute();
    $refund_stmt->close();
    
    // Delete the enrollment
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE student_id=? AND course_id=?");
    $stmt->bind_param("ii", $sid, $cid);
    $stmt->execute();
    $stmt->close();
    
    redirectBack();
}

redirectBack();