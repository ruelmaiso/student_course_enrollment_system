<?php
require_once __DIR__.'/db.php';

$student_id = (int)($_GET['student_id'] ?? 0);

if ($student_id <= 0) {
    echo '<div class="empty">Invalid student ID</div>';
    exit;
}

$stmt = $conn->prepare("
    SELECT al.*, s.name as student_name 
    FROM allowance_logs al 
    JOIN students s ON al.student_id = s.student_id 
    WHERE al.student_id = ?
    ORDER BY al.changed_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<table>';
    echo '<thead><tr><th>Old Amount</th><th>New Amount</th><th>Change</th><th>Date</th></tr></thead>';
    echo '<tbody>';
    
    while ($log = $result->fetch_assoc()) {
        $change = $log['new_allowance'] - $log['old_allowance'];
        $changeClass = $change > 0 ? 'positive' : 'negative';
        
        echo '<tr>';
        echo '<td>$' . number_format($log['old_allowance'], 2) . '</td>';
        echo '<td>$' . number_format($log['new_allowance'], 2) . '</td>';
        echo '<td><span class="change-amount ' . $changeClass . '">';
        echo ($change > 0 ? '+' : '') . '$' . number_format($change, 2);
        echo '</span></td>';
        echo '<td>' . date('M j, Y g:i A', strtotime($log['changed_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
} else {
    echo '<div class="empty">No allowance changes recorded for this student.</div>';
}

$stmt->close();
?>