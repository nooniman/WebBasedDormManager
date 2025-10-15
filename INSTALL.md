# Dormitory Management System - Installation Guide

## Quick Start Guide

### Step 1: Prerequisites
Ensure you have the following installed:
- XAMPP (Download from: https://www.apachefriends.org/)
- A modern web browser (Chrome, Firefox, Edge, etc.)

### Step 2: Start XAMPP
1. Open XAMPP Control Panel
2. Start **Apache** server
3. Start **MySQL** server

### Step 3: Database Setup

#### Option A: Automatic Installation (Recommended)
1. Open your browser and go to:
   ```
   http://localhost/dormitory-management-system/database/install.php
   ```
2. Follow the on-screen instructions
3. Delete the `install.php` file after successful installation

#### Option B: Manual Installation
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click "New" to create a database named `dormitory_db`
3. Select the database
4. Click "Import" tab
5. Choose file: `database/schema.sql`
6. Click "Go"

### Step 4: Configure Database Connection
1. Navigate to the `config/` folder
2. If `database.php` doesn't exist, copy from `database.example.php`
3. Edit `database.php` with your credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Leave empty for default XAMPP
   define('DB_NAME', 'dormitory_db');
   ```

### Step 5: Set Directory Permissions
Make sure the `uploads/` directory is writable:
- On Windows: Right-click → Properties → Security → Edit → Allow "Full Control"
- On Linux/Mac: `chmod 755 uploads/`

### Step 6: Access the Application
Open your browser and navigate to:
```
http://localhost/dormitory-management-system/
```

### Step 7: Login

**Administrator Account:**
- Email: `admin@dormitory.com`
- Password: `Admin123!`

**Test Tenant Account:**
- Email: `tenant@example.com`
- Password: `Tenant123!`

⚠️ **Important**: Change these default passwords immediately!

## Common Issues and Solutions

### Issue: "Database connection failed"
**Solution**: 
- Verify MySQL is running in XAMPP
- Check `config/database.php` credentials
- Ensure database `dormitory_db` exists

### Issue: "Page not found" or 404 errors
**Solution**:
- Verify the project is in the correct path: `C:\xampp\htdocs\dormitory-management-system`
- Check that Apache is running
- Try: `http://localhost/dormitory-management-system/public/index.php`

### Issue: "Permission denied" for uploads
**Solution**:
- Check folder permissions for `uploads/` directory
- On Windows, ensure the folder is not read-only

### Issue: White screen or PHP errors
**Solution**:
- Enable error reporting in PHP
- Check Apache error logs in `xampp/apache/logs/error.log`

## Testing the System

### Test as Administrator:
1. Login with admin credentials
2. Navigate to "Rooms" and add a new room
3. Check "Bookings" page
4. Create an announcement
5. View reports

### Test as Tenant:
1. Logout from admin account
2. Login with tenant credentials
3. Browse available rooms
4. Submit a booking request
5. Check your portal dashboard

## Security Checklist

- [ ] Changed default admin password
- [ ] Changed default tenant password
- [ ] Deleted `database/install.php` after installation
- [ ] Verified `config/database.php` is not in Git repository
- [ ] Tested CSRF protection on forms
- [ ] Checked that uploads directory is protected

## Next Steps

1. **Customize the System**
   - Add your logo to `assets/images/`
   - Modify colors in `assets/css/style.css`
   - Update contact information

2. **Add More Rooms**
   - Login as admin
   - Go to Rooms management
   - Add your actual room inventory

3. **Create User Accounts**
   - Register tenant accounts
   - Or create them via admin panel

4. **Configure Email** (Future Enhancement)
   - Set up SMTP for notifications
   - Configure booking confirmations

## Project Structure Quick Reference

```
dormitory-management-system/
├── admin/          → Admin panel pages
├── tenant/         → Tenant portal pages
├── public/         → Public-facing pages
├── config/         → Configuration files
├── includes/       → Reusable PHP includes
├── assets/         → CSS, JS, Images
├── database/       → SQL schema and installation
├── uploads/        → User uploaded files
├── login.php       → Login page
└── logout.php      → Logout handler
```

## Support

For issues or questions:
1. Check the README.md file
2. Review error logs in XAMPP
3. Verify all installation steps

## Development Mode

To enable detailed error messages for debugging:
1. Edit `php.ini` in XAMPP
2. Set: `display_errors = On`
3. Restart Apache

**Remember to turn this off in production!**

---

**Congratulations!** Your Dormitory Management System is now ready to use. 🎉
