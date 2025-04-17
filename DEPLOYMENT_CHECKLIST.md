# Astra C2 Deployment Checklist

Use this checklist to ensure that your Astra C2 deployment is complete and properly configured.

## Local Development Environment

- [ ] PHP 7.0 or higher installed
- [ ] SQLite database created in `database/astra_c2.db`
- [ ] Development server running with `php -S 0.0.0.0:5000 -t public_html`
- [ ] Admin panel accessible at http://localhost:5000
- [ ] Successfully logged in with default credentials
- [ ] Basic functionality works (dashboard, devices, commands, logs)

## Production Deployment (Hostinger)

### Database Setup

- [ ] Created MySQL database in Hostinger control panel
- [ ] Noted database credentials (host, port, name, user, password)
- [ ] Imported `setup_mysql.sql` via phpMyAdmin or ran `setup_mysql.php`
- [ ] Verified database tables were created correctly

### File Configuration

- [ ] Created `.env` file from `.env.example` in `/public_html/c2/config/`
- [ ] Updated MySQL credentials in `.env` file:
  ```
  DB_TYPE=mysql
  DB_HOST=your-mysql-host
  DB_PORT=3306
  DB_NAME=your-database-name
  DB_USER=your-database-username
  DB_PASSWORD=your-database-password
  ```
- [ ] Set `APP_DEBUG=false` in production environment
- [ ] Generated and set a unique `ENCRYPTION_KEY` in the `.env` file

### File Upload

- [ ] Uploaded all files from `public_html/` to Hostinger public_html folder
- [ ] Ensured permissions are set correctly:
  ```
  chmod 755 public_html/c2
  chmod 644 public_html/c2/config/.env
  ```

### Application Testing

- [ ] Accessed admin panel at your domain
- [ ] Successfully logged in with default credentials
- [ ] Changed default admin password
- [ ] Verified dashboard loads without errors
- [ ] Tested device registration API endpoint
- [ ] Verified logs and commands functionality

### Android Client

- [ ] Updated server URL in `Config.java` with your domain
- [ ] Built APK file with Android Studio
- [ ] Installed application on test device
- [ ] Verified device registration with server
- [ ] Tested command execution

### Security Checks

- [ ] Using HTTPS for all communication
- [ ] Changed default admin password
- [ ] Sensitive files (.env) are protected with proper permissions
- [ ] `.htaccess` rules are working to prevent direct access to includes
- [ ] API rate limiting is working
- [ ] Authentication tokens are properly validated

## Notes

* Document any issues encountered during deployment and their solutions
* Keep your database credentials secure
* Regularly back up your database
* Monitor server logs for any unusual activity