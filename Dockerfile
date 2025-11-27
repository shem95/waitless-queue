FROM php:8.2-apache

# تثبيت مكتبات PostgreSQL + تفعيل PDO مع MySQL و PostgreSQL
RUN apt-get update \
    && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql

# نسخ كل ملفات المشروع إلى مجلد الويب داخل الكونتينر
COPY . /var/www/html/

EXPOSE 80
