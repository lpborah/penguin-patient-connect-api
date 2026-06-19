#!/bin/bash
# Run this script on your AWS server to fix Composer and Slim version issues

cd /var/www/html/api

echo "Installing Composer 2..."
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer --2
php -r "unlink('composer-setup.php');"

echo "Removing vendor folder and composer.lock..."
rm -rf vendor
rm -f composer.lock

echo "Installing PHP 7.2 compatible dependencies..."
/usr/local/bin/composer install --no-dev --optimize-autoloader

echo "Setting correct permissions..."
# Change ownership to current user with www-data group
sudo chown -R $USER:www-data /var/www/html/api
# Set permissions: owner read/write/execute, group read/write/execute, others read/execute
sudo chmod -R 775 /var/www/html/api
# Ensure new files inherit group ownership
sudo chmod g+s /var/www/html/api

# Add your user to www-data group for easier file management
echo "Adding current user to www-data group..."
sudo usermod -a -G www-data $USER

echo "Done! Log out and log back in for group changes to take effect."
echo "Then check https://healthtech.com/api/ to verify it works."
