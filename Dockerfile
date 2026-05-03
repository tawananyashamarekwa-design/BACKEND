FROM php:8.2-apache

# Install extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy files
COPY . /var/www/html/

# Change Apache root to /public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Allow .htaccess overrides (VERY IMPORTANT)
RUN echo '<Directory /var/www/html/public>
    AllowOverride All
    Require all granted
</Directory>' > /etc/apache2/conf-available/override.conf

RUN a2enconf override

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80