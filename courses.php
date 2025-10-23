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
    $search_conditions = "WHERE course_name LIKE ?";
    $search_params = ["%$search_term%"];
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM courses $search_conditions";
if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("s", $search_params[0]);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
}

// Load courses with pagination
$courses_sql = "SELECT course_id, course_name, course_fee FROM courses $search_conditions ORDER BY course_id ASC LIMIT ? OFFSET ?";
$courses_stmt = $conn->prepare($courses_sql);
if (!empty($search_params)) {
    $courses_stmt->bind_param("sii", $search_params[0], $per_page, $offset);
} else {
    $courses_stmt->bind_param("ii", $per_page, $offset);
}
$courses_stmt->execute();
$courses = $courses_stmt->get_result();
$courses_stmt->close();

$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT course_id, course_name, course_fee FROM courses WHERE course_id=?");
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
        <h2>All Courses (<?php echo $total_records; ?> total)</h2>

        <!-- Simple Search -->
        <div class="search-box">
          <form method="get" style="display: flex; gap: 8px; flex: 1;">
            <input class="input" type="text" name="search" placeholder="Search courses..." 
                   value="<?php echo htmlspecialchars($search_term); ?>" style="flex: 1;">
            <button class="btn" type="submit">Search</button>
            <?php if (!empty($search_term)): ?>
              <a class="btn secondary" href="courses.php">Clear</a>
            <?php endif; ?>
          </form>
        </div>

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
                     onclick="return confirm('Delete this course?');">Delete</a>
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
              No courses found matching "<?php echo htmlspecialchars($search_term); ?>"
            <?php else: ?>
              No courses yet.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>