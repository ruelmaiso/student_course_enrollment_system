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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h2>All Courses</h2>
          <div style="display: flex; gap: 8px; align-items: center;">
            <span class="badge"><?php echo $total_records; ?> total</span>
            <span class="badge">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
          </div>
        </div>

        <!-- Search and Pagination Controls -->
        <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
          <form method="get" style="display: flex; gap: 8px; flex: 1; min-width: 300px;">
            <input class="input" type="text" name="search" placeholder="Search courses..." 
                   value="<?php echo htmlspecialchars($search_term); ?>" style="flex: 1;">
            <button class="btn" type="submit">
              <i class="fas fa-search"></i>
            </button>
            <?php if (!empty($search_term)): ?>
              <a class="btn secondary" href="courses.php">
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

<script>
function changePerPage(perPage) {
  const url = new URL(window.location);
  url.searchParams.set('per_page', perPage);
  url.searchParams.set('page', '1');
  window.location.href = url.toString();
}
</script>
</body>
</html>