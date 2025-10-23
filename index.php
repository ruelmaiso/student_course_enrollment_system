<?php
require_once __DIR__.'/db.php';// Quick counts
$total_students = $conn->query("SELECT COUNT(*) AS cnt FROM students")->fetch_assoc()['cnt'] ?? 0;
$total_courses = $conn->query("SELECT COUNT(*) AS cnt FROM courses")->fetch_assoc()['cnt'] ?? 0;
$total_enrollments = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments")->fetch_assoc()['cnt'] ?? 0;
$total_payments = $conn->query("SELECT SUM(amount) AS total FROM payments")->fetch_assoc()['total'] ?? 0;
$total_allowance = $conn->query("SELECT SUM(allowance) AS total FROM students")->fetch_assoc()['total'] ?? 0;?? 0;

// Students per course
$sql = "
    SELECT c.course_name, COUNT(e.student_id) AS student_count
    FROM courses c
    LEFT JOIN enrollments e ON c.course_id = e.course_id
    GROUP BY c.course_id, c.course_name
    ORDER BY c.course_name
";
$result = $conn->query($sql);
$course_names = [];
$student_counts = [];
while ($row = $result->fetch_assoc()) {
    $course_names[] = $row['course_name'];
    $student_counts[] = (int)$row['student_count'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
  <link rel="stylesheet" href="styles_enhanced.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
  <h1>Welcome to the Student-Course Enrollment System</h1>
  <p style="color:var(--muted);font-size:16px;">
    This dashboard provides an overview of students, courses, and enrollments.
    Use the navigation links below   <div class="nav">
    <a href="students.php">Manage Students</a>
    <a href="courses.php">Manage Courses</a>
    <a href="enrollments.php">Manage Enrollments</a>
    <a href="payments.php">Manage Payments</a>
  </div>s.php">Manage Enrollments</a>
  </div>

  <!-- Stats Cards -->
  <div class="row">
    <div class="col">
      <div class="card" style="text-align:center;">
        <h2>Total Students</h2>
        <p style="font-size:32px;font-weight:bold;"><?php echo $total_students; ?></p>
      </div>
    </div>
    <div class="col">
      <div class="card" style="text-align:center;">
        <h2>Total Courses</h2>
        <p style="font-size:32px;font-weight:bold;"><?php echo $    <div class="col">
      <div class="card" style="text-align:center;">
        <h2>Total Enrollments</h2>
        <p style="font-size:32px;font-weight:bold;"><?php echo $total_enrollments; ?></p>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col">
      <div class="card" style="text-align:center;">
        <h2>Total Payments</h2>
        <p style="font-size:32px;font-weight:bold;color:var(--ok);">$<?php echo number_format($total_payments, 2); ?></p>
      </div>
    </div>
    <div class="col">
      <div class="card" style="text-align:center;">
        <h2>Total Allowance</h2>
        <p style="font-size:32px;font-weight:bold;color:var(--accent);">$<?php echo number_format($total_allowance, 2); ?></p>
      </div>
    </div>
    <div class="col">
      <div class="card" style="text-align:center;">
        <h2>System Status</h2>
        <p style="font-size:18px;font-weight:bold;color:var(--ok);">✓ Active</p>
      </div>
    </div>o $total_enrollments; ?></p>
      </div>
    </div>
  </div>

  <!-- Chart -->
  <div class="card">
    <h2>Students Per Course</h2>
    <canvas id="courseChart" height="100"></canvas>
  </div>
</div>

<script>
const ctx = document.getElementById('courseChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($course_names); ?>,
        datasets: [{
            label: 'Number of Students',
            data: <?php echo json_encode($student_counts); ?>,
            backgroundColor: 'rgba(37, 99, 235, 0.6)',
            borderColor: 'rgba(37, 99, 235, 1)',
            borderWidth: 1,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
</script>
</body>
</html>
