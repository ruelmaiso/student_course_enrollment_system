<?php
require_once __DIR__.'/db.php';

// Load students (only those not already enrolled)
$students_sql = "
    SELECT s.student_id, s.name, s.allowance
    FROM students s
    WHERE NOT EXISTS (
        SELECT 1 FROM enrollments e 
        WHERE e.student_id = s.student_id
    )
    ORDER BY s.name ASC";
$students = $conn->query($students_sql);

// Load courses with fees
$courses = $conn->query("SELECT course_id, course_name, course_fee FROM courses ORDER BY course_name ASC");

// Load enrollments with student and course names, and financial info
$sql = "
  SELECT e.student_id, e.course_id, e.enrollment_date,
         s.name AS student_name, s.allowance, c.course_name, c.course_fee
  FROM enrollments e
  JOIN students s ON s.student_id = e.student_id
  JOIN courses   c ON c.course_id   = e.course_id
  ORDER BY s.name, c.course_name
";
$enrollments = $conn->query($sql);

// Edit mode (optional)
$edit = null;
if (isset($_GET['edit'])) {
    $student_id = (int)$_GET['student_id'];
    $course_id = (int)$_GET['course_id'];
    $stmt = $conn->prepare("SELECT student_id, course_id, enrollment_date FROM enrollments WHERE student_id=? AND course_id=?");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Enrollments</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
  <h1>Enrollments</h1>
  <div class="nav">
    <a href="index.php">Home</a>
    <a href="students.php">Students</a>
    <a href="courses.php">Courses</a>
    <a href="payments.php">Payments</a>
    <a href="search.php">Search</a>
  </div>

  <div class="row">
    <div class="col">
      <div class="card">
        <h2><?php echo $edit ? 'Edit Enrollment' : 'Add Enrollment'; ?></h2>
        <form method="post" action="enrollments_actions.php" id="enrollmentForm">
          <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
          <?php if ($edit): ?>
            <input type="hidden" name="student_id" value="<?php echo (int)$edit['student_id']; ?>">
            <input type="hidden" name="course_id" value="<?php echo (int)$edit['course_id']; ?>">
          <?php endif; ?>
          <div class="grid">
            <div>
              <label>Student</label>
              <select class="select" name="student_id" required onchange="updateStudentInfo()">
                <option value="">-- choose student --</option>
                <?php while ($student = $students->fetch_assoc()): ?>
                  <option value="<?php echo (int)$student['student_id']; ?>" 
                          data-allowance="<?php echo $student['allowance']; ?>"
                          <?php echo $edit && $edit['student_id'] == $student['student_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($student['name']); ?> (Allowance: $<?php echo number_format($student['allowance'], 2); ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div>
              <label>Course</label>
              <select class="select" name="course_id" required onchange="updateCourseInfo()">
                <option value="">-- choose course --</option>
                <?php 
                $courses->data_seek(0); // Reset cursor
                while ($course = $courses->fetch_assoc()): 
                ?>
                  <option value="<?php echo (int)$course['course_id']; ?>" 
                          data-fee="<?php echo $course['course_fee']; ?>"
                          <?php echo $edit && $edit['course_id'] == $course['course_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($course['course_name']); ?> (Fee: $<?php echo number_format($course['course_fee'], 2); ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <!-- Payment Information Section -->
          <div class="full" id="paymentInfo" style="display: none;">
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
              <h4 style="margin: 0 0 12px; color: var(--text);">Payment Information</h4>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                  <label style="font-size: 14px; font-weight: 600;">Student Allowance</label>
                  <div id="studentAllowance" style="font-size: 18px; font-weight: bold; color: var(--ok);">$0.00</div>
                </div>
                <div>
                  <label style="font-size: 14px; font-weight: 600;">Course Fee</label>
                  <div id="courseFee" style="font-size: 18px; font-weight: bold; color: var(--accent);">$0.00</div>
                </div>
              </div>
              <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                <label style="font-size: 14px; font-weight: 600;">Payment Required</label>
                <div id="paymentRequired" style="font-size: 20px; font-weight: bold; color: var(--danger);">$0.00</div>
                <small style="color: var(--muted);">If student has sufficient allowance, it will be deducted automatically</small>
              </div>
            </div>
          </div>

          <div style="margin-top:12px; display:flex; gap:8px;">
            <button class="btn" type="submit"><?php echo $edit ? 'Update' : 'Create'; ?></button>
            <?php if ($edit): ?>
              <a class="btn secondary" href="enrollments.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="col">
      <div class="card">
        <h2>All Enrollments</h2>
        <?php if ($enrollments && $enrollments->num_rows): ?>
          <table>
            <thead>
              <tr>
                <th>Student</th><th>Course</th><th>Fee</th><th>Allowance</th><th>Status</th><th>Date</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($row = $enrollments->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                <td>$<?php echo number_format($row['course_fee'], 2); ?></td>
                <td>$<?php echo number_format($row['allowance'], 2); ?></td>
                <td>
                  <?php if ($row['allowance'] >= $row['course_fee']): ?>
                    <span class="badge success">Sufficient</span>
                  <?php else: ?>
                    <span class="badge warning">Insufficient</span>
                  <?php endif; ?>
                </td>
                <td><?php echo date('M j, Y', strtotime($row['enrollment_date'])); ?></td>
                <td class="actions">
                  <a class="btn" href="?edit=1&student_id=<?php echo (int)$row['student_id']; ?>&course_id=<?php echo (int)$row['course_id']; ?>">Edit</a>
                  <a class="btn danger" href="enrollments_actions.php?action=delete&student_id=<?php echo (int)$row['student_id']; ?>&course_id=<?php echo (int)$row['course_id']; ?>"
                     onclick="return confirm('Delete this enrollment?');">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty">No enrollments yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
function updateStudentInfo() {
  updatePaymentInfo();
}

function updateCourseInfo() {
  updatePaymentInfo();
}

function updatePaymentInfo() {
  const studentSelect = document.querySelector('select[name="student_id"]');
  const courseSelect = document.querySelector('select[name="course_id"]');
  const paymentInfo = document.getElementById('paymentInfo');
  
  if (studentSelect.value && courseSelect.value) {
    const studentAllowance = parseFloat(studentSelect.options[studentSelect.selectedIndex].getAttribute('data-allowance')) || 0;
    const courseFee = parseFloat(courseSelect.options[courseSelect.selectedIndex].getAttribute('data-fee')) || 0;
    
    document.getElementById('studentAllowance').textContent = '$' + studentAllowance.toFixed(2);
    document.getElementById('courseFee').textContent = '$' + courseFee.toFixed(2);
    
    const paymentRequired = Math.max(0, courseFee - studentAllowance);
    document.getElementById('paymentRequired').textContent = '$' + paymentRequired.toFixed(2);
    
    paymentInfo.style.display = 'block';
  } else {
    paymentInfo.style.display = 'none';
  }
}

function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  
  document.getElementById('toastContainer').appendChild(toast);
  
  setTimeout(() => {
    toast.classList.add('show');
  }, 100);
  
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => {
      document.getElementById('toastContainer').removeChild(toast);
    }, 300);
  }, 3000);
}

// Initialize payment info if editing
document.addEventListener('DOMContentLoaded', function() {
  updatePaymentInfo();
});
</script>
</body>
</html>