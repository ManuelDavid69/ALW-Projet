# Partir de l'image officielle de PHP comme base
# https://hub.docker.com/_/php
FROM php:8.2-apache

# Installer et activer XDebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Activer le module de réécriture d'URL
RUN a2enmod rewrite

# Ajouter configuration PHP custom
COPY conf/webservice/custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Désactiver le vhost par défaut
RUN a2dissite 000-default.conf

# Définir et activer notre vhost custom
COPY conf/webservice/site.conf /etc/apache2/sites-available/site.conf
RUN a2ensite site

# S'assurer de la propriété des fichiers dans l'instance
# RUN chown -R root:www-data /var/www
