<?php
require_once __DIR__.'/includes/db.php';

// Load students
$students = $conn->query("SELECT student_id, name, age, email, address, allowance FROM students ORDER BY student_id ASC");

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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Students</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<div class="container">
  <h1>Students</h1>
  <div class="nav">
    <a href="index.php">Home</a>
    <a href="courses.php">Courses</a>
    <a href="enrollments.php">Enrollments</a>
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
              <input class="input" type="number" step="0.01" name="allowance" value="<?php echo htmlspecialchars($edit['allowance'] ?? '', ENT_QUOTES); ?>">
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
        <h2>All Students</h2>
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
                <td><?php echo htmlspecialchars($row['allowance']); ?></td>
                <td class="actions">
                  <a class="btn" href="?edit=<?php echo (int)$row['student_id']; ?>">Edit</a>
                  <a class="btn danger" href="students_actions.php?action=delete&id=<?php echo (int)$row['student_id']; ?>"
                     onclick="return confirm('Delete this student? This may affect enrollments.');">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty">No students yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>


