FROM php:8.2-cli
WORKDIR /app
COPY . .
RUN npm install
CMD ["php", "exchange-rates.php"]
