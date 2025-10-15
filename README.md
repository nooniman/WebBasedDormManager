# Dormitory Management System

A comprehensive web-based dormitory management system built with PHP, MySQL, JavaScript, HTML, and CSS.

## Features

### For Administrators
- **Dashboard**: View statistics and system overview
- **Tenant Management**: Manage tenant accounts and information
- **Room Management**: Add, edit, and manage room inventory
- **Booking Management**: Approve or reject booking requests
- **Payment Tracking**: Monitor and manage payment records
- **Announcements**: Create and publish announcements for tenants
- **Reports**: Generate occupancy and revenue reports

### For Tenants
- **Room Browsing**: View available rooms with details and photos
- **Online Booking**: Submit booking requests easily
- **Personal Portal**: View booking status and payment history
- **Profile Management**: Update personal information
- **Announcements**: Stay updated with important notices

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Server**: Apache (XAMPP)

## Installation

### Prerequisites
- XAMPP (or similar LAMP/WAMP stack)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

### Setup Instructions

1. **Clone or Download the Project**
   ```bash
   git clone <repository-url>
   cd dormitory-management-system
   ```

2. **Configure Database**
   - Start XAMPP and ensure Apache and MySQL are running
   - Copy `config/database.example.php` to `config/database.php`
   - Edit `config/database.php` with your database credentials

3. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the database schema:
     - Click "New" to create a database named `dormitory_db`
     - Select the database and click "Import"
     - Choose `database/schema.sql` and click "Go"

4. **Set Permissions**
   - Ensure the `uploads/` directory is writable:
     ```bash
     chmod 755 uploads/
     ```

5. **Access the Application**
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
│   ├── dashboard.php
│   ├── tenants.php
│   ├── rooms.php
│   ├── payments.php
│   ├── bookings.php
│   ├── announcements.php
│   └── reports.php
├── assets/                 # Static assets
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── images/
├── config/                 # Configuration files
│   ├── database.php        # Database configuration (excluded from git)
│   └── database.example.php
├── database/               # Database schema
│   └── schema.sql
├── includes/               # Reusable components
│   ├── header.php
│   ├── footer.php
│   ├── functions.php
│   ├── admin_auth.php
│   └── tenant_auth.php
├── public/                 # Public pages
│   ├── index.php
│   ├── rooms.php
│   └── booking.php
├── tenant/                 # Tenant portal
│   ├── portal.php
│   └── profile.php
├── uploads/                # User uploads (excluded from git)
├── login.php
├── logout.php
├── .gitignore
└── README.md
```

## Security Features

- **Password Hashing**: Using PHP's `password_hash()` with bcrypt
- **CSRF Protection**: Token-based CSRF prevention
- **SQL Injection Prevention**: Prepared statements for all queries
- **XSS Prevention**: Input sanitization and output escaping
- **Session Management**: Secure session handling with regeneration
- **Access Control**: Role-based authentication guards
- **Input Validation**: Client-side and server-side validation

## Usage Guide

### For Administrators

1. **Login** to the admin panel using admin credentials
2. **Add Rooms** through the Rooms management page
3. **Manage Tenants** - view and monitor tenant accounts
4. **Process Bookings** - approve or reject booking requests
5. **Track Payments** - monitor payment records
6. **Post Announcements** - communicate with tenants
7. **Generate Reports** - view occupancy and revenue statistics

### For Tenants

1. **Register/Login** to your account
2. **Browse Rooms** - view available rooms
3. **Submit Booking** - request to book a room
4. **View Portal** - check booking status and payment history
5. **Update Profile** - manage personal information
6. **Read Announcements** - stay informed

## Development Roadmap

- [ ] Phase 1: Project Setup & Database Architecture (Complete)
- [ ] Phase 2: User Authentication System
- [ ] Phase 3: Room Management Module
- [ ] Phase 4: Booking System
- [ ] Phase 5: Payment Tracking
- [ ] Phase 6: Reports & Analytics
- [ ] Phase 7: Testing & Security Hardening
- [ ] Phase 8: Deployment

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is developed for educational purposes.

## Support

For support and questions, please contact the system administrator.

---

**Note**: This is Phase 1 of the project. Additional features and improvements will be added in subsequent phases.
