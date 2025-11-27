FROM php:8.2-apache

# تفعيل PDO مع MySQL و PostgreSQL (لو احتجناه لاحقاً)
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql

# نسخ كل ملفات المشروع إلى مجلد الويب داخل الكونتينر
COPY . /var/www/html/

EXPOSE 80
