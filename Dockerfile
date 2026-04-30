FROM php:8.2-apache

# Habilitar URL rewriting (opcional pero muy útil para APIs REST)
RUN a2enmod rewrite

# Permitir que el archivo .htaccess anule las configuraciones de Apache
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Instalar los controladores PDO para MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli
