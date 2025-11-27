# Dormitory Management System

A comprehensive web-based dormitory management system built with PHP, MySQL, JavaScript, HTML, and CSS with Auth0 SSO integration, PayPal payment processing, and modern UI/UX design.

## Features

### For Administrators
- **Modern Dashboard**: Enhanced statistics dashboard with animated cards, charts, and PayPal revenue tracking
- **Tenant Management**: Comprehensive tenant management with search, filters, grid/table views, and detailed tenant profiles
- **Tenant Details**: Detailed tenant view with profile info, booking history, payment history, and account management
- **Room Management**: Advanced room inventory with photo galleries, amenities, and room creation wizard
- **Booking Management**: Visual booking calendar with status tracking and conflict detection
- **Payment Tracking**: Detailed payment monitoring with PayPal transaction history, receipt viewing, and capture IDs
- **Receipt Management**: View and print professional payment receipts with full transaction details
- **PayPal Analytics**: Track PayPal vs cash payments, view transaction details, and monitor payment trends
- **Announcements**: Create and publish system-wide announcements
- **Reports**: Professional reports with tabbed interface, CSV exports, payment analytics, and Chart.js visualizations
- **Calendar View**: Interactive booking calendar with month navigation
- **Profile Management**: Admin profile settings and account management

### For Tenants
- **Room Browsing**: Advanced filtering (type, category, floor, price, amenities)
- **Room Gallery**: Stunning photo galleries with lightbox viewer
- **Online Booking**: Streamlined booking request submission
- **Personal Portal**: Enhanced dashboard with statistics and payment summary
- **PayPal Payments**: Secure online payments via PayPal with sandbox/production modes
- **Multi-Room Payments**: Pay for multiple bookings if tenant has more than one room
- **Flexible Payment Duration**: Choose payment duration from 1-12 months
- **Payment History**: Complete transaction records with PayPal transaction IDs and status tracking
- **Payment Receipts**: Professional receipts after PayPal payments with print-to-PDF support
- **Profile Management**: Update personal information and profile picture
- **Announcements**: Stay updated with important notices
- **SSO Login**: Secure login via Auth0 with social providers
- **Booking Calendar**: Track your bookings with visual calendar
- **Booking Details**: View comprehensive booking information with payment history

### Public Features
- **Modern Homepage**: Welcoming landing page with feature highlights
- **Advanced Room Search**: Multi-criteria filtering system
- **Room Details**: Comprehensive room information with image galleries
- **User Registration**: Self-service account creation with validation
- **Password Recovery**: Secure token-based password reset
- **Auth0 SSO**: Single Sign-On with Google, Facebook, GitHub, and more

## Technology Stack

- **Backend**: PHP 7.4+ / 8.x
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Authentication**: Auth0 (OAuth 2.0 / OpenID Connect)
- **Payments**: PayPal Checkout SDK 1.0 (Sandbox & Production)
- **Server**: Apache (XAMPP / Hostinger)
- **Image Storage**: File-based upload system
- **HTTP Client**: Guzzle 7.0+
- **Charts**: Chart.js 4.4.0
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

6. **Configure PayPal**
   - Copy `config/paypal_config.php.example` to `config/paypal_config.php`
   - Update with your PayPal API credentials (Client ID & Secret)
   - Set `PAYPAL_MODE` to 'sandbox' for testing or 'live' for production

7. **Set Permissions**
   - Ensure the `uploads/` directory is writable:
     ```bash
     chmod 755 uploads/
     ```

8. **Access the Application**
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
├── admin/                      # Admin panel pages
│   ├── dashboard.php           # Enhanced dashboard with PayPal stats
│   ├── tenants.php             # ✨ Enhanced tenant management with filters & views
│   ├── tenant_details.php      # ✨ Detailed tenant profile page
│   ├── rooms.php               # Room inventory management
│   ├── room_add.php            # ✨ Room creation wizard
│   ├── room_details.php        # Room details with photo gallery
│   ├── payments.php            # ✨ Payment tracking with receipts & modals
│   ├── bookings.php            # Booking management with card view
│   ├── view_booking.php        # Detailed booking view with PayPal info
│   ├── edit_booking.php        # Booking edit functionality
│   ├── check_conflicts.php     # ✨ AJAX conflict detection endpoint
│   ├── calendar.php            # Interactive booking calendar
│   ├── announcements.php       # Announcement management
│   ├── reports.php             # ✨ Reports with CSV exports & Chart.js
│   └── profile.php             # ✨ Admin profile management
├── assets/                     # Static assets
│   ├── css/
│   │   ├── style.css           # Modern responsive styles
│   │   └── calendar.css        # Calendar-specific styles
│   ├── js/
│   │   ├── main.js             # Interactive features
│   │   └── calendar.js         # Calendar functionality
│   └── images/
├── config/                     # Configuration files
│   ├── database.php            # Database configuration
│   ├── auth0_config.php        # Auth0 SSO configuration
│   └── paypal_config.php       # ✨ PayPal API configuration
├── includes/                   # Reusable components
│   ├── header.php              # Glassmorphism header with SVG icons
│   ├── footer.php              # Enhanced footer
│   ├── functions.php           # Utility functions
│   ├── auth0_functions.php     # Auth0 helper functions
│   ├── booking_functions.php   # Booking helper functions
│   ├── paypal_functions.php    # ✨ PayPal SDK wrapper functions
│   ├── admin_auth.php          # Admin authentication guard
│   └── tenant_auth.php         # Tenant authentication guard
├── public/                     # Public pages
│   ├── index.php               # Homepage
│   ├── rooms.php               # Room browsing with filters
│   ├── room_view.php           # Room detail view with gallery
│   └── booking.php             # Room booking form
├── tenant/                     # Tenant portal
│   ├── portal.php              # Tenant dashboard
│   ├── profile.php             # Profile management
│   ├── bookings.php            # Tenant booking management
│   ├── booking_calendar.php    # Tenant booking calendar
│   ├── view_booking_details.php # ✨ Detailed booking information
│   ├── payments.php            # ✨ Payment history with multi-room support
│   ├── make_payment.php        # ✨ PayPal payment initiation
│   ├── payment_success.php     # ✨ PayPal success handler
│   └── payment_cancel.php      # ✨ PayPal cancellation handler
├── uploads/                    # User uploads (excluded from git)
├── profiles/                   # Profile picture uploads
├── vendor/                     # Composer dependencies
│   ├── auth0/                  # Auth0 PHP SDK
│   ├── guzzlehttp/             # HTTP client
│   ├── paypal/                 # ✨ PayPal Checkout SDK
│   └── ...
├── callback.php                # Auth0 callback handler
├── login.php                   # Login with Auth0 SSO
├── logout.php                  # Secure logout
├── password_reset_request.php  # Password reset request
├── password_reset.php          # Password reset confirmation
├── test_paypal.php             # ✨ PayPal integration test
├── test_auth0.php              # ✨ Auth0 integration test
├── composer.json               # Composer configuration
├── dormitory_db.sql            # Database schema
├── .gitignore
├── README.md
├── PHASE1_SUMMARY.md
├── PHASE2_SUMMARY.md
├── PHASE3_SUMMARY.md
├── PHASE4_SUMMARY.md
├── PHASE5_SUMMARY.md           # PayPal integration documentation
└── PHASE6_SUMMARY.md           # ✨ UI/UX overhaul documentation
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

### PayPal Integration Tables (Phase 5)
- `paypal_transactions` - ✨ PayPal order and capture tracking
  - `id` - Primary key
  - `tenant_id` - Foreign key to users
  - `room_id` - Foreign key to rooms
  - `booking_id` - Foreign key to bookings
  - `paypal_order_id` - PayPal order reference
  - `amount` - Transaction amount
  - `payment_period` - Number of months paid
  - `status` - pending/completed/failed/cancelled
  - `capture_id` - PayPal capture ID after successful payment
  - `payer_email` - PayPal payer email
  - `created_at`, `updated_at` - Timestamps

### Enhanced Fields (Phase 4, 4.5 & 5)
- **Bookings**: `duration_months`, `total_amount`, `approved_by`, `approved_at`, `rejected_reason`, `check_in_date`, `check_out_date`
- **Users**: `auth0_id`, `profile_picture`, `email_verified`, `remember_token`, `two_factor_enabled`
- **Rooms**: `floor_number`, `category`, `has_wifi`, `has_ac`, `has_bathroom`
- **Payments**: `paypal_transaction_id`, `paypal_capture_id` ✨ NEW

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

## New Features in Phase 6 ✨

### Enhanced Admin UI/UX
- ✅ Overhauled payments page with modern card-based stats
- ✅ Receipt modal with full transaction details
- ✅ Print-to-PDF receipt functionality
- ✅ Dual view modes (grid/table) for tenant management
- ✅ Advanced search and filtering for tenants
- ✅ Sort options (newest, oldest, name, highest paid)
- ✅ View toggle with localStorage persistence

### Tenant Details Page
- ✅ Comprehensive tenant profile view
- ✅ Stats cards (active bookings, total paid, pending, total bookings)
- ✅ Personal information section with account status
- ✅ Booking history with room details and status
- ✅ Payment history with method badges and receipts
- ✅ Account activation/deactivation controls
- ✅ Clean URL support (`/tenant_details?id=X`)

### Receipt System
- ✅ Professional receipt generation after PayPal payments
- ✅ Admin receipt viewing for all payments
- ✅ Print-optimized CSS with color preservation
- ✅ Receipt details: tenant, room, transaction IDs, amounts
- ✅ PDF export via browser print dialog

### UI Improvements
- ✅ Glassmorphism effects on profile headers
- ✅ Gradient stat cards with icons
- ✅ Status pills with dot indicators
- ✅ Payment method badges (PayPal/Cash/Bank)
- ✅ Animated card hover effects
- ✅ Responsive grid layouts

---

## New Features in Phase 5

### PayPal Payment Integration
- ✅ PayPal Checkout SDK integration (Sandbox & Production)
- ✅ Secure payment order creation with PayPal API
- ✅ Payment capture and verification
- ✅ Transaction tracking with paypal_transactions table
- ✅ Payment success and cancellation handling
- ✅ PayPal payer email and capture ID storage

### Multi-Room Payment Support
- ✅ Tenants can pay for multiple bookings
- ✅ Room selector tabs for tenants with multiple rooms
- ✅ Flexible payment duration (1-12 months)
- ✅ Dynamic total calculation based on room rate and duration
- ✅ Room filter on payment history page

### Enhanced Reports Dashboard
- ✅ Tabbed interface (Payments, Revenue, Occupancy, Tenants)
- ✅ CSV export for all report types
- ✅ Payment methods breakdown (Cash vs PayPal)
- ✅ PayPal analytics section with Chart.js
- ✅ Monthly revenue tables with export
- ✅ Room occupancy grid visualization
- ✅ Tenant activity reports

### Admin Payment Tracking
- ✅ PayPal transaction details in payment views
- ✅ Dashboard PayPal revenue statistics
- ✅ Tenant payment summaries with PayPal indicators
- ✅ Booking view with payment method display

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
8. **Make PayPal Payments** - Pay securely via PayPal for one or multiple rooms
9. **Choose Payment Duration** - Select 1-12 months for flexible payments
10. **View Payment History** - Track all transactions with PayPal details
11. **Update Profile** - Manage personal information and photo
12. **Read Announcements** - Stay informed about updates
13. **Receive Notifications** - Get instant updates on booking changes

## Development Roadmap

- [x] Phase 1: Project Setup & Database Architecture
- [x] Phase 2: User Authentication System
- [x] Phase 3: Room Management Module
- [x] Phase 4: Enhanced Booking System ✨ **COMPLETED**
- [x] Phase 4.5: Single Sign-On using Auth0 ✅
- [x] Phase 5: Payment Integration (PayPal) ✨ **COMPLETED**
- [x] Phase 6: UI/UX Overhaul & Receipt System ✨ **COMPLETED**
- [ ] Phase 7: Hostinger Production Deployment - **IN PROGRESS**
- [ ] Phase 8: Email Notifications (SMTP integration)
- [ ] Phase 9: Maintenance Request System
- [ ] Phase 10: Testing & Security Hardening
- [ ] Phase 11: Advanced Features (2FA, etc.)

## Project Statistics

### Overall Progress
- **Total Files**: 57+
- **Total Lines of Code**: 14,000+
- **Database Tables**: 14
- **Admin Pages**: 14
- **Tenant Pages**: 9
- **Public Pages**: 4
- **External Integrations**: 2 (Auth0, PayPal)
- **Composer Packages**: 5+

### Phase Completion
- ✅ Phase 1: Complete (October 2025)
- ✅ Phase 2: Complete (October 2025)
- ✅ Phase 3: Complete (November 2025)
- ✅ Phase 4: Complete (November 2025)
- ✅ Phase 4.5: Complete (November 2025)
- ✅ Phase 5: Complete (November 2025)
- ✅ Phase 6: Complete (November 2025) ✨ **NEW**
- ⏳ Phase 7: In Progress

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

## PayPal Configuration

### Setup Instructions
1. Create a PayPal Developer account at https://developer.paypal.com
2. Create a REST API app in the Developer Dashboard
3. Copy Client ID and Secret to `config/paypal_config.php`

### Required Settings in config/paypal_config.php
```php
define('PAYPAL_CLIENT_ID', 'your-client-id');
define('PAYPAL_CLIENT_SECRET', 'your-client-secret');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' or 'live'
```

### Environment Modes
- **Sandbox**: For development and testing (uses sandbox.paypal.com)
- **Live**: For production (uses api.paypal.com)

### Callback URLs
- **Success URL**: `/tenant/payment_success.php`
- **Cancel URL**: `/tenant/payment_cancel.php`

## Hostinger Deployment

### Database Configuration
Update `config/database.php` for Hostinger:
```php
$host = 'localhost';
$database = 'your_hostinger_db';
$username = 'your_hostinger_user';
$password = 'your_hostinger_password';
```

### Environment Detection
The system automatically detects localhost vs production environment for proper URL handling.

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Known Issues

- Email sending requires SMTP configuration (Phase 7)
- Remember me functionality needs cookie implementation
- Two-factor authentication is prepared but not active
- PayPal webhooks not yet implemented (manual verification only)

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

### PayPal Payment Issues
- Verify PayPal credentials in `config/paypal_config.php`
- Check PayPal mode is set correctly (sandbox/live)
- Ensure PayPal SDK is installed via Composer
- Check browser console for JavaScript errors
- Verify return URLs are accessible

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
- Payments: PayPal Checkout SDK
- HTTP Client: Guzzle
- Charts: Chart.js
- Design: Custom gradient-based modern UI
- Icons: Custom SVG icons
- Font: Inter (Google Fonts)

---

**Current Version**: 3.0.0

**Last Updated**: November 27, 2025

**Status**: ✅ Phase 1, 2, 3, 4, 4.5, 5, 6 Complete - Phase 7 (Hostinger Deployment) In Progress