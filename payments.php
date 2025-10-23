<?php
require_once __DIR__.'/db.php';

// Load payments with student and course names
$payments = $conn->query("
    SELECT p.*, s.name as student_name, c.course_name, c.course_fee
    FROM payments p
    JOIN students s ON p.student_id = s.student_id
    JOIN courses c ON p.course_id = c.course_id
    ORDER BY p.payment_date DESC
");

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
  <h1>Payment Management</h1>
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
        <h2><?php echo $edit ? 'Edit Payment' : 'Record Payment'; ?></h2>
        <form method="post" action="payments_actions.php" id="paymentForm">
          <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
          <?php if ($edit): ?>
            <input type="hidden" name="payment_id" value="<?php echo (int)$edit['payment_id']; ?>">
          <?php endif; ?>
          <div class="grid">
            <div>
              <label>Student</label>
              <select class="select" name="student_id" required onchange="updateCourseOptions()">
                <option value="">-- choose student --</option>
                <?php if ($students) while($s = $students->fetch_assoc()): ?>
                  <option value="<?php echo (int)$s['student_id']; ?>"
                    <?php echo $edit && (int)$edit['student_id']===(int)$s['student_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['name']); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div>
              <label>Course</label>
              <select class="select" name="course_id" required onchange="updateAmount()">
                <option value="">-- choose course --</option>
                <?php if ($courses) while($c = $courses->fetch_assoc()): ?>
                  <option value="<?php echo (int)$c['course_id']; ?>" 
                    data-fee="<?php echo $c['course_fee']; ?>"
                    <?php echo $edit && (int)$edit['course_id']===(int)$c['course_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['course_name']); ?> - $<?php echo number_format($c['course_fee'], 2); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div>
              <label>Amount</label>
              <input class="input" type="number" step="0.01" min="0" name="amount" required
                     value="<?php echo htmlspecialchars($edit['amount'] ?? '', ENT_QUOTES); ?>">
            </div>

            <div>
              <label>Payment Date</label>
              <input class="input" type="datetime-local" name="payment_date" required
                     value="<?php echo $edit ? date('Y-m-d\TH:i', strtotime($edit['payment_date'])) : date('Y-m-d\TH:i'); ?>">
            </div>
          </div>

          <div style="margin-top:12px; display:flex; gap:8px;">
            <button class="btn" type="submit"><?php echo $edit ? 'Update Payment' : 'Record Payment'; ?></button>
            <?php if ($edit): ?>
              <a class="btn secondary" href="payments.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="col">
      <div class="card">
        <h2>Payment History</h2>
        <?php if ($payments && $payments->num_rows): ?>
          <table>
            <thead>
              <tr>
                <th>Student</th><th>Course</th><th>Amount</th><th>Date</th><th>Actions</th>
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
                <td><?php echo date('M j, Y g:i A', strtotime($row['payment_date'])); ?></td>
                <td class="actions">
                  <a class="btn" href="?edit=<?php echo (int)$row['payment_id']; ?>">Edit</a>
                  <a class="btn danger" href="payments_actions.php?action=delete&id=<?php echo (int)$row['payment_id']; ?>"
                     onclick="return confirm('Delete this payment record?');">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty">No payments recorded yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
function updateAmount() {
  const courseSelect = document.querySelector('select[name="course_id"]');
  const amountInput = document.querySelector('input[name="amount"]');
  
  if (courseSelect.value) {
    const selectedOption = courseSelect.options[courseSelect.selectedIndex];
    const courseFee = selectedOption.getAttribute('data-fee');
    if (courseFee && !amountInput.value) {
      amountInput.value = courseFee;
    }
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

// Show toast on form submission
document.getElementById('paymentForm').addEventListener('submit', function() {
  showToast('Processing payment...', 'info');
});
</script>
</body>
</html>