<?php
// filepath: tenant/booking_calendar.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'Booking Calendar';
$tenant_id = $_SESSION['user_id'];

// Get current month and year
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($current_month < 1 || $current_month > 12) $current_month = date('n');
if ($current_year < 2020 || $current_year > 2100) $current_year = date('Y');

// Get bookings for the current month
$start_date = "$current_year-$current_month-01";
$end_date = date('Y-m-t', strtotime($start_date));

$bookings_query = "
    SELECT b.*, r.room_number, r.room_type
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.tenant_id = ?
    AND (
        (b.start_date BETWEEN ? AND ?)
        OR (b.end_date BETWEEN ? AND ?)
        OR (b.start_date <= ? AND b.end_date >= ?)
    )
    ORDER BY b.start_date
";

$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("issssss", $tenant_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$bookings_result = $stmt->get_result();

// Organize bookings by date
$bookings_by_date = [];
while ($booking = $bookings_result->fetch_assoc()) {
    $start = new DateTime($booking['start_date']);
    $end = $booking['end_date'] ? new DateTime($booking['end_date']) : null;
    
    // Add booking to each date it spans
    $current = clone $start;
    while (true) {
        $date_key = $current->format('Y-m-d');
        if (!isset($bookings_by_date[$date_key])) {
            $bookings_by_date[$date_key] = [];
        }
        $bookings_by_date[$date_key][] = $booking;
        
        if (!$end || $current >= $end) break;
        $current->modify('+1 day');
    }
}
$stmt->close();

// Get upcoming bookings (next 30 days)
$today = date('Y-m-d');
$next_month = date('Y-m-d', strtotime('+30 days'));

$upcoming_query = "
    SELECT b.*, r.room_number, r.room_type, r.price
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.tenant_id = ?
    AND b.start_date BETWEEN ? AND ?
    AND b.status IN ('pending', 'approved')
    ORDER BY b.start_date ASC
    LIMIT 5
";

$stmt = $conn->prepare($upcoming_query);
$stmt->bind_param("iss", $tenant_id, $today, $next_month);
$stmt->execute();
$upcoming_result = $stmt->get_result();
$stmt->close();

// Calculate calendar data
$first_day = new DateTime($start_date);
$days_in_month = (int)date('t', strtotime($start_date));
$start_day_of_week = (int)$first_day->format('w'); // 0 (Sunday) to 6 (Saturday)

require_once '../includes/header.php';
?>

<style>
    .calendar-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Calendar Layout */
    .calendar-layout {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 2rem;
    }
    
    /* Calendar Navigation */
    .calendar-nav {
        background: white;
        border-radius: 20px;
        padding: 1.5rem 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 2px solid #e2e8f0;
    }
    
    .calendar-nav h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .calendar-nav-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    
    .calendar-nav-buttons {
        display: flex;
        gap: 0.5rem;
    }
    
    .calendar-nav-btn {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 2px solid #e2e8f0;
        background: white;
        color: #475569;
        font-size: 1.25rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .calendar-nav-btn:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
        transform: translateY(-2px);
    }
    
    .calendar-nav-btn.today {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
        font-size: 0.875rem;
        font-weight: 600;
        width: auto;
        padding: 0 1rem;
    }
    
    /* Calendar Grid */
    .calendar-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
    }
    
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.5rem;
    }
    
    .calendar-day-header {
        text-align: center;
        font-weight: 700;
        font-size: 0.875rem;
        color: #64748b;
        padding: 1rem 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .calendar-day {
        aspect-ratio: 1;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.5rem;
        position: relative;
        cursor: pointer;
        transition: all 0.3s ease;
        background: white;
        display: flex;
        flex-direction: column;
    }
    
    .calendar-day:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }
    
    .calendar-day.other-month {
        background: #f8fafc;
        color: #cbd5e0;
    }
    
    .calendar-day.today {
        border-color: #667eea;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    }
    
    .calendar-day.has-booking {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: #3b82f6;
    }
    
    .calendar-day.has-booking.pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-color: #f59e0b;
    }
    
    .calendar-day.has-booking.approved {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        border-color: #10b981;
    }
    
    .calendar-day.has-booking.checked_in {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: #3b82f6;
    }
    
    .day-number {
        font-weight: 700;
        font-size: 1rem;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .calendar-day.other-month .day-number {
        color: #cbd5e0;
    }
    
    .day-events {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        overflow: hidden;
    }
    
    .day-event-dot {
        width: 100%;
        height: 4px;
        border-radius: 2px;
        background: #3b82f6;
    }
    
    .day-event-dot.pending { background: #f59e0b; }
    .day-event-dot.approved { background: #10b981; }
    .day-event-dot.checked_in { background: #3b82f6; }
    .day-event-dot.rejected { background: #ef4444; }
    .day-event-dot.cancelled { background: #94a3b8; }
    
    .day-event-count {
        font-size: 0.7rem;
        color: #64748b;
        font-weight: 600;
        margin-top: 0.25rem;
    }
    
    /* Sidebar */
    .calendar-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .sidebar-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
    }
    
    .sidebar-card h3 {
        margin: 0 0 1rem 0;
        font-size: 1.25rem;
        font-weight: 800;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    /* Legend */
    .calendar-legend {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .legend-color {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        border: 2px solid;
    }
    
    .legend-color.today {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        border-color: #667eea;
    }
    
    .legend-color.pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-color: #f59e0b;
    }
    
    .legend-color.approved {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        border-color: #10b981;
    }
    
    .legend-color.checked_in {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: #3b82f6;
    }
    
    .legend-label {
        font-size: 0.9rem;
        color: #475569;
        font-weight: 600;
    }
    
    /* Upcoming Events */
    .upcoming-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .upcoming-item {
        padding: 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        border-left: 4px solid #667eea;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .upcoming-item:hover {
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .upcoming-item.pending { border-left-color: #f59e0b; }
    .upcoming-item.approved { border-left-color: #10b981; }
    
    .upcoming-date {
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .upcoming-room {
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .upcoming-details {
        font-size: 0.85rem;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    /* Empty State */
    .empty-upcoming {
        text-align: center;
        padding: 2rem 1rem;
        color: #94a3b8;
    }
    
    .empty-upcoming-icon {
        font-size: 3rem;
        margin-bottom: 0.5rem;
        opacity: 0.5;
    }
    
    /* Month Selector */
    .month-selector {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .month-selector select {
        flex: 1;
        padding: 0.75rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        color: #475569;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .month-selector select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Tooltip */
    .calendar-tooltip {
        position: absolute;
        top: -10px;
        left: 50%;
        transform: translateX(-50%) translateY(-100%);
        background: #1e293b;
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        font-size: 0.75rem;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 100;
    }
    
    .calendar-day:hover .calendar-tooltip {
        opacity: 1;
    }
    
    .calendar-tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 6px solid transparent;
        border-top-color: #1e293b;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .calendar-layout {
            grid-template-columns: 1fr;
        }
        
        .calendar-sidebar {
            order: -1;
        }
    }
    
    @media (max-width: 768px) {
        .calendar-nav {
            flex-direction: column;
            gap: 1rem;
        }
        
        .calendar-grid {
            gap: 0.25rem;
        }
        
        .calendar-day {
            padding: 0.25rem;
        }
        
        .day-number {
            font-size: 0.85rem;
        }
        
        .calendar-day-header {
            font-size: 0.75rem;
            padding: 0.5rem 0;
        }
        
        .day-event-count {
            font-size: 0.6rem;
        }
    }
</style>

<div class="container calendar-page">
    
    <!-- Calendar Navigation -->
    <div class="calendar-nav">
        <h2>
            <span class="calendar-nav-icon">📅</span>
            <?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?>
        </h2>
        <div class="calendar-nav-buttons">
            <?php
            $prev_month = $current_month - 1;
            $prev_year = $current_year;
            if ($prev_month < 1) {
                $prev_month = 12;
                $prev_year--;
            }
            
            $next_month = $current_month + 1;
            $next_year = $current_year;
            if ($next_month > 12) {
                $next_month = 1;
                $next_year++;
            }
            ?>
            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="calendar-nav-btn">
                ←
            </a>
            <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="calendar-nav-btn today">
                Today
            </a>
            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="calendar-nav-btn">
                →
            </a>
        </div>
    </div>
    
    <div class="calendar-layout">
        <!-- Calendar Grid -->
        <div class="calendar-card">
            <div class="month-selector">
                <select onchange="window.location='?month=' + this.value + '&year=<?php echo $current_year; ?>'">
                    <?php
                    $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                               'July', 'August', 'September', 'October', 'November', 'December'];
                    foreach ($months as $i => $month):
                    ?>
                        <option value="<?php echo $i + 1; ?>" <?php echo ($i + 1) == $current_month ? 'selected' : ''; ?>>
                            <?php echo $month; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select onchange="window.location='?month=<?php echo $current_month; ?>&year=' + this.value">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="calendar-grid">
                <!-- Day Headers -->
                <?php
                $day_names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                foreach ($day_names as $day):
                ?>
                    <div class="calendar-day-header"><?php echo $day; ?></div>
                <?php endforeach; ?>
                
                <!-- Empty cells before first day -->
                <?php for ($i = 0; $i < $start_day_of_week; $i++): ?>
                    <div class="calendar-day other-month"></div>
                <?php endfor; ?>
                
                <!-- Calendar days -->
                <?php 
                $today_date = date('Y-m-d');
                for ($day = 1; $day <= $days_in_month; $day++): 
                    $current_date = sprintf("%04d-%02d-%02d", $current_year, $current_month, $day);
                    $is_today = $current_date === $today_date;
                    $day_bookings = $bookings_by_date[$current_date] ?? [];
                    $has_booking = !empty($day_bookings);
                    
                    $status_class = '';
                    if ($has_booking) {
                        $primary_booking = $day_bookings[0];
                        $status_class = $primary_booking['status'];
                    }
                    
                    $class = 'calendar-day';
                    if ($is_today) $class .= ' today';
                    if ($has_booking) $class .= ' has-booking ' . $status_class;
                ?>
                    <div class="<?php echo $class; ?>" onclick="viewDay('<?php echo $current_date; ?>')">
                        <div class="day-number"><?php echo $day; ?></div>
                        <?php if ($has_booking): ?>
                            <div class="day-events">
                                <?php foreach (array_slice($day_bookings, 0, 3) as $booking): ?>
                                    <div class="day-event-dot <?php echo $booking['status']; ?>"></div>
                                <?php endforeach; ?>
                                <?php if (count($day_bookings) > 3): ?>
                                    <div class="day-event-count">+<?php echo count($day_bookings) - 3; ?> more</div>
                                <?php endif; ?>
                            </div>
                            <div class="calendar-tooltip">
                                <?php echo count($day_bookings); ?> booking(s)
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
                
                <!-- Empty cells after last day -->
                <?php 
                $total_cells = $start_day_of_week + $days_in_month;
                $remaining_cells = (7 - ($total_cells % 7)) % 7;
                for ($i = 0; $i < $remaining_cells; $i++): 
                ?>
                    <div class="calendar-day other-month"></div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="calendar-sidebar">
            <!-- Legend -->
            <div class="sidebar-card">
                <h3>📊 Legend</h3>
                <div class="calendar-legend">
                    <div class="legend-item">
                        <div class="legend-color today"></div>
                        <span class="legend-label">Today</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color pending"></div>
                        <span class="legend-label">Pending</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color approved"></div>
                        <span class="legend-label">Approved</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color checked_in"></div>
                        <span class="legend-label">Checked In</span>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Bookings -->
            <div class="sidebar-card">
                <h3>🔔 Upcoming Bookings</h3>
                <?php if ($upcoming_result && $upcoming_result->num_rows > 0): ?>
                    <div class="upcoming-list">
                        <?php while ($upcoming = $upcoming_result->fetch_assoc()): ?>
                            <div class="upcoming-item <?php echo $upcoming['status']; ?>"
                                 onclick="window.location='<?php echo TENANT_URL; ?>/view_booking_details?id=<?php echo $upcoming['id']; ?>'">
                                <div class="upcoming-date">
                                    <?php echo date('M d, Y', strtotime($upcoming['start_date'])); ?>
                                </div>
                                <div class="upcoming-room">
                                    Room <?php echo htmlspecialchars($upcoming['room_number']); ?>
                                </div>
                                <div class="upcoming-details">
                                    <span class="badge-enhanced sm <?php 
                                        echo $upcoming['status'] === 'pending' ? 'warning' : 'success'; 
                                    ?>">
                                        <?php echo ucfirst($upcoming['status']); ?>
                                    </span>
                                    <span><?php echo format_currency($upcoming['price']); ?>/mo</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-upcoming">
                        <div class="empty-upcoming-icon">📭</div>
                        <p>No upcoming bookings</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Actions -->
            <div class="sidebar-card">
                <h3>⚡ Quick Actions</h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <a href="<?php echo TENANT_URL; ?>/bookings" class="btn-enhanced outline">
                        View All Bookings
                    </a>
                    <a href="<?php echo PUBLIC_URL; ?>/rooms" class="btn-enhanced primary">
                        Browse Rooms
                    </a>
                    <a href="<?php echo TENANT_URL; ?>/portal" class="btn-enhanced outline">
                        Back to Portal
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewDay(date) {
    // You can implement a modal or redirect to show bookings for that day
    // For now, redirect to bookings page
    window.location = '<?php echo TENANT_URL; ?>/bookings';
}

// Add keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft') {
        document.querySelector('.calendar-nav-btn[href*="month=<?php echo $prev_month; ?>"]').click();
    } else if (e.key === 'ArrowRight') {
        document.querySelector('.calendar-nav-btn[href*="month=<?php echo $next_month; ?>"]').click();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>