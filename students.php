<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/pagination.php';

// Get pagination parameters
$pagination = getPaginationParams();
$search_term = getSearchTerm();

// Build search conditions
$search_conditions = '';
$search_params = [];
if (!empty($search_term)) {
    $search_conditions = "WHERE name LIKE ? OR email LIKE ? OR address LIKE ?";
    $search_params = ["%$search_term%", "%$search_term%", "%$search_term%"];
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM students $search_conditions";
$count_stmt = $conn->prepare($count_sql);
if (!empty($search_params)) {
    $count_stmt->bind_param("sss", ...$search_params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Load students with pagination
$students_sql = "SELECT student_id, name, age, email, address, allowance FROM students $search_conditions ORDER BY student_id ASC LIMIT ? OFFSET ?";
$students_stmt = $conn->prepare($students_sql);
if (!empty($search_params)) {
    $students_stmt->bind_param("sssii", ...$search_params, $pagination['per_page'], $pagination['offset']);
} else {
    $students_stmt->bind_param("ii", $pagination['per_page'], $pagination['offset']);
}
$students_stmt->execute();
$students = $students_stmt->get_result();
$students_stmt->close();

// Load allowance logs for display (limited to recent)
$allowance_logs = $conn->query("
    SELECT al.*, s.name as student_name 
    FROM allowance_logs al 
    JOIN students s ON al.student_id = s.student_id 
    ORDER BY al.changed_at DESC 
    LIMIT 10
");

// Edit mode (optional)
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT student_id, name, age, email, address, allowance FROM students WHERE student_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$total_pages = getTotalPages($total_records, $pagination['per_page']);
$pagination_links = generatePaginationLinks($pagination['page'], $total_pages, 'students.php', addSearchToParams([], $search_term));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Students</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
  <h1>Students</h1>
  <div class="nav">
    <a href="index.php">Home</a>
    <a href="courses.php">Courses</a>
    <a href="enrollments.php">Enrollments</a>
    <a href="payments.php">Payments</a>
    <a href="search.php">Search</a>
  </div>

  <div class="row">
    <div class="col">
      <div class="card">
        <h2><?php echo $edit ? 'Edit Student' : 'Add Student'; ?></h2>
        <form method="post" action="students_actions.php" id="studentForm">
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h2>All Students</h2>
          <div style="display: flex; gap: 8px; align-items: center;">
            <span class="badge"><?php echo $total_records; ?> total</span>
            <span class="badge">Page <?php echo $pagination['page']; ?> of <?php echo $total_pages; ?></span>
          </div>
        </div>

        <!-- Search and Pagination Controls -->
        <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
          <form method="get" style="display: flex; gap: 8px; flex: 1; min-width: 300px;">
            <input class="input" type="text" name="search" placeholder="Search students..." 
                   value="<?php echo htmlspecialchars($search_term); ?>" style="flex: 1;">
            <button class="btn" type="submit">
              <i class="fas fa-search"></i>
            </button>
            <?php if (!empty($search_term)): ?>
              <a class="btn secondary" href="students.php">
                <i class="fas fa-times"></i>
              </a>
            <?php endif; ?>
          </form>
          
          <select class="select" onchange="changePerPage(this.value)" style="width: auto;">
            <option value="10" <?php echo $pagination['per_page'] == 10 ? 'selected' : ''; ?>>10 per page</option>
            <option value="25" <?php echo $pagination['per_page'] == 25 ? 'selected' : ''; ?>>25 per page</option>
            <option value="50" <?php echo $pagination['per_page'] == 50 ? 'selected' : ''; ?>>50 per page</option>
          </select>
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
                    <i class="fas fa-history"></i>
                  </button>
                </td>
                <td class="actions">
                  <a class="btn" href="?edit=<?php echo (int)$row['student_id']; ?>">Edit</a>
                  <a class="btn danger" href="students_actions.php?action=delete&id=<?php echo (int)$row['student_id']; ?>"
                     onclick="return confirm('Delete this student? This may affect enrollments.');">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>

          <!-- Pagination -->
          <div style="margin-top: 20px; display: flex; justify-content: center;">
            <?php renderPagination($pagination_links); ?>
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

<!-- Allowance Logs Modal -->
<div id="allowanceModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="modalTitle">Allowance History</h3>
      <span class="close" onclick="closeModal('allowanceModal')">&times;</span>
    </div>
    <div class="modal-body">
      <div id="allowanceLogsContent">
        <!-- Content will be loaded here -->
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
function showAllowanceLogs(studentId, studentName) {
  document.getElementById('modalTitle').textContent = 'Allowance History - ' + studentName;
  
  // Fetch allowance logs for specific student
  fetch('get_allowance_logs.php?student_id=' + studentId)
    .then(response => response.text())
    .then(data => {
      document.getElementById('allowanceLogsContent').innerHTML = data;
      document.getElementById('allowanceModal').style.display = 'block';
    })
    .catch(error => {
      showToast('Error loading allowance logs', 'error');
    });
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = 'none';
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

function changePerPage(perPage) {
  const url = new URL(window.location);
  url.searchParams.set('per_page', perPage);
  url.searchParams.set('page', '1'); // Reset to first page
  window.location.href = url.toString();
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modals = document.getElementsByClassName('modal');
  for (let modal of modals) {
    if (event.target == modal) {
      modal.style.display = 'none';
    }
  }
}
</script>
</body>
</html>