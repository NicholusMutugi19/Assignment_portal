# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Aiven CA certificate for SSL connections
RUN echo "-----BEGIN CERTIFICATE-----\nMIIERDCCAqygAwIBAgIUckoTP8lhwudofNKQEM+FHk5kyBgwDQYJKoZIhvcNAQEM\nBQAwOjE4MDYGA1UEAwwvYjBmYTNiNWQtYTc4OS00NmI4LTkzYmMtN2U5NDBkNzNk\nMDA1IFByb2plY3QgQ0EwHhcNMjYwMzI2MTgxNjA0WhcNMzYwMzIzMTgxNjA0WjA6\nMTgwNgYDVQQDDC9iMGZhM2I1ZC1hNzg5LTQ2YjgtOTNiYy03ZTk0MGQ3M2QwMDUg\nUHJvamVjdCBDQTCCAaIwDQYJKoZIhvcNAQEBBQADggGPADCCAYoCggGBAP0snWrT\nXr4Vr80Vzyc3M7NC1/YccWM4wcstKuLn2VslfJI1Vk68Tf/D4N60JdFQsaVxZPdx\n6+ZwHExpFwkPK5jZZ76ge6ZnuNdRhn4cFiMtLITV5Lhk2/DzHFN1r2cHSqyFnQxm\nqEew2DyYv6itzdHmQ6PR1n0ZkStOxDCT1WlalqgUXmvvEBUWqylNQhpW71FcI5dk\n89badGk/SpY2EEEQsbai5ozITPLFbmAvkeTr6pXjGPqreRxqkARV5KmRqVEQPJFH\nZk1+u8egLQqh11jEBMNxiIzLAc67xwxcZ+MySsdiukGVchHWG+gMxA8f3kt5KAut\noHS58G316SjvRFH11j3wpj6DmMJtpWKQE1bDMgRQFve2+fdHNZULu8RNx2Ipz9nm\nkSprQdmwq6JYzLtbwRv5YMQrTOqDFOVOO8m9pLQSsDcyHi8nBTMq7sC1CK4mPCJb\nqRR2G282d/jCC+PhEXY1y2pD7PMZTgBb17VBRFw1Aog0x3I+KSkcfwr3twIDAQAB\no0IwQDAdBgNVHQ4EFgQUUusMRFDMt8+twGfe7IGuFFPgnQ8wEgYDVR0TAQH/BAgw\nBgEB/wIBADALBgNVHQ8EBAMCAQYwDQYJKoZIhvcNAQEMBQADggGBAAE/8md4Wudu\nTboMPZgrA29hqZzO3XX2fak49MtPn30GXmjQWPl9fZ5ZG40vPb8LC0RsuzmBM8tG\nl0KvXxRxdnUKr4GIHoD31j3Ykn9iZoiDCZsOpgWTGTOmbm2AAvxbA/8q4QbR4c0A\npk1tpq3RLfw8sFv/DSYGKaCekjPgZByBIoawP3DA4RtlXPGuc+C48QCR6pomlwgu\nrngomf0FMo0p3LKwiXNFi3hgABkYvazJkVVeetL0dt+4UooJv4f/WK9HM1rVv9wo\nqAL//53JBqtY/cVtmmy/DPAdhams9/lOnooOnnwHcb4zmdXTOhHSKyQXRJaUSaKx\nj5z+jLycQ8HSDQo1+2AG6ODIw0MXjCdD5lAA3CPhFwodE/RwVkXvsmsh9urCzb8E\nuWfE7Ei+QuEMGWirxLLmixABh1f/gCVN922zfwrkH3suuEuaqv5GAyAI/mAGKlNL\nTO0I0SNdNq/w2lTGlyR8RnbiQRNYVDHsL4kdDU318Hju1oVvM9C1lg==\n-----END CERTIFICATE-----" > /usr/local/share/ca-certificates/aiven-ca.pem && \
    update-ca-certificates

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . /var/www/html/

# Create uploads directory structure and set permissions
RUN mkdir -p /var/www/html/public/uploads/{assignments,submissions} \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/public/uploads

# Configure Apache to serve from public directory
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]