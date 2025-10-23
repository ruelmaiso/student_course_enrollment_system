<?php
require_once __DIR__.'/db.php';

$courses = $conn->query("SELECT course_id, course_name, course_fee FROM courses ORDER BY course_id ASC");

$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT course_id, course_name, course_fee FROM courses WHERE course_id=?");
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
  <title>Courses</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
  <h1>Courses</h1>
  <div class="nav">
    <a href="index.php">Home</a>
    <a href="students.php">Students</a>
    <a href="enrollments.php">Enrollments</a>
    <a href="payments.php">Payments</a>
    <a href="search.php">Search</a>
  </div>

  <div class="row">
    <div class="col">
      <div class="card">
        <h2><?php echo $edit ? 'Edit Course' : 'Add Course'; ?></h2>
        <form method="post" action="courses_actions.php">
          <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
          <?php if ($edit): ?>
            <input type="hidden" name="course_id" value="<?php echo (int)$edit['course_id']; ?>">
          <?php endif; ?>
          <div class="grid">
            <div>
              <label>Course Name</label>
              <input class="input" required name="course_name" value="<?php echo htmlspecialchars($edit['course_name'] ?? '', ENT_QUOTES); ?>">
            </div>
            <div>
              <label>Course Fee</label>
              <input class="input" type="number" step="0.01" min="0" name="course_fee" value="<?php echo htmlspecialchars($edit['course_fee'] ?? '', ENT_QUOTES); ?>">
            </div>
          </div>
          <div style="margin-top:12px; display:flex; gap:8px;">
            <button class="btn" type="submit"><?php echo $edit ? 'Update' : 'Create'; ?></button>
            <?php if ($edit): ?>
              <a class="btn secondary" href="courses.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="col">
      <div class="card">
        <h2>All Courses</h2>
        <?php if ($courses && $courses->num_rows): ?>
          <table>
            <thead><tr><th>ID</th><th>Name</th><th>Fee</th><th>Actions</th></tr></thead>
            <tbody>
            <?php while ($row = $courses->fetch_assoc()): ?>
              <tr>
                <td><?php echo (int)$row['course_id']; ?></td>
                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                <td>$<?php echo number_format($row['course_fee'], 2); ?></td>
                <td class="actions">
                  <a class="btn" href="?edit=<?php echo (int)$row['course_id']; ?>">Edit</a>
                  <a class="btn danger" href="courses_actions.php?action=delete&id=<?php echo (int)$row['course_id']; ?>"
                     onclick="return confirm('Delete this course? This may affect enrollments.');">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty">No courses yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>