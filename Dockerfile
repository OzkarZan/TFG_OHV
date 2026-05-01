FROM php:8.2-apache

# Habilitar módulos de Apache necesarios
RUN a2enmod rewrite
RUN a2enmod headers

# Instalar los controladores PDO para MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Configurar el DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Copiar configuración de Apache personalizada
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Desabilitar sitios por defecto
RUN a2dissite 000-default || true

# Habilitar el nuevo sitio
RUN a2ensite 000-default

# Configurar ServerName
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Crear directorio para logs
RUN mkdir -p /var/log/apache2

# Exposer puerto 80
EXPOSE 80

# Comando para iniciar Apache
CMD ["apache2-foreground"]

