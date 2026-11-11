# Контур разработки. На боевом хостинге система разворачивается
# копированием файлов — образ нужен только чтобы гонять её здесь.
#
# ВАЖНО: это среда разработки. Боевой сайт по ст. 18 ч. 5 152-ФЗ
# размещается только на хостинге в России — он собирает заявки
# с персональными данными.

FROM php:8.2-apache

# gd — пережатие фотографий при загрузке, intl — сортировка русских
# названий, opcache — чтобы на каждый запрос не разбирать код заново.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libzip-dev libicu-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd zip intl pdo_mysql opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка пакетов оставляет включёнными сразу два MPM, и Apache
# отказывается стартовать. С mod_php работает только prefork.
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite headers

COPY docker/php.ini /usr/local/etc/php/conf.d/sklad.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/public/upload /var/www/html/config

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
