<?php
require_once __DIR__.'/db.php';

$search_term = $_GET['q'] ?? '';
$search_results = [];

if (!empty($search_term)) {
    $search_term = $conn->real_escape_string($search_term);
    
    // Search students
    $students_query = "
        SELECT 'student' as type, student_id as id, name as title, 
               CONCAT('Age: ', age, ' | Email: ', email, ' | Allowance: $', allowance) as description,
               'students.php' as link
        FROM students 
        WHERE name LIKE '%$search_term%' 
           OR email LIKE '%$search_term%' 
           OR address LIKE '%$search_term%'
        ORDER BY name ASC
    ";
    
    // Search courses
    $courses_query = "
        SELECT 'course' as type, course_id as id, course_name as title,
               CONCAT('Fee: $', course_fee) as description,
               'courses.php' as link
        FROM courses 
        WHERE course_name LIKE '%$search_term%'
        ORDER BY course_name ASC
    ";
    
    // Search enrollments
    $enrollments_query = "
        SELECT 'enrollment' as type, CONCAT(e.student_id, '-', e.course_id) as id,
               CONCAT(s.name, ' enrolled in ', c.course_name) as title,
               CONCAT('Date: ', e.enrollment_date, ' | Fee: $', c.course_fee) as description,
               'enrollments.php' as link
        FROM enrollments e
        JOIN students s ON e.student_id = s.student_id
        JOIN courses c ON e.course_id = c.course_id
        WHERE s.name LIKE '%$search_term%' 
           OR c.course_name LIKE '%$search_term%'
        ORDER BY e.enrollment_date DESC
    ";
    
    // Search payments
    $payments_query = "
        SELECT 'payment' as type, p.payment_id as id,
               CONCAT(s.name, ' paid for ', c.course_name) as title,
               CONCAT('Amount: $', p.amount, ' | Date: ', p.payment_date) as description,
               'payments.php' as link
        FROM payments p
        JOIN students s ON p.student_id = s.student_id
        JOIN courses c ON p.course_id = c.course_id
        WHERE s.name LIKE '%$search_term%' 
           OR c.course_name LIKE '%$search_term%'
        ORDER BY p.payment_date DESC
    ";
    
    // Execute all queries
    $results = [];
    
    if ($result = $conn->query($students_query)) {
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $result->close();
    }
    
    if ($result = $conn->query($courses_query)) {
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $result->close();
    }
    
    if ($result = $conn->query($enrollments_query)) {
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $result->close();
    }
    
    if ($result = $conn->query($payments_query)) {
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $result->close();
    }
    
    $search_results = $results;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Search Results</title>
  <link rel="stylesheet" href="styles_enhanced.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
  <h1>Search Results</h1>
  <div class="nav">
    <a href="index.php">Home</a>
    <a href="students.php">Students</a>
    <a href="courses.php">Courses</a>
    <a href="enrollments.php">Enrollments</a>
    <a href="payments.php">Payments</a>
  </div>

  <!-- Search Form -->
  <div class="card">
    <h2>Global Search</h2>
    <form method="get" action="search.php" style="display: flex; gap: 12px; align-items: end;">
      <div style="flex: 1;">
        <label>Search Term</label>
        <input class="input" type="text" name="q" placeholder="Search students, courses, enrollments, payments..." 
               value="<?php echo htmlspecialchars($search_term); ?>" required>
      </div>
      <button class="btn" type="submit">
        <i class="fas fa-search"></i> Search
      </button>
    </form>
  </div>

  <!-- Search Results -->
  <?php if (!empty($search_term)): ?>
    <div class="card">
      <h2>
        Search Results for "<?php echo htmlspecialchars($search_term); ?>"
        <span class="badge"><?php echo count($search_results); ?> results</span>
      </h2>
      
      <?php if (count($search_results) > 0): ?>
        <div class="search-results">
          <?php foreach ($search_results as $result): ?>
            <div class="search-result-item">
              <div class="search-result-header">
                <h3>
                  <i class="fas fa-<?php echo $result['type'] === 'student' ? 'user' : ($result['type'] === 'course' ? 'book' : ($result['type'] === 'enrollment' ? 'graduation-cap' : 'credit-card')); ?>"></i>
                  <?php echo htmlspecialchars($result['title']); ?>
                  <span class="badge"><?php echo ucfirst($result['type']); ?></span>
                </h3>
              </div>
              <div class="search-result-description">
                <?php echo htmlspecialchars($result['description']); ?>
              </div>
              <div class="search-result-actions">
                <a class="btn" href="<?php echo $result['link']; ?>">
                  <i class="fas fa-external-link-alt"></i> View
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">
          <i class="fas fa-search" style="font-size: 48px; color: var(--muted); margin-bottom: 16px;"></i>
          <p>No results found for "<?php echo htmlspecialchars($search_term); ?>"</p>
          <p style="color: var(--muted); font-size: 14px;">Try different keywords or check spelling</p>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="empty">
        <i class="fas fa-search" style="font-size: 48px; color: var(--muted); margin-bottom: 16px;"></i>
        <p>Enter a search term to find students, courses, enrollments, or payments</p>
      </div>
    </div>
  <?php endif; ?>
</div>

<style>
.search-results {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.search-result-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  transition: all 0.2s ease;
}

.search-result-item:hover {
  background: #f1f5f9;
  border-color: var(--accent);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.search-result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.search-result-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 8px;
}

.search-result-description {
  color: var(--muted);
  margin-bottom: 12px;
  font-size: 14px;
}

.search-result-actions {
  display: flex;
  gap: 8px;
}

.badge {
  background: var(--accent);
  color: white;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}
</style>
</body>
</html>