# Imagem base: PHP 8.3 com PHP-FPM (gerencia os processos PHP por trás de um servidor web)
FROM php:8.4-fpm

# Argumentos pra criar um usuário com o mesmo ID do seu usuário do host.
# Isso evita problemas de permissão nos arquivos compartilhados entre host e container.
ARG UID=1000
ARG GID=1000

# Dependências de sistema necessárias pra compilar as extensões do PHP
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Extensões do PHP que o Laravel usa:
# pdo_mysql -> conexão com MySQL | mbstring, exif, bcmath -> usadas pelo framework
# gd -> imagens | zip -> pacotes | pcntl -> necessária pro worker de filas
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Extensão do Redis (vem de um repositório externo chamado PECL)
RUN pecl install redis && docker-php-ext-enable redis

# Instala o Composer copiando o binário da imagem oficial do Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Cria um grupo e um usuário não-root chamado "appuser" com os IDs passados
RUN groupadd -g ${GID} appuser \
    && useradd -u ${UID} -g appuser -m -s /bin/bash appuser

# Diretório onde a aplicação vai morar dentro do container
WORKDIR /var/www/html

# A partir daqui, tudo roda como o usuário não-root (requisito do desafio)
USER appuser

# Porta padrão do PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]