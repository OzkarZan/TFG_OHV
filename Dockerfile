FROM php:8.2-apache

# Habilitar URL rewriting (opcional pero muy útil para APIs REST)
RUN a2enmod rewrite

# Instalar los controladores PDO para MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli
