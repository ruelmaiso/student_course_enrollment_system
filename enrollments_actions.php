<?php
require_once __DIR__.'/db.php';
function back(){ header("Location: enrollments.php"); exit; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if ($action==='create') {
        // Prevent duplicates under composite PK by handling potential errors gracefully
        $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date) VALUES (?,?,?)");
        $stmt->bind_param("iis", $_POST['student_id'], $_POST['course_id'], $_POST['enrollment_date']);
        $stmt->execute();
        // You might check $stmt->errno == 1062 for duplicate key
        $stmt->close(); back();
    }

    if ($action==='update') {
        $stmt = $conn->prepare("UPDATE enrollments SET enrollment_date=? WHERE student_id=? AND course_id=?");
        $stmt->bind_param("sii", $_POST['enrollment_date'], $_POST['student_id'], $_POST['course_id']);
        $stmt->execute(); $stmt->close(); back();
    }
}

if (($_GET['action'] ?? '')==='delete') {
    $sid = (int)($_GET['sid'] ?? 0);
    $cid = (int)($_GET['cid'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE student_id=? AND course_id=?");
    $stmt->bind_param("ii", $sid, $cid);
    $stmt->execute(); $stmt->close(); back();
}
