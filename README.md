# Dormitory Management System

A comprehensive web-based dormitory management system built with PHP, MySQL, JavaScript, HTML, and CSS.

## Features

### For Administrators
- **Dashboard**: View statistics and system overview
- **Tenant Management**: Manage tenant accounts and information
- **Room Management**: Add, edit, and manage room inventory with photo galleries
- **Room Details**: Enhanced room management with multiple photos and amenities
- **Booking Management**: Approve or reject booking requests
- **Payment Tracking**: Monitor and manage payment records
- **Announcements**: Create and publish announcements for tenants
- **Reports**: Generate occupancy and revenue reports

### For Tenants
- **Room Browsing**: View available rooms with advanced filtering (type, category, floor, price, amenities)
- **Room Gallery**: View detailed room information with photo galleries and lightbox viewer
- **Online Booking**: Submit booking requests easily
- **Personal Portal**: Enhanced dashboard with statistics and payment summary
- **Payment History**: View complete payment records and transaction details
- **Profile Management**: Update personal information and profile picture
- **Announcements**: Stay updated with important notices

### Public Features
- **Modern Homepage**: Welcoming landing page with feature highlights
- **Advanced Room Search**: Filter rooms by multiple criteria
- **Room Details**: View comprehensive room information before booking
- **User Registration**: Self-service account creation with validation
- **Password Recovery**: Secure password reset functionality

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Server**: Apache (XAMPP)
- **Image Storage**: File-based upload system

## Installation

### Prerequisites
- XAMPP (or similar LAMP/WAMP stack)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser
- At least 100MB free disk space for uploads

### Setup Instructions

1. **Clone or Download the Project**
   ```bash
   git clone <repository-url>
   cd dormitory-management-system
   ```

2. **Start XAMPP**
   - Start Apache and MySQL services
   - Ensure both services are running (green indicators)

3. **Set Permissions**
   - Ensure the `uploads/` directory is writable:
     ```bash
     chmod 755 uploads/
     ```

4. **Access the Application**
   - Open your browser and navigate to:
     ```
     http://localhost/dormitory-management-system/
     ```

## Default Login Credentials

### Administrator Account
- **Email**: nas@admin.com
- **Password**: password

### Tenant Account (For Testing)
- **Email**: yuriko@tenant.com
- **Password**: password

**Important**: Change these default passwords immediately after first login!

## Project Structure

```
dormitory-management-system/
├── admin/                  # Admin panel pages
│   ├── dashboard.php       # Admin dashboard with statistics
│   ├── tenants.php         # Tenant management
│   ├── rooms.php           # Room inventory management
│   ├── room_details.php    # ✨ Enhanced room details with photos
│   ├── payments.php        # Payment tracking
│   ├── bookings.php        # Booking request processing
│   ├── announcements.php   # Announcement management
│   └── reports.php         # Analytics and reports
├── assets/                 # Static assets
│   ├── css/
│   │   └── style.css       # Enhanced responsive styles
│   ├── js/
│   │   └── main.js         # Interactive JavaScript features
│   └── images/
├── config/                 # Configuration files
│   └── database.php        # Database configuration (excluded from git)   
├── includes/               # Reusable components
│   ├── header.php          # Common header template
│   ├── footer.php          # Common footer template
│   ├── functions.php       # Utility functions
│   ├── admin_auth.php      # Admin authentication guard
│   └── tenant_auth.php     # Tenant authentication guard
├── public/                 # Public pages
│   ├── index.php           # Homepage
│   ├── rooms.php           # ✨ Enhanced room browsing with filters
│   ├── room_view.php       # ✨ Detailed room view with gallery
│   └── booking.php         # Room booking form
├── tenant/                 # Tenant portal
│   ├── portal.php          # ✨ Enhanced tenant dashboard
│   ├── profile.php         # Profile management
│   └── payments.php        # ✨ Payment history view
├── uploads/                # User uploads (excluded from git)
├── login.php               # ✨ Enhanced login with registration
├── logout.php              # Secure logout
├── password_reset_request.php # ✨ Password reset request
├── password_reset.php      # ✨ Password reset confirmation
├── .gitignore
├── README.md
├── PHASE1_SUMMARY.md
├── PHASE2_SUMMARY.md       # ✨ Phase 2 documentation
└── PHASE3_SUMMARY.md       # ✨ Phase 3 documentation
```

## Security Features

- **Password Hashing**: Using PHP's `password_hash()` with bcrypt
- **CSRF Protection**: Token-based CSRF prevention on all forms
- **SQL Injection Prevention**: Prepared statements for all queries
- **XSS Prevention**: Input sanitization and output escaping
- **Session Management**: Secure session handling with regeneration
- **Access Control**: Role-based authentication guards
- **Input Validation**: Client-side and server-side validation
- **File Upload Security**: Type and size validation for uploads
- **Password Reset**: Secure token-based password recovery
- **Email Verification**: Support for email confirmation (ready for integration)

## Database Schema

### Core Tables (Phase 1)
- `users` - User accounts (admin & tenant)
- `rooms` - Room inventory
- `bookings` - Booking requests
- `payments` - Payment records
- `announcements` - System announcements
- `maintenance_requests` - Maintenance tracking

### Enhanced Tables (Phase 2 & 3)
- `password_resets` - ✨ Password reset tokens
- `email_verifications` - ✨ Email verification system
- `room_amenities` - ✨ Available amenities
- `room_amenity_assignments` - ✨ Room-amenity relationships
- `room_photos` - ✨ Room photo gallery

### Enhanced Fields
- Users: `profile_picture`, `email_verified`, `remember_token`, `two_factor_enabled`
- Rooms: `floor_number`, `category`, `has_wifi`, `has_ac`, `has_bathroom`

## Usage Guide

### For Administrators

1. **Login** to the admin panel using admin credentials
2. **Add Rooms** through the Rooms management page
3. **Upload Photos** - Add multiple photos to each room
4. **Assign Amenities** - Configure room features and amenities
5. **Manage Tenants** - View and monitor tenant accounts
6. **Process Bookings** - Approve or reject booking requests
7. **Track Payments** - Monitor payment records
8. **Post Announcements** - Communicate with tenants
9. **Generate Reports** - View occupancy and revenue statistics

### For Tenants

1. **Register** - Create an account via the registration tab
2. **Login** to your account
3. **Browse Rooms** - Use advanced filters to find your perfect room
4. **View Details** - See photo galleries and complete room information
5. **Submit Booking** - Request to book a room
6. **View Portal** - Check booking status and payment summary
7. **Track Payments** - View complete payment history
8. **Update Profile** - Manage personal information
9. **Read Announcements** - Stay informed
10. **Reset Password** - Use forgot password if needed

## New Features in Phase 2 & 3

### Phase 2: Enhanced Authentication
- ✅ User registration with validation
- ✅ Password reset functionality
- ✅ Email verification support (ready for SMTP integration)
- ✅ Profile picture upload
- ✅ Enhanced login page with tabbed interface

### Phase 3: Advanced Room Management
- ✅ Multiple photo uploads per room
- ✅ Primary photo designation
- ✅ Photo gallery with lightbox viewer
- ✅ Room amenities system
- ✅ Room categories (Standard, Deluxe, Premium)
- ✅ Floor number tracking
- ✅ Advanced filtering (type, category, floor, price, amenities)
- ✅ Enhanced room details page
- ✅ Photo count badges
- ✅ Amenity icons and labels

### UI/UX Improvements
- ✅ Responsive grid layouts
- ✅ Sticky filter sidebar
- ✅ Hover effects and animations
- ✅ Statistical dashboards
- ✅ Color-coded status badges
- ✅ Modern card designs
- ✅ Lightbox image viewer
- ✅ Enhanced tenant portal

## Development Roadmap

- [x] Phase 1: Project Setup & Database Architecture
- [x] Phase 2: User Authentication System
- [x] Phase 3: Room Management Module
- [ ] Phase 4: Enhanced Booking System (calendar view, conflicts)
- [ ] Phase 5: Payment Integration (online payments, receipts)
- [ ] Phase 6: Email Notifications (SMTP integration)
- [ ] Phase 7: Advanced Reports (charts, PDF exports)
- [ ] Phase 8: Maintenance Request System
- [ ] Phase 9: Testing & Security Hardening
- [ ] Phase 10: Production Deployment

## Project Statistics

### Overall Progress
- **Total Files**: 35+
- **Total Lines of Code**: 4,000+
- **Database Tables**: 11
- **Admin Pages**: 8
- **Tenant Pages**: 3
- **Public Pages**: 4
- **API Endpoints**: Ready for Phase 5

### Phase Completion
- ✅ Phase 1: Complete (October 2025)
- ✅ Phase 2: Complete (October 2025)
- ✅ Phase 3: Complete (November 2025)
- ⏳ Phase 4: In Progress

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Known Issues

- Email sending requires SMTP configuration (Phase 6)
- Remember me functionality needs cookie implementation
- Two-factor authentication is prepared but not active

## Troubleshooting

### Database Connection Error
- Verify MySQL is running in XAMPP
- Check `config/database.php` credentials
- Ensure `dormitory_db` database exists

### Upload Directory Error
- Check `uploads/` directory permissions (755)
- Verify Apache has write access

### Login Issues
- Clear browser cache and cookies
- Verify user exists in database
- Check password meets requirements (8+ characters)

### Photo Upload Issues
- Maximum file size: 5MB
- Accepted formats: JPG, JPEG, PNG
- Check `uploads/` directory permissions

## License

This project is developed for educational purposes.

## Support

For support and questions, please contact the system administrator.

## Credits

- Built with PHP, MySQL, JavaScript, HTML5, CSS3
- Icons: Unicode Emoji
- Font: System default fonts

---

**Current Version**: 1.2.0

**Last Updated**: November 5, 2025

**Status**: ✅ Phase 1, 2, 3 Complete - In Active Development