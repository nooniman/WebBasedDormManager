<?php
// filepath: admin/calendar.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';
require_once '../includes/booking_functions.php';

$page_title = 'Booking Calendar';

// Get date range for calendar
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$room_filter = isset($_GET['room_id']) ? intval($_GET['room_id']) : null;

// Validate month and year
$month = max(1, min(12, $month));
$year = max(2020, min(2100, $year));

$first_day = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
$last_day = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

// Get all rooms for filter
$rooms_query = "SELECT id, room_number FROM rooms ORDER BY room_number";
$rooms_result = $conn->query($rooms_query);

// Get bookings for calendar
$bookings = get_calendar_bookings($conn, $first_day, $last_day, $room_filter);

// Get statistics for this month
$stats_query = "
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in
    FROM bookings
    WHERE (start_date <= ? AND (end_date >= ? OR end_date IS NULL))
       OR (start_date BETWEEN ? AND ?)
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("ssss", $last_day, $first_day, $first_day, $last_day);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

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
    
    /* Calendar Container */
    .calendar-wrapper {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    /* Calendar Header */
    .calendar-header-modern {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .calendar-title-section {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .calendar-month-year {
        font-size: 2rem;
        font-weight: 800;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1;
    }
    
    .calendar-subtitle {
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 500;
    }
    
    .calendar-nav-buttons {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }
    
    .month-nav-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-weight: 600;
        color: #475569;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }
    
    .month-nav-btn:hover {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .today-btn {
        padding: 0.75rem 1.5rem;
        background: var(--gradient-primary);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        transition: all 0.3s ease;
    }
    
    .today-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
    }
    
    /* Quick Stats for Calendar */
    .calendar-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .calendar-stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.75rem;
        box-shadow: var(--card-shadow);
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .calendar-stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        opacity: 0.1;
        transition: all 0.5s ease;
    }
    
    .calendar-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-shadow-hover);
        border-color: #cbd5e0;
    }
    
    .calendar-stat-card:hover::before {
        top: -20%;
        right: -20%;
    }
    
    .calendar-stat-card.total::before { background: #667eea; }
    .calendar-stat-card.pending::before { background: #f59e0b; }
    .calendar-stat-card.approved::before { background: #10b981; }
    .calendar-stat-card.checked-in::before { background: #3b82f6; }
    
    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        position: relative;
        z-index: 1;
    }
    
    .stat-card-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .calendar-stat-card.total .stat-card-icon {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .calendar-stat-card.pending .stat-card-icon {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    }
    
    .calendar-stat-card.approved .stat-card-icon {
        background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
    }
    
    .calendar-stat-card.checked-in .stat-card-icon {
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
    }
    
    .stat-card-value {
        font-size: 2.25rem;
        font-weight: 800;
        color: #1e293b;
        line-height: 1;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }
    
    .stat-card-label {
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 600;
        position: relative;
        z-index: 1;
    }
    
    /* Filter Section */
    .filter-section-modern {
        background: white;
        border-radius: 16px;
        padding: 1.75rem;
        box-shadow: var(--card-shadow);
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .filter-form {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1rem;
        align-items: end;
    }
    
    .filter-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    /* Calendar Legend */
    .calendar-legend-modern {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        padding: 1.25rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        margin-bottom: 2rem;
        border: 2px dashed #cbd5e0;
    }
    
    .legend-item-modern {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 600;
        font-size: 0.9rem;
        color: #475569;
    }
    
    .legend-dot {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .legend-dot.approved { background: linear-gradient(135deg, #d4f4dd 0%, #c6f6d5 100%); border: 2px solid #10b981; }
    .legend-dot.pending { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; }
    .legend-dot.checked-in { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border: 2px solid #3b82f6; }
    .legend-dot.rejected { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border: 2px solid #ef4444; }
    .legend-dot.cancelled { background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border: 2px solid #6b7280; }
    
    /* Calendar Grid */
    .calendar-grid-modern {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background: #cbd5e0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    }
    
    .calendar-weekday-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem;
        text-align: center;
        font-weight: 700;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .calendar-day-cell {
        background: white;
        padding: 0.75rem;
        min-height: 120px;
        position: relative;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .calendar-day-cell:hover {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        transform: scale(1.02);
        z-index: 10;
    }
    
    .calendar-day-cell.empty {
        background: #f8fafc;
        cursor: default;
    }
    
    .calendar-day-cell.empty:hover {
        transform: none;
    }
    
    .calendar-day-cell.today {
        background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
        border: 2px solid #8b5cf6;
    }
    
    .day-number-modern {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .calendar-day-cell.today .day-number-modern {
        color: #8b5cf6;
    }
    
    .today-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #8b5cf6;
        box-shadow: 0 0 8px rgba(139, 92, 246, 0.6);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.2); opacity: 0.8; }
    }
    
    .booking-pills-container {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    
    .booking-pill-modern {
        padding: 0.4rem 0.65rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }
    
    .booking-pill-modern:hover {
        transform: translateX(3px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .booking-pill-modern.approved {
        background: linear-gradient(135deg, #d4f4dd 0%, #c6f6d5 100%);
        color: #065f46;
        border-left: 3px solid #10b981;
    }
    
    .booking-pill-modern.pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #78350f;
        border-left: 3px solid #f59e0b;
    }
    
    .booking-pill-modern.checked_in,
    .booking-pill-modern.checked-in {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e3a8a;
        border-left: 3px solid #3b82f6;
    }
    
    .booking-pill-modern.rejected {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border-left: 3px solid #ef4444;
    }
    
    .booking-pill-modern.cancelled {
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        color: #374151;
        border-left: 3px solid #6b7280;
    }
    
    /* Bookings List Table */
    .bookings-list-section {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: var(--card-shadow);
        border: 1px solid #e2e8f0;
        margin-top: 2rem;
    }
    
    .section-header-modern {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .section-icon-modern {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .section-header-modern h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .bookings-table-modern {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .bookings-table-modern thead tr {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .bookings-table-modern th {
        padding: 1rem;
        text-align: left;
        font-weight: 700;
        font-size: 0.875rem;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #cbd5e0;
    }
    
    .bookings-table-modern th:first-child {
        border-top-left-radius: 12px;
    }
    
    .bookings-table-modern th:last-child {
        border-top-right-radius: 12px;
    }
    
    .bookings-table-modern tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .bookings-table-modern tbody tr:hover {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        transform: scale(1.01);
    }
    
    .bookings-table-modern td {
        padding: 1rem;
        color: #475569;
        font-weight: 500;
    }
    
    /* Empty State */
    .calendar-empty-state {
        text-align: center;
        padding: 3rem 2rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        border: 2px dashed #cbd5e0;
    }
    
    .empty-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .calendar-empty-state h3 {
        color: #64748b;
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }
    
    .calendar-empty-state p {
        color: #94a3b8;
        margin: 0;
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
        .calendar-grid-modern {
            font-size: 0.9rem;
        }
        
        .calendar-day-cell {
            min-height: 100px;
        }
    }
    
    @media (max-width: 768px) {
        .calendar-wrapper {
            padding: 1.5rem;
        }
        
        .calendar-header-modern {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .calendar-nav-buttons {
            width: 100%;
            justify-content: space-between;
        }
        
        .filter-form {
            grid-template-columns: 1fr;
        }
        
        .calendar-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .calendar-grid-modern {
            display: block;
        }
        
        .calendar-weekday-header {
            display: none;
        }
        
        .calendar-day-cell {
            margin-bottom: 0.5rem;
            border-radius: 8px;
            min-height: auto;
        }
        
        .bookings-table-modern {
            display: block;
            overflow-x: auto;
        }
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>Booking Calendar</h1>
                <p class="subtitle">Visual overview of all bookings and schedules</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="bookings" class="btn-enhanced outline">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor" style="width: 18px; height: 18px;">
                        <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
                    </svg>
                    List View
                </a>
                <a href="dashboard" class="btn-enhanced outline">← Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container calendar-page">
    
    <!-- Statistics Cards -->
    <div class="calendar-stats-grid">
        <div class="calendar-stat-card total">
            <div class="stat-card-header">
                <div class="stat-card-icon">📊</div>
            </div>
            <div class="stat-card-value"><?php echo $stats['total_bookings']; ?></div>
            <div class="stat-card-label">Total Bookings</div>
        </div>
        <div class="calendar-stat-card pending">
            <div class="stat-card-header">
                <div class="stat-card-icon">⏳</div>
            </div>
            <div class="stat-card-value"><?php echo $stats['pending']; ?></div>
            <div class="stat-card-label">Pending Review</div>
        </div>
        <div class="calendar-stat-card approved">
            <div class="stat-card-header">
                <div class="stat-card-icon">✓</div>
            </div>
            <div class="stat-card-value"><?php echo $stats['approved']; ?></div>
            <div class="stat-card-label">Approved</div>
        </div>
        <div class="calendar-stat-card checked-in">
            <div class="stat-card-header">
                <div class="stat-card-icon">🏠</div>
            </div>
            <div class="stat-card-value"><?php echo $stats['checked_in']; ?></div>
            <div class="stat-card-label">Currently Checked In</div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section-modern">
        <form method="GET" action="" class="filter-form">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Filter by Room</label>
                <select name="room_id" class="form-control">
                    <option value="">All Rooms</option>
                    <?php 
                    $rooms_result->data_seek(0);
                    while ($room = $rooms_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $room['id']; ?>" 
                                <?php echo $room_filter == $room['id'] ? 'selected' : ''; ?>>
                            Room <?php echo htmlspecialchars($room['room_number']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            <div class="filter-actions">
                <button type="submit" class="btn-enhanced primary" style="flex: 1;">Apply Filter</button>
                <?php if ($room_filter): ?>
                    <a href="calendar?month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                       class="btn-enhanced outline" style="flex: 1;">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Calendar Container -->
    <div class="calendar-wrapper">
        <!-- Calendar Header -->
        <div class="calendar-header-modern">
            <div class="calendar-title-section">
                <div class="calendar-month-year">
                    <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                </div>
                <div class="calendar-subtitle">
                    <?php echo count($bookings); ?> booking(s) this month
                </div>
            </div>
            <div class="calendar-nav-buttons">
                <a href="?month=<?php echo $month == 1 ? 12 : $month - 1; ?>&year=<?php echo $month == 1 ? $year - 1 : $year; ?><?php echo $room_filter ? '&room_id=' . $room_filter : ''; ?>" 
                   class="month-nav-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                    Previous
                </a>
                <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?><?php echo $room_filter ? '&room_id=' . $room_filter : ''; ?>" 
                   class="today-btn">
                    Today
                </a>
                <a href="?month=<?php echo $month == 12 ? 1 : $month + 1; ?>&year=<?php echo $month == 12 ? $year + 1 : $year; ?><?php echo $room_filter ? '&room_id=' . $room_filter : ''; ?>" 
                   class="month-nav-btn">
                    Next
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                    </svg>
                </a>
            </div>
        </div>
        
        <!-- Calendar Legend -->
        <div class="calendar-legend-modern">
            <div class="legend-item-modern">
                <div class="legend-dot approved"></div>
                <span>Approved</span>
            </div>
            <div class="legend-item-modern">
                <div class="legend-dot pending"></div>
                <span>Pending</span>
            </div>
            <div class="legend-item-modern">
                <div class="legend-dot checked-in"></div>
                <span>Checked In</span>
            </div>
            <div class="legend-item-modern">
                <div class="legend-dot rejected"></div>
                <span>Rejected</span>
            </div>
            <div class="legend-item-modern">
                <div class="legend-dot cancelled"></div>
                <span>Cancelled</span>
            </div>
        </div>
        
        <!-- Calendar Grid -->
        <div class="calendar-grid-modern">
            <div class="calendar-weekday-header">Sun</div>
            <div class="calendar-weekday-header">Mon</div>
            <div class="calendar-weekday-header">Tue</div>
            <div class="calendar-weekday-header">Wed</div>
            <div class="calendar-weekday-header">Thu</div>
            <div class="calendar-weekday-header">Fri</div>
            <div class="calendar-weekday-header">Sat</div>
            
            <?php
            $first_day_of_week = date('w', mktime(0, 0, 0, $month, 1, $year));
            $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
            
            // Empty cells for days before month starts
            for ($i = 0; $i < $first_day_of_week; $i++) {
                echo '<div class="calendar-day-cell empty"></div>';
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
                
                $day_class = 'calendar-day-cell';
                if ($is_today) $day_class .= ' today';
                
                echo '<div class="' . $day_class . '">';
                echo '<div class="day-number-modern">';
                echo '<span>' . $day . '</span>';
                if ($is_today) echo '<span class="today-indicator"></span>';
                echo '</div>';
                
                if (count($day_bookings) > 0) {
                    echo '<div class="booking-pills-container">';
                    $count = 0;
                    foreach ($day_bookings as $booking) {
                        if ($count >= 3) {
                            $remaining = count($day_bookings) - 3;
                            echo '<div class="booking-pill-modern" style="background: #e2e8f0; color: #475569; border-left: 3px solid #94a3b8;">+' . $remaining . ' more</div>';
                            break;
                        }
                        $status_class = 'booking-pill-modern ' . str_replace('_', '-', $booking['status']);
                        $tenant_name = $booking['first_name'] . ' ' . $booking['last_name'];
                        echo '<div class="' . $status_class . '" title="Room ' . htmlspecialchars($booking['room_number']) . ' - ' . htmlspecialchars($tenant_name) . ' - ' . ucfirst($booking['status']) . '" onclick="viewBooking(' . $booking['id'] . ')">';
                        echo htmlspecialchars($booking['room_number']);
                        echo '</div>';
                        $count++;
                    }
                    echo '</div>';
                }
                
                echo '</div>';
            }
            ?>
        </div>
    </div>
    
    <!-- Bookings List -->
    <?php if (count($bookings) > 0): ?>
        <div class="bookings-list-section">
            <div class="section-header-modern">
                <div class="section-icon-modern">📋</div>
                <h3>Bookings This Month</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="bookings-table-modern">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Tenant</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['room_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></td>
                                <td><?php echo $booking['end_date'] ? date('M d, Y', strtotime($booking['end_date'])) : '<em>Open-ended</em>'; ?></td>
                                <td><?php echo $booking['duration_months'] ?? 1; ?> month(s)</td>
                                <td><strong><?php echo format_currency($booking['total_amount'] ?? 0); ?></strong></td>
                                <td>
                                    <span class="badge-enhanced <?php echo str_replace('_', '-', $booking['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_booking?id=<?php echo $booking['id']; ?>" 
                                       class="btn-enhanced primary sm">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="calendar-empty-state">
            <div class="empty-icon">📅</div>
            <h3>No Bookings This Month</h3>
            <p>There are no bookings scheduled for this period.</p>
        </div>
    <?php endif; ?>
</div>

<script>
function viewBooking(bookingId) {
    window.location.href = 'view_booking.php?id=' + bookingId;
}
</script>

<?php require_once '../includes/footer.php'; ?>