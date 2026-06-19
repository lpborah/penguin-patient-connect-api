# Server Deployment Instructions

## Important: Configure .env on Server

On your AWS server at `/var/www/html/api/`, create a `.env` file with:

```env
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password

# BASE_PATH configuration:
# If URL is https://healthtech.com/api/getMessages
# Then BASE_PATH=/api
BASE_PATH=/api
```

## Apache Configuration Required

Ensure Apache has mod_rewrite enabled and AllowOverride set:

```bash
# Enable mod_rewrite
sudo a2enmod rewrite

# Edit Apache config
sudo nano /etc/apache2/sites-available/000-default.conf
```

Add this inside `<VirtualHost *:80>`:

```apache
<Directory /var/www/html/api>
    AllowOverride All
    Require all granted
</Directory>
```

Then restart Apache:
```bash
sudo systemctl restart apache2
```

## Files to Upload

1. All project files including:
   - `public/` folder
   - `src/` folder  
   - `vendor/` folder (or run `composer install` on server)
   - `.htaccess` (root)
   - `public/.htaccess`
   - `.env` (create with proper values)

## Verify Setup

1. Check https://healthtech.com/api/ - should show "API is up and running"
2. Check https://healthtech.com/api/getMessages - should return data
3. If 404 errors, check Apache error logs: `sudo tail -f /var/log/apache2/error.log`
