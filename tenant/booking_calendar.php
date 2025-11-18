<?php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';
require_once '../includes/booking_functions.php';

$page_title = 'Booking Calendar';

// Get date range for calendar
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
$month = max(1, min(12, $month));
$year = max(2020, min(2100, $year));

$first_day = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
$last_day = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

// Get tenant's bookings for this month
$tenant_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT b.*, r.room_number, r.room_type 
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.tenant_id = ? 
    AND (
        (b.start_date <= ? AND (b.end_date >= ? OR b.end_date IS NULL))
        OR (b.start_date BETWEEN ? AND ?)
    )
    ORDER BY b.start_date
");
$stmt->bind_param("issss", $tenant_id, $last_day, $first_day, $first_day, $last_day);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/calendar.css">

<div class="container">
    <div class="page-header">
        <h1>📅 My Booking Calendar</h1>
        <a href="bookings.php" class="btn btn-secondary">List View</a>
    </div>
    
    <div class="calendar-container">
        <div class="calendar-header">
            <a href="?month=<?php echo $month == 1 ? 12 : $month - 1; ?>&year=<?php echo $month == 1 ? $year - 1 : $year; ?>" 
               class="btn btn-secondary btn-sm">← Previous</a>
            <h2><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h2>
            <a href="?month=<?php echo $month == 12 ? 1 : $month + 1; ?>&year=<?php echo $month == 12 ? $year + 1 : $year; ?>" 
               class="btn btn-secondary btn-sm">Next →</a>
        </div>
        
        <div class="calendar-legend">
            <span class="legend-item"><span class="legend-color" style="background: #d4edda;"></span> Approved</span>
            <span class="legend-item"><span class="legend-color" style="background: #fff3cd;"></span> Pending</span>
            <span class="legend-item"><span class="legend-color" style="background: #d1ecf1;"></span> Checked In</span>
            <span class="legend-item"><span class="legend-color" style="background: #f8d7da;"></span> Rejected</span>
        </div>
        
        <div class="calendar-grid">
            <div class="calendar-day-header">Sun</div>
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header">Fri</div>
            <div class="calendar-day-header">Sat</div>
            
            <?php
            $first_day_of_week = date('w', mktime(0, 0, 0, $month, 1, $year));
            $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
            
            // Empty cells for days before month starts
            for ($i = 0; $i < $first_day_of_week; $i++) {
                echo '<div class="calendar-day empty"></div>';
            }
            
            // Days of the month
            for ($day = 1; $day <= $days_in_month; $day++) {
                $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $is_today = ($current_date == date('Y-m-d'));
                
                // Find bookings for this day
                $day_bookings = array_filter($bookings, function($booking) use ($current_date) {
                    $start = $booking['start_date'];
                    $end = $booking['end_date'] ?? '9999-12-31';
                    return $current_date >= $start && $current_date <= $end;
                });
                
                $day_class = 'calendar-day';
                if ($is_today) $day_class .= ' today';
                if (count($day_bookings) > 0) $day_class .= ' has-booking';
                
                echo '<div class="' . $day_class . '">';
                echo '<div class="day-number">' . $day . '</div>';
                
                foreach ($day_bookings as $booking) {
                    $status_class = 'booking-pill status-' . $booking['status'];
                    echo '<div class="' . $status_class . '" title="Room ' . htmlspecialchars($booking['room_number']) . ' - ' . ucfirst($booking['status']) . '">';
                    echo htmlspecialchars($booking['room_number']);
                    echo '</div>';
                }
                
                echo '</div>';
            }
            ?>
        </div>
    </div>
    
    <?php if (count($bookings) > 0): ?>
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3>Bookings This Month</h3>
            </div>
            <div class="card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                                <td><?php echo ucfirst($booking['room_type']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></td>
                                <td><?php echo $booking['end_date'] ? date('M d, Y', strtotime($booking['end_date'])) : 'Open-ended'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="bookings.php" class="btn btn-sm btn-primary">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>