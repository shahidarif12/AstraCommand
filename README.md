# Astra C2 – Lightweight Advanced Command & Control System

Astra C2 is a lightweight command and control system with PHP backend, MySQL database, and Android client for device monitoring and management.

## Features

- PHP backend for shared hosting compatibility
- MySQL database for storing device information, commands, and logs
- Bootstrap with Google Material UI admin panel
- Android client for device monitoring and command execution
- API endpoints for device communication
- Log collection and viewing
- Command execution system
- Basic security implementations

## System Requirements

### Server

- PHP 7.0 or higher
- MySQL/MariaDB database
- curl, zip/unzip utilities
- Shared hosting environment (like Hostinger) or any PHP-compatible web server

### Android Client

- Android 6.0 (Marshmallow) or higher
- Internet connectivity
- Various permissions for device monitoring

## Directory Structure

- `public_html/` - Web accessible files
  - `c2/` - Main application directory
    - `api/` - API endpoints for device communication
    - `assets/` - CSS, JavaScript, and other assets
    - `config/` - Configuration files
    - `includes/` - Shared PHP includes
    - `panel/` - Admin panel interface
- `android_client/` - Android client source code
- `database/` - Database setup files (development use)
- `setup_mysql.sql` - MySQL database setup script
- `setup_mysql.php` - Alternative MySQL setup script

## Deployment Instructions

### Local Development

1. Clone or download this repository
2. Make sure PHP is installed locally
3. Start the PHP development server:
   ```
   php -S 0.0.0.0:5000 -t public_html
   ```
4. Access the admin panel at http://localhost:5000
5. Default login: username `admin`, password `admin123`

### Hostinger Shared Hosting Deployment

1. **Prepare your local files**
   - Copy `public_html/c2/config/.env.example` to `public_html/c2/config/.env`
   - Update the `.env` file with your MySQL credentials:
     ```
     DB_TYPE=mysql
     DB_HOST=your-mysql-host
     DB_PORT=3306
     DB_NAME=your-database-name
     DB_USER=your-database-username
     DB_PASSWORD=your-database-password
     ```
   - Ensure `APP_DEBUG=false` in production environment

2. **Set up the MySQL database**
   - Log in to your Hostinger control panel
   - Navigate to the MySQL Databases section
   - Create a new MySQL database
   - Note your database name, username, and password

3. **Upload files to Hostinger**
   - Use FTP or the Hostinger File Manager
   - Upload all files from the `public_html` directory to your Hostinger public_html folder
   - Upload `setup_mysql.sql` and `setup_mysql.php` to your root directory

4. **Set up the database**
   - Method 1: Use phpMyAdmin
     - Log in to phpMyAdmin from your Hostinger control panel
     - Select your database
     - Go to the Import tab
     - Upload and import the `setup_mysql.sql` file
   
   - Method 2: Use the PHP setup script
     - Edit `setup_mysql.php` with your database credentials
     - Access the script via your browser: `https://yourdomain.com/setup_mysql.php`
     - This will create all necessary tables and the default admin user

5. **Configure the application**
   - Ensure your `.env` file in `public_html/c2/config/` has the correct MySQL credentials
   - Set appropriate permissions:
     ```
     chmod 755 public_html/c2
     chmod 644 public_html/c2/config/.env
     ```

6. **Access your admin panel**
   - Visit `https://yourdomain.com`
   - Login with the default credentials (username: `admin`, password: `admin123`)
   - Change the default password immediately after first login

### Android Client Setup

1. Update the `Config.java` file in the Android client with your server domain:
   ```java
   public static final String SERVER_URL = "https://yourdomain.com/c2/api/";
   ```

2. Build the Android client using Android Studio and install on target devices

## Security Considerations

1. Change the default admin password immediately
2. Use HTTPS for all communications
3. Set proper file permissions
4. Keep your server and PHP installation updated
5. Consider implementing additional authentication methods
6. Review code for security vulnerabilities before deployment
7. Use strong, unique passwords for database and admin accounts

## Troubleshooting

If you encounter any issues during setup, check the following:

1. Database connection issues:
   - Verify your MySQL credentials in the `.env` file
   - Ensure the database exists and has the correct permissions
   - Check for any firewall restrictions

2. API connection issues:
   - Verify your server URL in the Android client
   - Ensure all API endpoints are accessible
   - Check your server's mod_rewrite configuration

3. File permission issues:
   - Ensure all files have the correct permissions
   - The web server must be able to read all files
   - Config directory needs write permissions for log files

## Support

For additional support or to report issues, please contact the developer.

## License

This software is distributed for educational purposes only.
