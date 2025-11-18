/**
 * Calendar functionality for booking system
 */

// Global calendar state
const CalendarState = {
    currentMonth: new Date().getMonth() + 1,
    currentYear: new Date().getFullYear(),
    selectedDate: null,
    bookings: []
};

/**
 * Initialize calendar
 */
function initCalendar() {
    // Add event listeners to calendar days
    const calendarDays = document.querySelectorAll('.calendar-day:not(.empty)');
    calendarDays.forEach(day => {
        day.addEventListener('click', handleDayClick);
    });
    
    // Initialize booking pills
    const bookingPills = document.querySelectorAll('.booking-pill');
    bookingPills.forEach(pill => {
        pill.addEventListener('click', handleBookingClick);
    });
    
    // Initialize month navigation
    const prevButton = document.querySelector('.calendar-nav-prev');
    const nextButton = document.querySelector('.calendar-nav-next');
    
    if (prevButton) {
        prevButton.addEventListener('click', navigatePrevMonth);
    }
    
    if (nextButton) {
        nextButton.addEventListener('click', navigateNextMonth);
    }
    
    // Highlight today
    highlightToday();
}

/**
 * Handle day click event
 */
function handleDayClick(event) {
    const day = event.currentTarget;
    
    // Remove previous selection
    document.querySelectorAll('.calendar-day.selected').forEach(d => {
        d.classList.remove('selected');
    });
    
    // Add selection to clicked day
    day.classList.add('selected');
    
    // Get day information
    const dayNumber = day.querySelector('.day-number').textContent;
    CalendarState.selectedDate = dayNumber;
    
    // Show bookings for this day
    showDayBookings(day);
}

/**
 * Handle booking pill click
 */
function handleBookingClick(event) {
    event.stopPropagation();
    const bookingId = event.currentTarget.getAttribute('data-booking-id');
    
    if (bookingId) {
        showBookingDetails(bookingId);
    }
}

/**
 * Show bookings for a specific day
 */
function showDayBookings(dayElement) {
    const bookings = dayElement.querySelectorAll('.booking-pill');
    
    if (bookings.length === 0) {
        showNotification('No bookings for this day', 'info');
        return;
    }
    
    // Create modal or sidebar to show bookings
    let bookingList = '<h3>Bookings for ' + CalendarState.selectedDate + '</h3><ul>';
    
    bookings.forEach(booking => {
        const text = booking.textContent;
        const status = booking.className.match(/status-(\w+)/)[1];
        bookingList += `<li>${text} - ${status}</li>`;
    });
    
    bookingList += '</ul>';
    
    // Display in a modal or alert (simplified version)
    console.log(bookingList);
}

/**
 * Show booking details
 */
function showBookingDetails(bookingId) {
    // Redirect to booking details page or open modal
    if (window.location.pathname.includes('/admin/')) {
        window.location.href = 'bookings.php?id=' + bookingId;
    } else {
        window.location.href = '../tenant/bookings.php';
    }
}

/**
 * Navigate to previous month
 */
function navigatePrevMonth() {
    CalendarState.currentMonth--;
    
    if (CalendarState.currentMonth < 1) {
        CalendarState.currentMonth = 12;
        CalendarState.currentYear--;
    }
    
    updateCalendarUrl();
}

/**
 * Navigate to next month
 */
function navigateNextMonth() {
    CalendarState.currentMonth++;
    
    if (CalendarState.currentMonth > 12) {
        CalendarState.currentMonth = 1;
        CalendarState.currentYear++;
    }
    
    updateCalendarUrl();
}

/**
 * Update calendar URL with new month/year
 */
function updateCalendarUrl() {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('month', CalendarState.currentMonth);
    currentUrl.searchParams.set('year', CalendarState.currentYear);
    window.location.href = currentUrl.toString();
}

/**
 * Highlight today's date
 */
function highlightToday() {
    const today = new Date();
    const todayElement = document.querySelector('.calendar-day.today');
    
    if (todayElement) {
        todayElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Fade in
    setTimeout(() => {
        notification.style.opacity = '1';
    }, 10);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

/**
 * Filter calendar by room
 */
function filterByRoom(roomId) {
    const url = new URL(window.location.href);
    
    if (roomId) {
        url.searchParams.set('room_id', roomId);
    } else {
        url.searchParams.delete('room_id');
    }
    
    window.location.href = url.toString();
}

/**
 * Export calendar to print
 */
function printCalendar() {
    window.print();
}

/**
 * Check for booking conflicts
 */
async function checkBookingConflicts(roomId, startDate, endDate) {
    try {
        const response = await fetch('check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                room_id: roomId,
                start_date: startDate,
                end_date: endDate
            })
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error checking conflicts:', error);
        return { available: false, error: true };
    }
}

/**
 * Load bookings via AJAX (for dynamic updates)
 */
async function loadBookings(month, year, roomId = null) {
    try {
        const params = new URLSearchParams({
            month: month,
            year: year
        });
        
        if (roomId) {
            params.append('room_id', roomId);
        }
        
        const response = await fetch(`get_bookings.php?${params}`);
        const bookings = await response.json();
        
        CalendarState.bookings = bookings;
        renderCalendar();
    } catch (error) {
        console.error('Error loading bookings:', error);
        showNotification('Failed to load bookings', 'error');
    }
}

/**
 * Render calendar (if using dynamic loading)
 */
function renderCalendar() {
    // This would be used for a fully AJAX-based calendar
    // For now, we're using server-side rendering
    console.log('Calendar rendered with', CalendarState.bookings.length, 'bookings');
}

/**
 * Initialize on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.calendar-container')) {
        initCalendar();
    }
});

/**
 * Keyboard navigation
 */
document.addEventListener('keydown', function(event) {
    if (!document.querySelector('.calendar-container')) return;
    
    switch(event.key) {
        case 'ArrowLeft':
            const prevBtn = document.querySelector('.calendar-nav-prev');
            if (prevBtn) prevBtn.click();
            break;
        case 'ArrowRight':
            const nextBtn = document.querySelector('.calendar-nav-next');
            if (nextBtn) nextBtn.click();
            break;
        case 'p':
            if (event.ctrlKey || event.metaKey) {
                event.preventDefault();
                printCalendar();
            }
            break;
    }
});

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initCalendar,
        checkBookingConflicts,
        filterByRoom,
        printCalendar
    };
}