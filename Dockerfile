FROM php:8.2-cli
WORKDIR /app
COPY . .

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs

RUN npm install
CMD ["php", "exchange-rates.php"]