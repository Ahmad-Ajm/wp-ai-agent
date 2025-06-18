#!/bin/bash
set -e
sudo apt update && sudo apt install -y php php-mysql php-curl php-xml php-gd php-mbstring php-zip unzip curl wget git nginx mariadb-server
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar && sudo mv wp-cli.phar /usr/local/bin/wp
sudo service mariadb start
sudo mysql -e "CREATE DATABASE wp_ai_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'wp_ai_user'@'localhost' IDENTIFIED BY 'wp_ai_pass';"
sudo mysql -e "GRANT ALL PRIVILEGES ON wp_ai_db.* TO 'wp_ai_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
mkdir -p wordpress && cd wordpress
wp core download --locale=ar --force
wp config create --dbname=wp_ai_db --dbuser=wp_ai_user --dbpass=wp_ai_pass --dbhost=localhost --locale=ar --skip-check
wp core install --url="http://localhost" --title="WP AI Agent" --admin_user="admin" --admin_password="123456" --admin_email="admin@example.com"
