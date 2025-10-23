<?php
require_once __DIR__.'/includes/db.php';

function back(){ header("Location: courses.php"); exit; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    if ($action==='create') {
        $stmt = $conn->prepare("INSERT INTO course (course_name) VALUES (?)");
        $stmt->bind_param("s", $_POST['course_name']);
        $stmt->execute(); $stmt->close(); back();
    }
    if ($action==='update') {
        $stmt = $conn->prepare("UPDATE course SET course_name=? WHERE course_id=?");
        $stmt->bind_param("si", $_POST['course_name'], $_POST['course_id']);
        $stmt->execute(); $stmt->close(); back();
    }
}
if (($_GET['action'] ?? '')==='delete') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM course WHERE course_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute(); $stmt->close(); back();
}
back();
