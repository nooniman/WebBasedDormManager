# Bedspace Management Submodule

## 📋 Overview

A dedicated administrative submodule has been created at `admin/bedspaces.php` to manage all bedspace-related operations in a centralized interface. This module provides comprehensive bedspace management capabilities including:

- **Real-time Statistics Dashboard**
- **Visual Bedspace Grid Management**
- **Room-by-Room Overview**
- **Tenant Assignment & Release**
- **Status Management**
- **Advanced Filtering & Search**

---

## 🎯 Features Implemented

### 1. **Statistics Dashboard**

Five key metric cards displaying:
- **Total Bedspace Rooms** - Count of rooms configured for bedspacing
- **Total Bedspaces** - Total individual bed slots across all rooms
- **Available Bedspaces** - Currently unoccupied slots with percentage
- **Occupied Bedspaces** - Current occupancy with percentage rate
- **Monthly Revenue** - Projected revenue from occupied bedspaces

### 2. **Two-Tab Interface**

#### Tab 1: All Bedspaces View
- **Card-based grid layout** showing all individual bedspaces
- **Color-coded status indicators**:
  - 🟢 Green = Available
  - 🔵 Blue = Occupied (with tenant info)
  - 🟡 Yellow = Maintenance
- **Tenant information display** (name, email, phone)
- **Quick action buttons**:
  - Status toggle for available/maintenance
  - Release bedspace for occupied slots

#### Tab 2: Room Overview
- **Room-centric view** showing all bedspace rooms
- **Visual occupancy bars** with percentage
- **Bedspace slot visualization** (A, B, C, D format)
- **Key statistics per room**:
  - Total beds
  - Occupied count
  - Available count
  - Price per bedspace
- **Direct link** to room details page

### 3. **Advanced Filtering System**

Three filter options + search:
- **Room Filter** - Dropdown showing all bedspace rooms with current occupancy
- **Status Filter** - All/Available/Occupied/Maintenance
- **Search** - Room number, bedspace letter, tenant name
- **Auto-submit** on filter change for instant results

### 4. **Bedspace Management Actions**

#### For Available/Maintenance Bedspaces:
- Inline status dropdown to switch between available/maintenance
- Auto-submits form on change

#### For Occupied Bedspaces:
- Displays current tenant information (name, email, phone)
- **Release Bedspace** button with confirmation modal
- Upon release:
  - Sets bedspace status to 'available'
  - Removes tenant assignment
  - Decrements room's occupied_bedspaces count

### 5. **Safety Features**

- **CSRF token protection** on all form submissions
- **Confirmation modal** for destructive actions (release bedspace)
- **Flash messages** for user feedback
- **Real-time validation** via dropdown selections

---

## 🗂️ File Structure

```
admin/
├── bedspaces.php          # NEW - Main bedspace management module
├── rooms.php              # Has bedspace queries
├── room_details.php       # Bedspace conversion & management
├── room_add.php           # Create rooms with bedspacing
├── bookings.php           # Shows bedspace info
├── view_booking.php       # Displays bedspace details
└── payments.php           # Bedspace in payment records

includes/
├── header.php             # UPDATED - Added "Bedspaces" nav link
└── bedspace_functions.php # All bedspace utility functions
```

---

## 🔗 Navigation Integration

The "Bedspaces" link has been added to the admin navigation menu:

**Admin Navigation:**
```
Dashboard > Tenants > Rooms > Bedspaces > Bookings > Payments > Announcements > Reports
```

**Location in header.php:**
- Positioned after "Rooms" and before "Bookings"
- Uses bed icon SVG for visual identification
- Active state highlighting when on bedspaces.php

---

## 💾 Database Integration

The module uses existing bedspace tables and functions:

### Tables Used:
- `rooms` - is_bedspace, total_bedspaces, occupied_bedspaces, price_per_bedspace
- `bedspaces` - id, room_id, bedspace_number, status, current_tenant_id
- `users` - Tenant information for occupied bedspaces

### Functions Used (from bedspace_functions.php):
- `get_bedspace_stats()` - Dashboard statistics
- `get_room_bedspaces()` - Fetch bedspaces for a room
- `update_bedspace_status()` - Change bedspace status
- `release_bedspace()` - Remove tenant from bedspace
- `get_available_bedspaces()` - Filter available slots

---

## 🎨 UI/UX Features

### Design Elements:
- **Gradient header** with purple/violet theme (matching admin style)
- **Hover effects** on cards with subtle lift animation
- **Status-based color coding** throughout
- **Responsive grid layouts** (auto-fill, minmax)
- **Modal overlays** for confirmations
- **Empty states** with helpful messages

### Visual Indicators:
- 📊 **Occupancy bars** - Gradient progress bars
- 🏠 **Room badges** - Violet background with room number
- 👤 **Tenant info boxes** - Gray background containers
- 🔄 **Status badges** - Color-coded pills (green/blue/yellow)

### Interactive Elements:
- **Tab switching** - JavaScript-powered instant tab change
- **Filter dropdowns** - Auto-submit forms
- **Action buttons** - Contextual based on bedspace status
- **Modal dialogs** - Confirm dangerous operations

---

## 📊 Statistics Calculation

### Monthly Revenue Formula:
```php
SUM(price_per_bedspace * occupied_bedspaces) FROM rooms WHERE is_bedspace = TRUE
```

### Occupancy Percentage:
```php
(occupied_bedspaces / total_bedspaces) * 100
```

### Available Count:
```php
total_bedspaces - occupied_bedspaces
```

---

## 🔐 Security Features

1. **CSRF Protection** - All forms include `csrf_token` verification
2. **Input Sanitization** - `sanitize_input()` on all user inputs
3. **Prepared Statements** - All database queries use parameter binding
4. **Session Validation** - `admin_auth.php` required at page level
5. **Status Validation** - Only allowed statuses accepted

---

## 🚀 Usage Examples

### Release a Bedspace:
1. Navigate to **Admin > Bedspaces**
2. Find occupied bedspace card (blue border)
3. Click **"Release Bedspace"** button
4. Confirm in modal dialog
5. System automatically:
   - Sets status to 'available'
   - Removes tenant_id
   - Updates room occupancy count

### Change Bedspace Status:
1. Find available or maintenance bedspace
2. Use inline dropdown to select new status
3. Form auto-submits on change
4. Flash message confirms update

### Filter Bedspaces:
1. Select **Room** from dropdown (shows occupancy)
2. Select **Status** filter
3. Enter search term (room/bedspace/tenant)
4. Click **Filter** button
5. Results update instantly

### View Room Overview:
1. Click **"Room Overview"** tab
2. See all bedspace rooms with visual grids
3. Click **"View Details"** to go to room_details.php
4. Each room shows A/B/C/D slots colored by status

---

## 📱 Responsive Design

**Desktop (>1024px):**
- 5-column statistics grid
- 3-column bedspace cards
- 3-column room overview cards
- 4-column filter grid

**Tablet (768px-1024px):**
- 3-column statistics
- 2-column cards
- 2-column filters

**Mobile (<768px):**
- Stacked single column
- Full-width cards
- Vertical filter layout

---

## 🎯 Integration with Existing Features

### Connected Pages:

1. **admin/rooms.php**
   - Shows bedspace availability in room listings
   - "Manage Bedspaces" action button links to room_details.php

2. **admin/room_details.php**
   - Full bedspace management interface
   - Convert regular room to bedspace
   - Manage individual bedspace statuses

3. **admin/bookings.php**
   - Shows bedspace info in booking records
   - Displays "Bedspace A" in room column

4. **admin/view_booking.php**
   - Full bedspace details in booking view
   - Shows bedspace number and status

5. **public/bedspaces.php**
   - Frontend bedspace browsing (tenant-facing)
   - Links to booking page with bedspace selection

---

## ✅ Tested Functionality

All features have been verified:
- ✅ Statistics display correctly from database
- ✅ Tab switching works without page reload
- ✅ Filters apply properly with URL parameters
- ✅ Search functionality works across fields
- ✅ Status updates save to database
- ✅ Release bedspace updates room occupancy
- ✅ Modal dialogs open/close properly
- ✅ CSRF tokens validate correctly
- ✅ Navigation link highlights on active page
- ✅ Responsive layouts work on all screens

---

## 🔧 Future Enhancement Possibilities

1. **Bulk Operations** - Select multiple bedspaces for batch status changes
2. **Assignment Interface** - Directly assign tenants from this page
3. **History Log** - Show bedspace occupancy history
4. **Export Functionality** - Generate Excel/PDF reports
5. **Analytics Charts** - Visual graphs of occupancy trends
6. **Calendar View** - Show bedspace bookings on timeline
7. **Notification System** - Alert when bedspaces become available
8. **Price Management** - Inline editing of price_per_bedspace

---

## 📝 Code Quality

- **PHP 8.2+ compatible** - Uses modern syntax
- **SQL injection safe** - Prepared statements throughout
- **XSS protected** - htmlspecialchars() on all output
- **Well commented** - Clear inline documentation
- **Consistent styling** - Matches admin panel theme
- **Error handling** - Try-catch for critical operations
- **DRY principle** - Reuses bedspace_functions.php utilities

---

## 🎓 Developer Notes

### Key Functions:
```php
get_bedspace_stats($conn)          // Dashboard statistics
get_room_bedspaces($conn, $room_id) // Fetch room's bedspaces
update_bedspace_status($conn, $id, $status) // Change status
release_bedspace($conn, $id)       // Remove tenant assignment
```

### CSS Classes:
```css
.bedspace-card                     // Individual bedspace container
.bedspace-card.available           // Green theme
.bedspace-card.occupied            // Blue theme with tenant info
.bedspace-card.maintenance         // Yellow theme
.room-overview-card                // Room summary container
.bedspaces-visual                  // A/B/C/D slot grid
```

### JavaScript Functions:
```javascript
switchTab(tabName, btn)            // Tab navigation
releaseBedspace(id, room, bed)     // Open release modal
closeReleaseModal()                // Close modal
```

---

## 📞 Support Integration

The module is fully integrated with the existing bedspace ecosystem:

- **Backend Functions**: Uses `includes/bedspace_functions.php`
- **Authentication**: Protected by `includes/admin_auth.php`
- **Flash Messages**: Uses `includes/functions.php` messaging system
- **Database**: Connects via `config/database.php`
- **Navigation**: Integrated in `includes/header.php`

---

## 🎉 Summary

The admin bedspace management submodule provides a **centralized, intuitive interface** for managing all bedspace operations. It combines:
- 📊 Real-time statistics
- 👥 Tenant management
- 🏠 Room-level overview
- 🔍 Advanced filtering
- ⚡ Quick actions
- 🎨 Modern UI/UX

This module complements the existing bedspace features in other admin pages (rooms, bookings, payments) and provides a **dedicated workspace** for administrators to efficiently manage the bedspacing system.

**Location:** `admin/bedspaces.php`  
**Navigation:** Admin Menu > Bedspaces  
**Status:** ✅ Production Ready
