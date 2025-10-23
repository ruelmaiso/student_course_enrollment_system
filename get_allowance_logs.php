<?php
require_once __DIR__.'/db.php';

$student_id = (int)($_GET['student_id'] ?? 0);

if ($student_id <= 0) {
    echo '<p>Invalid student ID</p>';
    exit;
}

// Get allowance logs for specific student
$logs_sql = "
    SELECT al.*, s.name as student_name 
    FROM allowance_logs al 
    JOIN students s ON al.student_id = s.student_id 
    WHERE al.student_id = ?
    ORDER BY al.changed_at DESC
";
$stmt = $conn->prepare($logs_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();

if ($logs && $logs->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Old Amount</th>
                <th>New Amount</th>
                <th>Change</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($log = $logs->fetch_assoc()): ?>
            <tr>
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
    <p>No allowance changes found for this student.</p>
<?php endif; ?>