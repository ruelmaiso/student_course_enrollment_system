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
    FROM payments p
    JOIN students s ON p.student_id = s.student_id
    JOIN courses c ON p.course_id = c.course_id
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

// Load payments with pagination
$payments_sql = "
    SELECT p.*, s.name as student_name, c.course_name, c.course_fee
    FROM payments p
    JOIN students s ON p.student_id = s.student_id
    JOIN courses c ON p.course_id = c.course_id
    $search_conditions
    ORDER BY p.payment_date DESC
    LIMIT ? OFFSET ?
";
$payments_stmt = $conn->prepare($payments_sql);
if (!empty($search_params)) {
    $payments_stmt->bind_param("ssii", $search_params[0], $search_params[1], $per_page, $offset);
} else {
    $payments_stmt->bind_param("ii", $per_page, $offset);
}
$payments_stmt->execute();
$payments = $payments_stmt->get_result();
$payments_stmt->close();

// Load students and courses for dropdowns
$students = $conn->query("SELECT student_id, name FROM students ORDER BY name ASC");
$courses = $conn->query("SELECT course_id, course_name, course_fee FROM courses ORDER BY course_name ASC");

// Get payment statistics
$total_payments = $conn->query("SELECT SUM(amount) as total FROM payments")->fetch_assoc()['total'] ?? 0;
$monthly_payments = $conn->query("
    SELECT SUM(amount) as total 
    FROM payments 
    WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(payment_date) = YEAR(CURRENT_DATE())
")->fetch_assoc()['total'] ?? 0;

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("
        SELECT p.*, s.name as student_name, c.course_name 
        FROM payments p
        JOIN students s ON p.student_id = s.student_id
        JOIN courses c ON p.course_id = c.course_id
        WHERE p.payment_id = ?
    ");
    $stmt->bind_param("i", $id);
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
  <title>Payments</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
  <h1>Payments</h1>
  <div class="nav">
    <a href="index.php">Home</a>
    <a href="students.php">Students</a>
    <a href="courses.php">Courses</a>
    <a href="enrollments.php">Enrollments</a>
  </div>

  <!-- Payment Statistics -->
  <div class="row">
    <div class="col">
      <div class="card" style="text-align:center;">
        <h2>Total Payments</h2>
        <p style="font-size:32px;font-weight:bold;color:var(--ok);">$<?php echo number_format($total_payments, 2); ?></p>
      </div>
    </div>
    <div class="col">
      <div class="card" style="text-align:center;">
        <h2>This Month</h2>
        <p style="font-size:32px;font-weight:bold;color:var(--accent);">$<?php echo number_format($monthly_payments, 2); ?></p>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col">
      <div class="card">
        <h2><?php echo $edit ? 'Edit Payment' : 'Add Payment'; ?></h2>
        <form method="post" action="payments_actions.php">
          <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
          <?php if ($edit): ?>
            <input type="hidden" name="payment_id" value="<?php echo (int)$edit['payment_id']; ?>">
          <?php endif; ?>
          <div class="grid">
            <div>
              <label>Student</label>
              <select class="select" name="student_id" required onchange="updateCourseOptions()">
                <option value="">-- choose student --</option>
                <?php 
                $students->data_seek(0); // Reset cursor
                while ($student = $students->fetch_assoc()): 
                ?>
                  <option value="<?php echo (int)$student['student_id']; ?>" 
                          <?php echo $edit && $edit['student_id'] == $student['student_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($student['name']); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div>
              <label>Course</label>
              <select class="select" name="course_id" required onchange="updateAmount()">
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
            <div>
              <label>Amount</label>
              <input class="input" type="number" step="0.01" min="0" name="amount" 
                     value="<?php echo htmlspecialchars($edit['amount'] ?? '', ENT_QUOTES); ?>" required>
            </div>
            <div>
              <label>Payment Date</label>
              <input class="input" type="datetime-local" name="payment_date" 
                     value="<?php echo $edit ? date('Y-m-d\TH:i', strtotime($edit['payment_date'])) : date('Y-m-d\TH:i'); ?>" required>
            </div>
          </div>
          <div style="margin-top:12px; display:flex; gap:8px;">
            <button class="btn" type="submit"><?php echo $edit ? 'Update' : 'Create'; ?></button>
            <?php if ($edit): ?>
              <a class="btn secondary" href="payments.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="col">
      <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h2>All Payments</h2>
          <div style="display: flex; gap: 8px; align-items: center;">
            <span class="badge"><?php echo $total_records; ?> total</span>
            <span class="badge">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
          </div>
        </div>

        <!-- Search and Pagination Controls -->
        <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
          <form method="get" style="display: flex; gap: 8px; flex: 1; min-width: 300px;">
            <input class="input" type="text" name="search" placeholder="Search payments..." 
                   value="<?php echo htmlspecialchars($search_term); ?>" style="flex: 1;">
            <button class="btn" type="submit">
              <i class="fas fa-search"></i>
            </button>
            <?php if (!empty($search_term)): ?>
              <a class="btn secondary" href="payments.php">
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

        <?php if ($payments && $payments->num_rows): ?>
          <table>
            <thead>
              <tr>
                <th>Student</th><th>Course</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($row = $payments->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                <td>
                  <span class="payment-amount">$<?php echo number_format($row['amount'], 2); ?></span>
                  <?php if ($row['amount'] < $row['course_fee']): ?>
                    <span class="badge warning">Partial</span>
                  <?php elseif ($row['amount'] > $row['course_fee']): ?>
                    <span class="badge success">Overpaid</span>
                  <?php else: ?>
                    <span class="badge success">Complete</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($row['amount'] < $row['course_fee']): ?>
                    <span class="badge warning">Partial</span>
                  <?php elseif ($row['amount'] > $row['course_fee']): ?>
                    <span class="badge success">Overpaid</span>
                  <?php else: ?>
                    <span class="badge success">Complete</span>
                  <?php endif; ?>
                </td>
                <td><?php echo date('M j, Y g:i A', strtotime($row['payment_date'])); ?></td>
                <td class="actions">
                  <a class="btn" href="?edit=<?php echo (int)$row['payment_id']; ?>">Edit</a>
                  <a class="btn danger" href="payments_actions.php?action=delete&id=<?php echo (int)$row['payment_id']; ?>"
                     onclick="return confirm('Delete this payment?');">Delete</a>
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
              No payments found matching "<?php echo htmlspecialchars($search_term); ?>"
            <?php else: ?>
              No payments yet.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function updateCourseOptions() {
  // This function can be enhanced to filter courses based on student
}

function updateAmount() {
  // This function can be enhanced to auto-fill amount based on course fee
}

function changePerPage(perPage) {
  const url = new URL(window.location);
  url.searchParams.set('per_page', perPage);
  url.searchParams.set('page', '1');
  window.location.href = url.toString();
}
</script>
</body>
</html>