FROM php:8.2-fpm

# Installer les dépendances système nécessaires
RUN apt-get update && apt-get install -y \
    unzip \
    curl \
    git \
    gnupg \
    ca-certificates \
    libzip-dev \
    zip \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql mysqli zip intl

# Composer
RUN curl -sS https://getcomposer.org/installer | php && \
mv composer.phar /usr/local/bin/composer


# Installer Node.js LTS + npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Définir le répertoire de travail
WORKDIR /var/www/html
RUN git config --global --add safe.directory /var/www/html

# Copier les fichiers PHP pour installer les dépendances PHP
COPY composer.json composer.lock ./

RUN composer install --no-scripts --no-autoloader \
    && composer dump-autoload --optimize

# Définir le répertoire de travail du thème
# WORKDIR /var/www/html/web/app/themes/lumberjack-child

# # Copier les fichiers frontend pour l'installation JS
# COPY web/app/themes/lumberjack-child/package.json ./
# COPY web/app/themes/lumberjack-child/package-lock.json ./
# COPY web/app/themes/lumberjack-child/vite.config.mjs ./
# COPY web/app/themes/lumberjack-child/assets/ ./assets

# # Installer les dépendances JS + build
# RUN npm install && npm run build

# Copier le reste du projet
COPY . .

