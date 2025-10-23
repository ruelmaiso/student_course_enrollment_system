<?php
require_once __DIR__.'/db.php';

// Get search term and pagination
$search_term = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 7; // Fixed to 7 records per page
$offset = ($page - 1) * $per_page;

// Build search conditions
$search_conditions = '';
$search_params = [];
if (!empty($search_term)) {
    $search_conditions = "WHERE name LIKE ? OR email LIKE ? OR address LIKE ?";
    $search_params = ["%$search_term%", "%$search_term%", "%$search_term%"];
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM students $search_conditions";
if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("sss", $search_params[0], $search_params[1], $search_params[2]);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
}

// Load students with pagination
$students_sql = "SELECT student_id, name, age, email, address, allowance FROM students $search_conditions ORDER BY student_id ASC LIMIT ? OFFSET ?";
$students_stmt = $conn->prepare($students_sql);
if (!empty($search_params)) {
    $students_stmt->bind_param("sssii", $search_params[0], $search_params[1], $search_params[2], $per_page, $offset);
} else {
    $students_stmt->bind_param("ii", $per_page, $offset);
}
$students_stmt->execute();
$students = $students_stmt->get_result();
$students_stmt->close();

// Load allowance logs for display
$allowance_logs = $conn->query("
    SELECT al.*, s.name as student_name 
    FROM allowance_logs al 
    JOIN students s ON al.student_id = s.student_id 
    ORDER BY al.changed_at DESC 
    LIMIT 10
");

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT student_id, name, age, email, address, allowance FROM students WHERE student_id=?");
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
  <title>Students</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
  <h1>Students</h1>
  <div class="nav">
    <a href="index.php">Home</a>
    <a href="courses.php">Courses</a>
    <a href="enrollments.php">Enrollments</a>
    <a href="payments.php">Payments</a>
  </div>

  <div class="row">
    <div class="col">
      <div class="card">
        <h2><?php echo $edit ? 'Edit Student' : 'Add Student'; ?></h2>
        <form method="post" action="students_actions.php">
          <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
          <?php if ($edit): ?>
            <input type="hidden" name="student_id" value="<?php echo (int)$edit['student_id']; ?>">
          <?php endif; ?>
          <div class="grid">
            <div>
              <label>Name</label>
              <input class="input" required name="name" value="<?php echo htmlspecialchars($edit['name'] ?? '', ENT_QUOTES); ?>">
            </div>
            <div>
              <label>Age</label>
              <input class="input" type="number" min="0" name="age" value="<?php echo htmlspecialchars($edit['age'] ?? '', ENT_QUOTES); ?>">
            </div>
            <div class="full">
              <label>Email</label>
              <input class="input" type="email" required name="email" value="<?php echo htmlspecialchars($edit['email'] ?? '', ENT_QUOTES); ?>">
            </div>
            <div class="full">
              <label>Address</label>
              <input class="input" name="address" value="<?php echo htmlspecialchars($edit['address'] ?? '', ENT_QUOTES); ?>">
            </div>
            <div>
              <label>Allowance</label>
              <input class="input" type="number" step="0.01" min="0" name="allowance" value="<?php echo htmlspecialchars($edit['allowance'] ?? '', ENT_QUOTES); ?>">
            </div>
          </div>
          <div style="margin-top:12px; display:flex; gap:8px;">
            <button class="btn" type="submit"><?php echo $edit ? 'Update' : 'Create'; ?></button>
            <?php if ($edit): ?>
              <a class="btn secondary" href="students.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="col">
      <div class="card">
        <h2>All Students (<?php echo $total_records; ?> total)</h2>

        <!-- Simple Search -->
        <div class="search-box">
          <form method="get" style="display: flex; gap: 8px; flex: 1;">
            <input class="input" type="text" name="search" placeholder="Search students..." 
                   value="<?php echo htmlspecialchars($search_term); ?>" style="flex: 1;">
            <button class="btn" type="submit">Search</button>
            <?php if (!empty($search_term)): ?>
              <a class="btn secondary" href="students.php">Clear</a>
            <?php endif; ?>
          </form>
        </div>

        <?php if ($students && $students->num_rows): ?>
          <table>
            <thead>
              <tr>
                <th>ID</th><th>Name</th><th>Age</th><th>Email</th><th>Allowance</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($row = $students->fetch_assoc()): ?>
              <tr>
                <td><?php echo (int)$row['student_id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['age']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td>
                  <span class="allowance-amount">$<?php echo number_format($row['allowance'], 2); ?></span>
                  <button class="btn-icon" onclick="showAllowanceLogs(<?php echo $row['student_id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                    📊
                  </button>
                </td>
                <td class="actions">
                  <a class="btn" href="?edit=<?php echo (int)$row['student_id']; ?>">Edit</a>
                  <a class="btn danger" href="students_actions.php?action=delete&id=<?php echo (int)$row['student_id']; ?>"
                     onclick="return confirm('Delete this student?');">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>

          <!-- Simple Pagination -->
          <div class="pagination">
            <?php if ($page > 1): ?>
              <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">← Previous</a>
            <?php endif; ?>
            
            <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            
            <?php if ($page < $total_pages): ?>
              <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next →</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="empty">
            <?php if (!empty($search_term)): ?>
              No students found matching "<?php echo htmlspecialchars($search_term); ?>"
            <?php else: ?>
              No students yet.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Allowance Logs Section -->
  <div class="card">
    <h2>Recent Allowance Changes</h2>
    <?php if ($allowance_logs && $allowance_logs->num_rows): ?>
      <table>
        <thead>
          <tr>
            <th>Student</th><th>Old Amount</th><th>New Amount</th><th>Change</th><th>Date</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($log = $allowance_logs->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($log['student_name']); ?></td>
            <td>$<?php echo number_format($log['old_allowance'], 2); ?></td>
            <td>$<?php echo number_format($log['new_allowance'], 2); ?></td>
            <td>
              <span class="change-amount <?php echo $log['new_allowance'] > $log['old_allowance'] ? 'positive' : 'negative'; ?>">
                <?php 
                $change = $log['new_allowance'] - $log['old_allowance'];
                echo ($change > 0 ? '+' : '') . '$' . number_format($change, 2);
                ?>
              </span>
            </td>
            <td><?php echo date('M j, Y g:i A', strtotime($log['changed_at'])); ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty">No allowance changes yet.</div>
    <?php endif; ?>
  </div>
</div>

<!-- Simple Modal -->
<div id="allowanceModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="modalTitle">Allowance History</h3>
      <span class="close" onclick="closeModal()">&times;</span>
    </div>
    <div class="modal-body">
      <div id="allowanceLogsContent">
        <p>Loading...</p>
      </div>
    </div>
  </div>
</div>

<script>
function showAllowanceLogs(studentId, studentName) {
  document.getElementById('modalTitle').textContent = 'Allowance History - ' + studentName;
  document.getElementById('allowanceLogsContent').innerHTML = '<p>Loading...</p>';
  document.getElementById('allowanceModal').style.display = 'block';
  
  // Simple fetch for allowance logs
  fetch('get_allowance_logs.php?student_id=' + studentId)
    .then(response => response.text())
    .then(data => {
      document.getElementById('allowanceLogsContent').innerHTML = data;
    })
    .catch(error => {
      document.getElementById('allowanceLogsContent').innerHTML = '<p>Error loading data</p>';
    });
}

function closeModal() {
  document.getElementById('allowanceModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('allowanceModal');
  if (event.target == modal) {
    modal.style.display = 'none';
  }
}
</script>
</body>
</html>