FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql mysqli gd mbstring exif pcntl bcmath

# Enable Apache mod_rewrite
RUN a2enmod rewrite
RUN a2enmod headers

# Set memory limit and upload size
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/memory-limit.ini
RUN echo "upload_max_filesize = 64M" > /usr/local/etc/php/conf.d/uploads.ini
RUN echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/uploads.ini

# Create uploads directory and set permissions
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 755 /var/www/html/uploads

# Copy application files
COPY public/ /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Create custom Apache configuration
RUN echo "<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]