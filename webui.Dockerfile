FROM php:8.3-fpm-bookworm

# Install dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    certbot \
    nginx \
    supervisor \
    curl \
    ca-certificates \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install latest Docker CLI
ENV DOCKERVERSION=27.3.1
RUN curl -fsSL https://download.docker.com/linux/static/stable/x86_64/docker-${DOCKERVERSION}.tgz -o docker.tgz \
    && tar xzvf docker.tgz --strip 1 -C /usr/local/bin docker/docker \
    && rm docker.tgz \
    && chmod +x /usr/local/bin/docker

# Configure Nginx for web UI
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/sites-available/default
COPY ./src/nginx-webui.conf /etc/nginx/sites-available/default
RUN ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Configure PHP-FPM
RUN sed -i 's/listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^user = .*/user = nobody/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = .*/group = nogroup/' /usr/local/etc/php-fpm.d/www.conf

# Copy templates
COPY ./src/nginx-templates /nginx-templates

# Configure Supervisor
COPY ./src/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories and set permissions
RUN mkdir -p /var/log/supervisor /run/php \
    && mkdir -p /var/www/html/.well-known/acme-challenge \
    && chown -R nobody:nogroup /var/www/html/.well-known \
    && chmod -R 755 /var/www/html/.well-known \
    && groupadd -g 987 docker || true \
    && usermod -aG docker nobody

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
