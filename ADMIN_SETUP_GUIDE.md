# 👑 Admin Dashboard Setup Guide

## Quick Access URLs

- **Admin Dashboard**: `http://localhost/part1/admin/dashboard.php`
- **Admin Panel**: `http://localhost/part1/admin/`

## 🔧 Setup Steps

### 1. Make User an Admin

Open **phpMyAdmin** or run this SQL query:

```sql
-- Make yourself admin (replace with your email)
UPDATE users SET role='admin' WHERE email='your@email.com';

-- Or by user ID
UPDATE users SET role='admin' WHERE id=1;
```

### 2. Login and Access

1. Login at: `http://localhost/part1/login.php`
2. Go to: `http://localhost/part1/admin/dashboard.php`
3. Or click **"👑 Admin Panel"** button from your dashboard

## ✅ Admin Features

### Dashboard Overview
- 📊 **Statistics Cards**
  - Total Properties
  - Pending Reviews
  - Total Users
  - Total Bookings

### Property Approval
- 🏠 **Pending Properties List**
  - Full property details
  - Host information
  - Property description
  - Room details (beds, baths, guests)
  - Price per night

### Actions
- ✅ **Approve** - Makes property live
- ❌ **Reject** - Denies listing
- 👁️ **View Details** - See full property info

## 📋 Admin Workflow

```
1. Host submits property
   ↓
2. Status: "Pending"
   ↓
3. Admin sees in dashboard
   ↓
4. Admin reviews details
   ↓
5. Admin clicks "Approve" or "Reject"
   ↓
6. Property status updates
   ↓
7. Host sees status change
```

## 🎯 Testing the Approval System

### Create Test Properties

1. **Register a new user** (or use existing)
2. **Make them a host**: 
   ```sql
   UPDATE users SET role='host' WHERE id=2;
   ```
3. **Access Host Dashboard**: `http://localhost/part1/host/dashboard.php`
4. **Add a property** with full details and amenities
5. **Submit** (status will be "pending")

### Approve as Admin

1. **Login as admin**
2. **Go to Admin Dashboard**: `http://localhost/part1/admin/dashboard.php`
3. **See the pending property**
4. **Click "✓ Approve"**
5. **Property is now live!**

### Verify Approval

1. **Go back to host dashboard**
2. **Property status** now shows "Approved" (green badge)
3. **Property visible** on main landing page

## 🗄️ Database Tables

The admin system uses these tables:

- **users** - User accounts with roles
- **properties** - Property listings with status
- **amenities** - Available amenities (20 pre-loaded)
- **property_amenities** - Property-amenity relationships
- **bookings** - Guest reservations

## 🔐 Role Types

```sql
-- Guest (default)
role = 'guest'

-- Host (can list properties)
role = 'host'

-- Admin (can approve/reject)
role = 'admin'
```

## 📊 Property Statuses

- **pending** - Waiting for admin review
- **approved** - Live on platform
- **rejected** - Denied by admin
- **suspended** - Temporarily disabled

## 🎨 Admin Panel Design

- **Red theme** for admin identification
- **Card-based layout** for easy scanning
- **One-click approval** system
- **Responsive design** for all devices

## 🚀 Quick Commands

```sql
-- View all pending properties
SELECT * FROM properties WHERE status='pending';

-- View all admin users
SELECT * FROM users WHERE role='admin';

-- Approve property manually
UPDATE properties SET status='approved' WHERE id=1;

-- Check property with amenities
SELECT p.title, GROUP_CONCAT(a.name) as amenities
FROM properties p
LEFT JOIN property_amenities pa ON p.id = pa.property_id
LEFT JOIN amenities a ON pa.amenity_id = a.id
WHERE p.id = 1;
```

## 💡 Tips

- Keep the admin panel open in a separate browser/tab
- Review properties promptly for better host experience
- Check property details thoroughly before approval
- Use reject only when necessary (provide feedback if possible)

---

**Ready to manage your platform! 👑**

Access: `http://localhost/part1/admin/dashboard.php`
