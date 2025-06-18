#!/bin/bash
set -e

echo "🔧 تحديث الحزم وتثبيت المتطلبات..."
sudo apt update && sudo apt install -y php php-mysql php-curl php-xml php-gd php-mbstring php-zip unzip curl wget git nginx mariadb-server

echo "🔧 تثبيت wp-cli..."
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar && sudo mv wp-cli.phar /usr/local/bin/wp

echo "🔐 إعداد قاعدة بيانات MariaDB..."
sudo service mariadb start
sudo mysql -e "CREATE DATABASE wp_ai_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'wp_ai_user'@'localhost' IDENTIFIED BY 'wp_ai_pass';"
sudo mysql -e "GRANT ALL PRIVILEGES ON wp_ai_db.* TO 'wp_ai_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

echo "📦 تحميل WordPress..."
mkdir -p wordpress && cd wordpress
wp core download --locale=ar --force --allow-root

echo "🧩 إنشاء ملف wp-config.php..."
wp config create \
  --dbname=wp_ai_db \
  --dbuser=wp_ai_user \
  --dbpass=wp_ai_pass \
  --dbhost=localhost \
  --locale=ar \
  --skip-check \
  --allow-root

echo "⚙️ تنصيب WordPress..."
wp core install \
  --url="http://localhost" \
  --title="WP AI Agent" \
  --admin_user="admin" \
  --admin_password="123456" \
  --admin_email="admin@example.com" \
  --allow-root

echo "🔗 ربط الإضافة وتفعيلها..."
ln -s /workspace/wp-ai-agent ./wp-content/plugins/wp-ai-agent
wp plugin activate wp-ai-agent --allow-root

echo "✅ تم التثبيت والتفعيل بنجاح"
