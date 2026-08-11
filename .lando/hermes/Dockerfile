FROM nousresearch/hermes-agent:latest

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        php-cli \
        php-curl \
        php-gd \
        php-intl \
        php-mbstring \
        php-mysql \
        php-xml \
        php-zip \
        php-bcmath \
        mariadb-client \
        unzip \
        rsync \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /workspace
