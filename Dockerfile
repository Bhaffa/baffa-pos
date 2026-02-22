FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

RUN a2enmod rewrite

EXPOSE 8080

CMD ["/bin/bash", "-c", "sed -i 's/80/$PORT/g' /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"]
