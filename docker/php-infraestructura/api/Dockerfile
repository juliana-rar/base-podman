FROM php:8.4-cli

# Dependencias del sistema + extensiones PHP que necesita Laravel + MySQL
RUN apt-get update && apt-get install -y \
        git \
        unzip \
        curl \
        ca-certificates \
        gnupg \
        libzip-dev \
        libpng-dev \
        libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# Node 22 + npm (necesario para compilar el frontend Inertia/Vite de php-api)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer (copiado desde la imagen oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000
