# 📦 Database Import Instructions

## Quick Setup Guide

Follow these steps to import the complete ServePro database:

### **Step 1: Open phpMyAdmin**

1. Start **XAMPP Control Panel**
2. Start **Apache** and **MySQL**
3. Open browser and go to: `http://localhost/phpmyadmin`

### **Step 2: Import the SQL File**

1. In phpMyAdmin, click on **"Import"** tab (at the top)
2. Click **"Choose File"** button
3. Select: `servepro_complete_setup.sql`
4. Scroll down and click **"Go"** button at the bottom
5. Wait for the import to complete (should take a few seconds)

### **Step 3: Verify Setup**

Check if these tables were created:
- ✅ users
- ✅ properties
- ✅ amenities
- ✅ property_amenities
- ✅ property_photos
- ✅ bookings

You should see: **"Import has been successfully completed"**

### **Step 4: Login and Test**

Now you can login with these accounts:

#### 👑 **ADMIN ACCOUNT**
- **Email**: `admin@servepro.com`
- **Password**: `admin123`
- **URL**: `http://localhost/part1/admin/dashboard.php`
- **Access**: Can approve/reject properties, view all data

#### 🏠 **HOST ACCOUNT**
- **Email**: `host@servepro.com`
- **Password**: `host123`
- **URL**: `http://localhost/part1/host/dashboard.php`
- **Access**: Can add properties, manage listings

#### 👤 **GUEST ACCOUNT**
- **Email**: `guest@servepro.com`
- **Password**: `guest123`
- **URL**: `http://localhost/part1/login.php`
- **Access**: Can browse and book properties

---

## 🎯 What Gets Installed

### **Database Structure**
- Complete database: `servepro_auth`
- 6 tables with relationships
- Foreign key constraints

### **Default Data**
- ✅ **20 Amenities** (WiFi, Pool, AC, etc.)
- ✅ **3 User Accounts** (Admin, Host, Guest)
- ✅ All accounts use password: `admin123`, `host123`, `guest123`

### **Features Ready**
- User authentication
- Property listings
- Admin approval system
- Amenities filtering
- Booking system (structure ready)
- Photo upload (structure ready)

---

## 🚀 Quick Start After Import

1. **Login as Admin**:
   ```
   http://localhost/part1/login.php
   Email: admin@servepro.com
   Password: admin123
   ```

2. **Go to Admin Dashboard**:
   ```
   http://localhost/part1/admin/dashboard.php
   ```

3. **Or Login as Host to Add Properties**:
   ```
   http://localhost/part1/login.php
   Email: host@servepro.com
   Password: host123
   ```

4. **Add a Property**:
   ```
   http://localhost/part1/host/add-property.php
   ```

5. **Approve as Admin**:
   - Login as admin
   - See pending property
   - Click "Approve"

---

## ⚠️ Troubleshooting

### Import Error: Table already exists
**Solution**: 
1. In phpMyAdmin, select `servepro_auth` database
2. Click "Operations" tab
3. Scroll down to "Remove database"
4. Check "DROP TABLE" option
5. Confirm deletion
6. Import the SQL file again

### Can't Login
**Solution**:
- Make sure you're using the correct email/password
- Clear browser cache
- Try another browser
- Check if tables were created in phpMyAdmin

### Missing Tables
**Solution**:
- Re-import the SQL file
- Make sure MySQL is running in XAMPP
- Check phpMyAdmin for any error messages

---

## 📊 Database Schema Overview

```
users (id, first_name, last_name, email, password, role)
  └── properties (id, host_id, title, description, status...)
       ├── property_amenities (property_id, amenity_id)
       │    └── amenities (id, name, icon, category)
       ├── property_photos (id, property_id, photo_url)
       └── bookings (id, property_id, guest_id, check_in, check_out)
```

---

## 🔐 Security Notes

⚠️ **IMPORTANT**: These are demo accounts!

- Change all passwords after first login
- Delete demo accounts before production
- Use strong passwords in production
- Enable HTTPS in production

---

## ✅ Checklist

After import, verify:
- [ ] Database `servepro_auth` exists
- [ ] 6 tables created successfully
- [ ] 20 amenities inserted
- [ ] 3 user accounts created
- [ ] Can login as admin
- [ ] Admin dashboard loads
- [ ] Host can add property
- [ ] Admin can approve property

---

## 🎉 You're Ready!

Everything is set up and ready to use!

**Start here**: `http://localhost/part1/admin/dashboard.php`

Login with: `admin@servepro.com` / `admin123`
