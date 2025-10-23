<?php
require_once __DIR__.'/db.php';

function back(){ header("Location: courses.php"); exit; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    if ($action==='create') {
        $stmt = $conn->prepare("INSERT INTO courses (course_name, course_fee) VALUES (?,?)");
        $stmt->bind_param("sd", $_POST['course_name'], $_POST['course_fee']);
        $stmt->execute(); 
        $stmt->close(); 
        back();
    }
    if ($action==='update') {
        $stmt = $conn->prepare("UPDATE courses SET course_name=?, course_fee=? WHERE course_id=?");
        $stmt->bind_param("sdi", $_POST['course_name'], $_POST['course_fee'], $_POST['course_id']);
        $stmt->execute(); 
        $stmt->close(); 
        back();
    }
}
if (($_GET['action'] ?? '')==='delete') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute(); 
    $stmt->close(); 
    back();
}
back();