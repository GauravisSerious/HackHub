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

   ```bash
   chmod 755 uploads
   ```

5. **Web Server Setup**

   - Configure your web server (Apache/Nginx) to point to the project directory
   - If using Apache, the `.htaccess` file is already included

## Usage

1. **Admin Access**

   - Default admin credentials:
     - Email: admin@example.com
     - Password: admin123
   - After logging in, access the admin panel to configure hackathon settings

2. **Participant Registration**

   - Users can register as participants
   - Admin approval is required before participants can access the system

3. **Team Formation**

   - Approved participants can create or join teams
   - Team leaders can approve/reject join requests

4. **Project Submission**

   - Teams can submit project details, including:
     - Title and description
     - Technologies used
     - GitHub repository links
     - Demo links
     - Supporting files

5. **Project Evaluation**

   - Admins can evaluate projects on criteria such as:
     - Innovation
     - Functionality
     - Code quality
     - Presentation

6. **Leaderboard**

   - View the rankings of all evaluated projects
   - Top projects are highlighted

## Directory Structure

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

## Security Considerations

- All user inputs are validated and sanitized
- Passwords are securely hashed using PHP's password_hash()
- SQL injection protection through prepared statements
- CSRF protection for form submissions

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contributors

- Your Name - Initial work 