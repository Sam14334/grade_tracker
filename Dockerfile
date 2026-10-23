FROM php:8.2-apache

# Install PostgreSQL dev libraries and enable pdo_pgsql extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Configure Apache to listen on port 10000 (Render default web service port)
RUN sed -i 's/80/10000/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Forward environment variables from container/Render to PHP getenv()
RUN echo "PassEnv DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD DB_SSLMODE RESEND_API_KEY RESEND_FROM_EMAIL" >> /etc/apache2/conf-enabled/environment.conf

# Set working directory and copy application source code
WORKDIR /var/www/html
COPY . /var/www/html/

# Expose Render port
EXPOSE 10000
