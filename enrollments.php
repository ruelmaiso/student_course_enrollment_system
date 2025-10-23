<?php
require_once __DIR__.'/db.php';

// Fetch lists for selects
$courses  = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name ASC");

// Get all students not currently enrolled (for the dropdown)
$students_sql = "
    SELECT s.student_id, s.name 
    FROM students s
    WHERE NOT EXISTS (
        SELECT 1 FROM enrollments e 
        WHERE e.student_id = s.student_id
    )
    ORDER BY s.name ASC";
$students = $conn->query($students_sql);

// If in edit mode, include the currently enrolled student in the list
if (isset($_GET['sid'], $_GET['cid'])) {
    $sid = (int)$_GET['sid'];
    $edit_student_sql = "SELECT student_id, name FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($edit_student_sql);
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $edit_student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Prepend the current student to the students list
    if ($edit_student) {
        $all_students = [];
        $all_students[] = $edit_student;
        while($s = $students->fetch_assoc()) {
            $all_students[] = $s;
        }
        // Create a new result object with the combined data
        $students = new ArrayObject($all_students);
    }
}

// Load enrollments with joins
$sql = "
  SELECT e.student_id, e.course_id, e.enrollment_date,
         s.name AS student_name, c.course_name
  FROM enrollments e
  JOIN students s ON s.student_id = e.student_id
  JOIN courses   c ON c.course_id   = e.course_id
  ORDER BY s.name, c.course_name
";
$enrollments = $conn->query($sql);

// Optional edit mode (identified by composite key)
$edit = null;
if (isset($_GET['sid'], $_GET['cid'])) {
    $sid = (int)$_GET['sid'];
    $cid = (int)$_GET['cid'];
    $stmt = $conn->prepare("SELECT student_id, course_id, enrollment_date FROM enrollments WHERE student_id=? AND course_id=?");
    $stmt->bind_param("ii", $sid, $cid);
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
</head>
<body>
<div class="container">
  <h1>Enrollments</h1>
  <div class="nav">
    <a href="index.php">Home</a>
    <a href="students.php">Students</a>
    <a href="courses.php">Courses</a>
  </div>

  <div class="row">
    <div class="col">
      <div class="card">
        <h2><?php echo $edit ? 'Edit Enrollment Date' : 'Add Enrollment'; ?></h2>
        <form method="post" action="enrollments_actions.php">
          <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
          <div class="grid">
            <div>
              <label>Student</label>
              <select class="select" name="student_id" <?php echo $edit ? 'readonly disabled' : 'required'; ?>>
                <option value="">-- choose --</option>
                <?php if ($students) foreach($students as $s): ?>
                  <option value="<?php echo (int)$s['student_id']; ?>"
                    <?php echo $edit && (int)$edit['student_id']===(int)$s['student_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if ($edit): ?>
                <input type="hidden" name="student_id" value="<?php echo (int)$edit['student_id']; ?>">
              <?php endif; ?>
            </div>

            <div>
              <label>Course</label>
              <select class="select" name="course_id" <?php echo $edit ? 'readonly disabled' : 'required'; ?>>
                <option value="">-- choose --</option>
                <?php if ($courses) while($c = $courses->fetch_assoc()): ?>
                  <option value="<?php echo (int)$c['course_id']; ?>"
                    <?php echo $edit && (int)$edit['course_id']===(int)$c['course_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['course_name']); ?>
                  </option>
                <?php endwhile; ?>
              </select>
              <?php if ($edit): ?>
                <input type="hidden" name="course_id" value="<?php echo (int)$edit['course_id']; ?>">
              <?php endif; ?>
            </div>

            <div class="full">
              <label>Enrollment Date</label>
              <input class="input" type="date" name="enrollment_date" required
                     value="<?php echo htmlspecialchars($edit['enrollment_date'] ?? '', ENT_QUOTES); ?>">
            </div>
          </div>

          <div style="margin-top:12px; display:flex; gap:8px;">
            <button class="btn" type="submit"><?php echo $edit ? 'Update Date' : 'Enroll'; ?></button>
            <?php if ($edit): ?><a class="btn secondary" href="enrollments.php">Cancel</a><?php endif; ?>
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
                <th>Student</th><th>Course</th><th>Date</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($row = $enrollments->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                <td><span class="badge"><?php echo htmlspecialchars($row['enrollment_date']); ?></span></td>
                <td class="actions">
                  <a class="btn" href="?sid=<?php echo (int)$row['student_id']; ?>&cid=<?php echo (int)$row['course_id']; ?>">Edit</a>
                  <a class="btn danger"
                     href="enrollments_actions.php?action=delete&sid=<?php echo (int)$row['student_id']; ?>&cid=<?php echo (int)$row['course_id']; ?>"
                     onclick="return confirm('Remove this enrollment?');">Delete</a>
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
</body>
</html>
