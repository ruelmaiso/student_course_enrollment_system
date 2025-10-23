<?php
require_once __DIR__.'/db.php';

// Get search term and pagination
$search_term = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(5, min(100, (int)($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $per_page;

// Build search conditions
$search_conditions = '';
$search_params = [];
if (!empty($search_term)) {
    $search_conditions = "WHERE s.name LIKE ? OR c.course_name LIKE ?";
    $search_params = ["%$search_term%", "%$search_term%"];
}

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM enrollments e
    JOIN students s ON s.student_id = e.student_id
    JOIN courses c ON c.course_id = e.course_id
    $search_conditions
";
if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("ss", $search_params[0], $search_params[1]);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
}

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

// Load enrollments with pagination
$enrollments_sql = "
  SELECT e.student_id, e.course_id, e.enrollment_date,
         s.name AS student_name, s.allowance, c.course_name, c.course_fee
  FROM enrollments e
  JOIN students s ON s.student_id = e.student_id
  JOIN courses c ON c.course_id = e.course_id
  $search_conditions
  ORDER BY s.name, c.course_name
  LIMIT ? OFFSET ?
";
$enrollments_stmt = $conn->prepare($enrollments_sql);
if (!empty($search_params)) {
    $enrollments_stmt->bind_param("ssii", $search_params[0], $search_params[1], $per_page, $offset);
} else {
    $enrollments_stmt->bind_param("ii", $per_page, $offset);
}
$enrollments_stmt->execute();
$enrollments = $enrollments_stmt->get_result();
$enrollments_stmt->close();

// Edit mode
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

$total_pages = max(1, ceil($total_records / $per_page));
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
  </div>

  <div class="row">
    <div class="col">
      <div class="card">
        <h2><?php echo $edit ? 'Edit Enrollment' : 'Add Enrollment'; ?></h2>
        <form method="post" action="enrollments_actions.php">
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h2>All Enrollments</h2>
          <div style="display: flex; gap: 8px; align-items: center;">
            <span class="badge"><?php echo $total_records; ?> total</span>
            <span class="badge">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
          </div>
        </div>

        <!-- Search and Pagination Controls -->
        <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
          <form method="get" style="display: flex; gap: 8px; flex: 1; min-width: 300px;">
            <input class="input" type="text" name="search" placeholder="Search enrollments..." 
                   value="<?php echo htmlspecialchars($search_term); ?>" style="flex: 1;">
            <button class="btn" type="submit">
              <i class="fas fa-search"></i>
            </button>
            <?php if (!empty($search_term)): ?>
              <a class="btn secondary" href="enrollments.php">
                <i class="fas fa-times"></i>
              </a>
            <?php endif; ?>
          </form>
          
          <select class="select" onchange="changePerPage(this.value)" style="width: auto;">
            <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10 per page</option>
            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25 per page</option>
            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50 per page</option>
          </select>
        </div>

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

          <!-- Pagination -->
          <div style="margin-top: 20px; display: flex; justify-content: center;">
            <?php
            if ($total_pages > 1) {
                echo '<div class="pagination">';
                
                if ($page > 1) {
                    $prev_params = array_merge($_GET, ['page' => $page - 1]);
                    echo '<a href="?' . http_build_query($prev_params) . '" class="pagination-link">&laquo; Previous</a>';
                }
                
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $page_params = array_merge($_GET, ['page' => $i]);
                    $class = $i == $page ? 'pagination-link active' : 'pagination-link';
                    echo '<a href="?' . http_build_query($page_params) . '" class="' . $class . '">' . $i . '</a>';
                }
                
                if ($page < $total_pages) {
                    $next_params = array_merge($_GET, ['page' => $page + 1]);
                    echo '<a href="?' . http_build_query($next_params) . '" class="pagination-link">Next &raquo;</a>';
                }
                
                echo '</div>';
            }
            ?>
          </div>
        <?php else: ?>
          <div class="empty">
            <?php if (!empty($search_term)): ?>
              No enrollments found matching "<?php echo htmlspecialchars($search_term); ?>"
            <?php else: ?>
              No enrollments yet.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

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

function changePerPage(perPage) {
  const url = new URL(window.location);
  url.searchParams.set('per_page', perPage);
  url.searchParams.set('page', '1');
  window.location.href = url.toString();
}

// Initialize payment info if editing
document.addEventListener('DOMContentLoaded', function() {
  updatePaymentInfo();
});
</script>
</body>
</html>