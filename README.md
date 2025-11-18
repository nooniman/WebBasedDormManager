# Dormitory Management System

A comprehensive web-based dormitory management system built with PHP, MySQL, JavaScript, HTML, and CSS with Auth0 SSO integration and modern UI/UX design.

## Features

### For Administrators
- **Modern Dashboard**: Enhanced statistics dashboard with animated cards and charts
- **Tenant Management**: Comprehensive tenant account management with profile cards
- **Room Management**: Advanced room inventory with photo galleries and amenities
- **Booking Management**: Visual booking calendar with status tracking and conflict detection
- **Payment Tracking**: Detailed payment monitoring with transaction history
- **Announcements**: Create and publish system-wide announcements
- **Reports**: Professional reports with charts, PDF exports, and analytics
- **Calendar View**: Interactive booking calendar with month navigation

### For Tenants
- **Room Browsing**: Advanced filtering (type, category, floor, price, amenities)
- **Room Gallery**: Stunning photo galleries with lightbox viewer
- **Online Booking**: Streamlined booking request submission
- **Personal Portal**: Enhanced dashboard with statistics and payment summary
- **Payment History**: Complete transaction records with visual indicators
- **Profile Management**: Update personal information and profile picture
- **Announcements**: Stay updated with important notices
- **SSO Login**: Secure login via Auth0 with social providers
- **Booking Calendar**: Track your bookings with visual calendar

### Public Features
- **Modern Homepage**: Welcoming landing page with feature highlights
- **Advanced Room Search**: Multi-criteria filtering system
- **Room Details**: Comprehensive room information with image galleries
- **User Registration**: Self-service account creation with validation
- **Password Recovery**: Secure token-based password reset
- **Auth0 SSO**: Single Sign-On with Google, Facebook, GitHub, and more

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Authentication**: Auth0 (OAuth 2.0 / OpenID Connect)
- **Server**: Apache (XAMPP)
- **Image Storage**: File-based upload system
- **HTTP Client**: Guzzle 7.0+
- **Design**: Modern gradient-based UI with glassmorphism effects

## Installation

### Prerequisites
- XAMPP (or similar LAMP/WAMP stack)
- PHP 7.4 or higher with Composer
- MySQL 5.7 or higher
- Modern web browser (Chrome, Firefox, Safari, Edge)
- Auth0 Account (free tier available)
- At least 100MB free disk space for uploads

### Setup Instructions

1. **Clone or Download the Project**
   ```bash
   git clone <repository-url>
   cd dormitory-management-system
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Start XAMPP**
   - Start Apache and MySQL services
   - Ensure both services are running (green indicators)

4. **Import Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create database `dormitory_db`
   - Import `dormitory_db_phase4_complete.sql`

5. **Configure Auth0**
   - Copy `config/auth0_config.php.example` to `config/auth0_config.php`
   - Update with your Auth0 credentials
   - Set callback URL in Auth0 dashboard

6. **Set Permissions**
   - Ensure the `uploads/` directory is writable:
     ```bash
     chmod 755 uploads/
     ```

7. **Access the Application**
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
│   ├── dashboard.php       # Enhanced dashboard with animated statistics
│   ├── tenants.php         # Modern tenant management with profile cards
│   ├── rooms.php           # Advanced room inventory management
│   ├── room_details.php    # Room details with photo gallery
│   ├── payments.php        # Payment tracking with transaction history
│   ├── bookings.php        # Booking management with modern card view
│   ├── view_booking.php    # ✨ Detailed booking view with timeline
│   ├── calendar.php        # ✨ Interactive booking calendar
│   ├── announcements.php   # Announcement management
│   └── reports.php         # ✨ Professional reports with charts
├── assets/                 # Static assets
│   ├── css/
│   │   └── style.css       # ✨ Modern responsive styles with gradients
│   ├── js/
│   │   └── main.js         # Enhanced interactive features
│   └── images/
├── config/                 # Configuration files
│   ├── database.php        # Database configuration (excluded from git)
│   └── auth0_config.php    # Auth0 SSO configuration (excluded from git)
├── includes/               # Reusable components
│   ├── header.php          # ✨ Modern glassmorphism header with SVG icons
│   ├── footer.php          # Enhanced footer
│   ├── functions.php       # Utility functions
│   ├── auth0_functions.php # Auth0 helper functions
│   ├── booking_functions.php # ✨ Booking helper functions
│   ├── admin_auth.php      # Admin authentication guard
│   └── tenant_auth.php     # Tenant authentication guard
├── public/                 # Public pages
│   ├── index.php           # Homepage
│   ├── rooms.php           # Enhanced room browsing with filters
│   ├── room_view.php       # Detailed room view with gallery
│   └── booking.php         # Room booking form
├── tenant/                 # Tenant portal
│   ├── portal.php          # Enhanced tenant dashboard
│   ├── profile.php         # Profile management
│   ├── bookings.php        # ✨ Tenant booking management
│   ├── booking_calendar.php # ✨ Tenant booking calendar
│   └── payments.php        # Payment history view
├── uploads/                # User uploads (excluded from git)
├── vendor/                 # Composer dependencies (excluded from git)
├── callback.php            # Auth0 callback handler
├── login.php               # Enhanced login with Auth0 SSO
├── logout.php              # Secure logout with Auth0 support
├── password_reset_request.php # Password reset request
├── password_reset.php      # Password reset confirmation
├── composer.json           # Composer configuration
├── .gitignore
├── README.md
├── PHASE1_SUMMARY.md
├── PHASE2_SUMMARY.md
├── PHASE3_SUMMARY.md
├── PHASE4_SUMMARY.md       # ✨ Enhanced booking system documentation
└── PHASE4.5_SUMMARY.md     # Auth0 SSO integration documentation
```

## Security Features

- **Password Hashing**: Using PHP's `password_hash()` with bcrypt
- **OAuth 2.0 / OpenID Connect**: Auth0 SSO integration
- **PKCE**: Proof Key for Code Exchange for enhanced security
- **CSRF Protection**: Token-based CSRF prevention on all forms
- **SQL Injection Prevention**: Prepared statements for all queries
- **XSS Prevention**: Input sanitization and output escaping
- **Session Management**: Secure session handling with regeneration
- **Access Control**: Role-based authentication guards
- **Input Validation**: Client-side and server-side validation
- **File Upload Security**: Type and size validation for uploads
- **Password Reset**: Secure token-based password recovery
- **Email Verification**: Support for email confirmation (ready for integration)
- **Social Login**: Secure authentication via Google, Facebook, GitHub

## Database Schema

### Core Tables (Phase 1)
- `users` - User accounts (admin & tenant)
- `rooms` - Room inventory
- `bookings` - Booking requests with enhanced workflow
- `payments` - Payment records
- `announcements` - System announcements
- `maintenance_requests` - Maintenance tracking

### Enhanced Tables (Phase 2, 3, 4 & 4.5)
- `password_resets` - Password reset tokens
- `email_verifications` - Email verification system
- `room_amenities` - Available amenities
- `room_amenity_assignments` - Room-amenity relationships
- `room_photos` - Room photo gallery
- `booking_conflicts` - ✨ Booking conflict tracking
- `notifications` - ✨ In-app notification system

### Enhanced Fields (Phase 4 & 4.5)
- **Bookings**: `duration_months`, `total_amount`, `approved_by`, `approved_at`, `rejected_reason`, `check_in_date`, `check_out_date`
- **Users**: `auth0_id`, `profile_picture`, `email_verified`, `remember_token`, `two_factor_enabled`
- **Rooms**: `floor_number`, `category`, `has_wifi`, `has_ac`, `has_bathroom`

## New Features in Phase 4

### Enhanced Booking System
- ✅ Visual booking calendar with interactive month navigation
- ✅ Modern booking card view with status badges
- ✅ Detailed booking view with timeline visualization
- ✅ Conflict detection and prevention
- ✅ Enhanced booking workflow (6 statuses)
- ✅ Automatic duration and pricing calculations
- ✅ In-app notification system
- ✅ Admin approval/rejection with reasons
- ✅ Check-in/check-out tracking
- ✅ Payment history integration

### UI/UX Improvements
- ✅ Modern glassmorphism header with backdrop blur
- ✅ Gradient-based design system
- ✅ Professional SVG icons (no emojis)
- ✅ Smooth animations and transitions
- ✅ Responsive card layouts
- ✅ Enhanced color scheme with shadows and glows
- ✅ Minimalist professional design
- ✅ Touch-friendly mobile interface
- ✅ Floating action buttons
- ✅ Modal overlays with blur effects

### Reports & Analytics
- ✅ Professional PDF-ready report layouts
- ✅ Revenue analytics with charts
- ✅ Occupancy rate tracking
- ✅ Monthly statistics
- ✅ Payment trend analysis
- ✅ Visual data representation

## Usage Guide

### For Administrators

1. **Login** to the admin panel using admin credentials or Auth0 SSO
2. **View Dashboard** - See real-time statistics and system overview
3. **Manage Rooms** - Add/edit rooms with photos and amenities
4. **Process Bookings** - Use calendar or list view to manage bookings
5. **Approve/Reject** - Process booking requests with detailed reasons
6. **Check-in/out Tenants** - Track tenant occupancy status
7. **Monitor Payments** - View transaction history and pending payments
8. **Generate Reports** - Create professional reports with analytics
9. **Post Announcements** - Communicate with all tenants
10. **View Calendar** - Visualize all bookings in calendar format

### For Tenants

1. **Register** - Create an account via registration or Auth0 SSO
2. **Login** to your account (standard or Auth0)
3. **Browse Rooms** - Use advanced filters to find your perfect room
4. **View Details** - See photo galleries and complete room information
5. **Submit Booking** - Request to book a room with date selection
6. **Track Status** - Monitor booking status in real-time
7. **View Calendar** - See your bookings in calendar format
8. **Make Payments** - Track payment history and status
9. **Update Profile** - Manage personal information and photo
10. **Read Announcements** - Stay informed about updates
11. **Receive Notifications** - Get instant updates on booking changes

## Development Roadmap

- [x] Phase 1: Project Setup & Database Architecture
- [x] Phase 2: User Authentication System
- [x] Phase 3: Room Management Module
- [x] Phase 4: Enhanced Booking System ✨ **COMPLETED**
- [x] Phase 4.5: Single Sign-On using Auth0
- [ ] Phase 5: Maintenance Request System - **NEXT**
- [ ] Phase 6: Email Notifications (SMTP integration)
- [ ] Phase 7: Advanced Reports (more charts, PDF exports)
- [ ] Phase 8: Payment Integration (payment gateway, receipts)
- [ ] Phase 9: Testing & Security Hardening
- [ ] Phase 10: Production Deployment

## Project Statistics

### Overall Progress
- **Total Files**: 45+
- **Total Lines of Code**: 8,000+
- **Database Tables**: 13
- **Admin Pages**: 9
- **Tenant Pages**: 5
- **Public Pages**: 4
- **External Integrations**: 1 (Auth0)
- **Composer Packages**: 3

### Phase Completion
- ✅ Phase 1: Complete (October 2025)
- ✅ Phase 2: Complete (October 2025)
- ✅ Phase 3: Complete (November 2025)
- ✅ Phase 4: Complete (November 2025) ✨ **NEW**
- ✅ Phase 4.5: Complete (November 2025)
- ⏳ Phase 5: Planned

## Design System

### Color Palette
- **Primary Gradient**: `#667eea` to `#764ba2`
- **Success**: `#10b981` (Green)
- **Warning**: `#f59e0b` (Amber)
- **Danger**: `#ef4444` (Red)
- **Info**: `#3b82f6` (Blue)
- **Neutral**: `#64748b` (Slate)

### Typography
- **Font Family**: Inter, -apple-system, BlinkMacSystemFont
- **Weights**: 300-900 (Variable)
- **Line Heights**: 1.5-1.8 for body text

### Components
- Glassmorphism effects with backdrop blur
- Gradient backgrounds and text
- Rounded corners (8px-20px)
- Soft shadows for depth
- Smooth transitions (0.3s ease)

## Auth0 Configuration

### Required Settings in Auth0 Dashboard
- **Application Type**: Regular Web Application
- **Allowed Callback URLs**: `http://localhost/dormitory-management-system/callback.php`
- **Allowed Logout URLs**: `http://localhost/dormitory-management-system/login.php`
- **Allowed Web Origins**: `http://localhost`

### Enabled Features
- Google Social Connection
- Email/Password authentication
- Profile information (name, email)

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

### Auth0 Connection Error
- Verify Auth0 credentials in `config/auth0_config.php`
- Check callback URL matches Auth0 dashboard
- Ensure Composer dependencies are installed
- Check PHP has curl extension enabled

### Calendar Not Loading
- Clear browser cache
- Check JavaScript console for errors
- Verify database has booking records

### Modal Not Appearing
- Ensure CSS is loaded properly
- Check for JavaScript errors
- Verify modal overlay styles in style.css

## License

This project is developed for educational purposes.

## Support

For support and questions, please contact the system administrator.

## Credits

- Built with PHP, MySQL, JavaScript, HTML5, CSS3
- Authentication: Auth0
- HTTP Client: Guzzle
- Design: Custom gradient-based modern UI
- Icons: Custom SVG icons
- Font: Inter (Google Fonts)

---

**Current Version**: 2.0.0

**Last Updated**: November 19, 2025

**Status**: ✅ Phase 1, 2, 3, 4, 4.5 Complete - Phase 5 Next