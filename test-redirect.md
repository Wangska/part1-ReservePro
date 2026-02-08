# Role-Based Redirect Testing Guide

## 🎯 How Redirects Work After Signup

### **Guest Registration**
1. User selects "🏖️ Browse & Book Properties (Guest)"
2. Completes registration
3. **Redirects to**: `dashboard.php` (Regular user dashboard)

### **Host Registration**
1. User selects "🏠 List My Properties (Host)"
2. Completes registration
3. **Redirects to**: `host/dashboard.php` (Host dashboard to add properties)

### **Admin Login** (Special Case)
1. Admin logs in (cannot register via public form)
2. **Redirects to**: `admin/dashboard.php` (Admin panel)

---

## 🧪 Testing Steps

### Test 1: Register as Guest
1. Go to: `http://localhost/part1/home.php`
2. Click "Sign up" button (modal opens)
3. Select: "🏖️ Browse & Book Properties (Guest)"
4. Fill in details
5. Submit
6. ✅ Should redirect to: `dashboard.php`

### Test 2: Register as Host
1. Go to: `http://localhost/part1/home.php`
2. Click "Sign up" button (modal opens)
3. Select: "🏠 List My Properties (Host)"
4. Fill in details
5. Submit
6. ✅ Should redirect to: `host/dashboard.php`

### Test 3: Login Based on Existing Role
1. Login with existing account
2. ✅ Guest → `dashboard.php`
3. ✅ Host → `host/dashboard.php`
4. ✅ Admin → `admin/dashboard.php`

---

## 📊 Database Verification

After signup, check in phpMyAdmin:

```sql
SELECT id, first_name, last_name, email, role 
FROM users 
ORDER BY id DESC 
LIMIT 5;
```

Verify the `role` column matches what was selected!

---

## ✅ Expected Results

| Selected Role | Database Value | Redirect Destination |
|--------------|----------------|---------------------|
| Guest | `role = 'guest'` | `/dashboard.php` |
| Host | `role = 'host'` | `/host/dashboard.php` |
| (Login) Admin | `role = 'admin'` | `/admin/dashboard.php` |

---

## 🎨 Visual Indicators

When selecting role, you should see:
- **Guest**: Purple/Blue info box
- **Host**: Green info box
- Clear message about where you'll be redirected

---

## 🔄 Complete Flow Example

### Host Registration Flow:
```
1. Click "Sign up" → Modal opens
   ↓
2. Select "Host" from dropdown
   ↓
3. Green box shows: "You'll go to Host Dashboard"
   ↓
4. Fill form and submit
   ↓
5. Account created with role='host'
   ↓
6. Auto-login happens
   ↓
7. Redirect to: host/dashboard.php
   ↓
8. Can immediately add properties!
```

---

## 🎉 All Working!

Your signup system now:
- ✅ Shows role selection
- ✅ Saves role to database
- ✅ Redirects based on selected role
- ✅ Visual feedback of redirect destination
- ✅ Works from both modal and page
