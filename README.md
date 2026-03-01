# ReservePro Authentication System

A modern, secure login and registration system built with PHP, featuring a sleek and professional design.

## Features

- ✨ **Beautiful Professional UI** - Clean, modern interface with smooth animations
- 🔒 **Secure Authentication** - Password hashing with PHP's `password_hash()`
- ✅ **Real-time Validation** - Client-side form validation with instant feedback
- 📱 **Responsive Design** - Works perfectly on all devices
- 🎨 **Modern CSS** - Custom properties, gradients, and animations
- 🚀 **Session Management** - Secure session handling
- 💾 **MySQL Database** - Automatic database and table creation

## Installation

### Prerequisites

- XAMPP (or any PHP development environment)
- MySQL database
- Modern web browser

### Setup Steps

1. **Clone/Copy files** to your XAMPP htdocs directory:
   ```
   C:\xampp\htdocs\part1\
   ```

2. **Start XAMPP services**:
   - Start Apache
   - Start MySQL

3. **Database Configuration**:
   The database and tables are automatically created on first run. Default settings:
   - Host: `localhost`
   - User: `root`
   - Password: `` (empty)
   - Database: `servepro_auth`

   To change these settings, edit `config/database.php`

4. **Access the application**:
   ```
   http://localhost/part1/
   ```

## File Structure

```
part1/
├── assets/
│   ├── css/
│   │   └── style.css          # ReservePro CSS
│   └── js/
│       └── validation.js      # Form validation & UX
├── config/
│   ├── database.php           # Database configuration
│   └── session.php            # Session management
├── includes/
│   └── auth.php               # Authentication logic
├── index.php                  # Landing page
├── register.php               # Registration page
├── login.php                  # Login page
├── dashboard.php              # User dashboard
├── logout.php                 # Logout handler
└── README.md                  # This file
```

## Usage

### Registration

1. Navigate to `http://localhost/part1/register.php`
2. Fill in your details:
   - First Name
   - Last Name
   - Email
   - Password (minimum 8 characters)
   - Confirm Password
3. Click "Sign up"
4. You'll be automatically logged in and redirected to the dashboard

### Login

1. Navigate to `http://localhost/part1/login.php`
2. Enter your email and password
3. Click "Log in"
4. You'll be redirected to your dashboard

### Logout

Click the "Log out" button on the dashboard or navigate to `logout.php`

## Security Features

- **Password Hashing**: Uses PHP's `PASSWORD_DEFAULT` algorithm
- **Prepared Statements**: All database queries use prepared statements to prevent SQL injection
- **Input Validation**: Both client-side and server-side validation
- **Session Security**: Secure session management with proper checks
- **XSS Protection**: All output is escaped with `htmlspecialchars()`

## Customization

### Change Colors

Edit CSS variables in `assets/css/style.css`:

```css
:root {
    --airbnb-red: #FF385C;
    --airbnb-red-dark: #E31C5F;
    --airbnb-gray: #717171;
    --airbnb-light-gray: #F7F7F7;
    --airbnb-border: #DDDDDD;
    --airbnb-black: #222222;
}
```

### Change Database Settings

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'servepro_auth');
```

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Troubleshooting

### Database Connection Error

- Make sure MySQL is running in XAMPP
- Check database credentials in `config/database.php`
- Verify the database user has proper permissions

### Page Not Found

- Ensure Apache is running in XAMPP
- Check that files are in the correct directory: `C:\xampp\htdocs\part1\`
- Try accessing: `http://localhost/part1/index.php`

### CSS/JS Not Loading

- Clear your browser cache
- Check browser console for errors
- Verify file paths are correct

## Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Design**: Professional ReservePro UI

## License

This project is open source and available for educational purposes.

## Credits

Professional design for ReservePro authentication system.
