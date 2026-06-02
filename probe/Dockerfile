FROM php:8.4-cli-bookworm

WORKDIR /app

COPY run.php /app/run.php
COPY public /app/public

CMD ["sh", "-c", "php -S 0.0.0.0:8082 -t public & while true; do php /app/run.php; sleep 300; done"]
