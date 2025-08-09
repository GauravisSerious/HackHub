# Hackathon Management System

A comprehensive web application for organizing and managing hackathon events. This system allows for participant registration, team formation, project submission, and evaluation.

## Features

- **User Management**: Registration, login, profile management
- **Team Management**: Create teams, join teams, manage team members
- **Project Management**: Submit projects, upload files, view submissions
- **Evaluation System**: Score projects based on multiple criteria
- **Leaderboard**: Real-time rankings of projects
- **Admin Panel**: User approval, hackathon settings, team and project management

## Technology Stack

- PHP 7.4+
- MySQL 5.7+
- HTML5, CSS3, JavaScript
- Bootstrap 5
- Font Awesome
- jQuery

## Installation

1. **Clone the repository**

   ```bash
   git clone https://github.com/yourusername/hackathon-management-system.git
   cd hackathon-management-system
   ```

2. **Database Setup**

   - Create a MySQL database named `hackathon_db`
   - Import the database schema from `hackathon.sql`:

   ```bash
   mysql -u username -p hackathon_db < hackathon.sql
   ```

3. **Configuration**

   - Rename `includes/config.sample.php` to `includes/config.php`
   - Update the database connection details in `config.php`:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_database_username');
   define('DB_PASS', 'your_database_password');
   define('DB_NAME', 'hackathon_db');
   define('BASE_URL', 'http://localhost/hackathon-management-system');
   ```

4. **File Permissions**

   - Ensure the `uploads` directory has write permissions:


```
hackathon-management-system/
├── css/                    # CSS files
├── images/                 # Image assets
├── includes/               # Core PHP includes
│   ├── config.php          # Configuration file
│   ├── auth_check.php      # Authentication utilities
│   ├── functions.php       # Helper functions
│   └── ...
├── pages/                  # Page controllers
│   ├── admin.php           # Admin panel
│   ├── dashboard.php       # User dashboard
│   ├── login.php           # Login page
│   └── ...
├── uploads/                # Project file uploads
├── index.php               # Homepage
└── hackathon.sql           # Database schema
```

---

## 🔒 Security

- 🔒 Secure password hashing with `password_hash()`
- 🛡️ SQL injection prevention using prepared statements
- 🛡️ CSRF protection for all forms
- 🔍 Input validation and sanitization
- 🔄 Session security measures

---

## 🤝 Contributing

We love contributions! Here's how you can help:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---



---

<div align="center">
  <h3>Show some ❤️ by starring this repository!</h3>
  <p>Created with ☕ and ❤️ by Team Cloud</p>
</div>
