# Proxy Manager
## Modern SSL & Nginx Management

[English](#english) | [Русский](#russian)

---

<a name="english"></a>
## 🇬🇧 English

### Overview

A powerful web-based Nginx reverse proxy manager with automatic SSL certificate management via Let's Encrypt. Built with PHP, SQLite, and Docker for easy deployment and management.

**GitHub:** https://github.com/JazzyTM/proxy-meneger  
**Telegram:** @jazzytm

### Features

- 🌐 **Domain Management** - Add and manage multiple domains with custom configurations
- 🔒 **Automatic SSL** - One-click Let's Encrypt SSL certificate generation and auto-renewal
- ⚙️ **Advanced Configuration** - Fine-tune proxy settings per domain:
  - TLS version selection (1.0, 1.1, 1.2, 1.3)
  - HTTP/2 support
  - WebSocket support
  - Gzip compression
  - Asset caching
  - Security exploit blocking
  - Custom headers and Nginx directives
- 🔄 **Auto Config Generation** - Automatic Nginx configuration generation and reload
- 👥 **User Management** - Multi-user support with role-based access control
- 📊 **Activity Logging** - Track all system activities
- 🎨 **Modern UI** - Clean, responsive interface built with Vue.js and Tailwind CSS

### Quick Start

#### Automatic Installation (Recommended)

```bash
git clone https://github.com/JazzyTM/proxy-meneger.git
cd proxy-meneger
chmod +x install.sh
sudo ./install.sh
```

The installer will:
- ✓ Detect your OS (Ubuntu, Debian, CentOS, Fedora, RHEL)
- ✓ Check and install Docker if needed
- ✓ Verify port availability (80, 443, 8080)
- ✓ Create necessary directories with correct permissions
- ✓ Generate environment configuration
- ✓ Build and start all services
- ✓ Display access information

#### Manual Installation

1. **Clone the repository:**
```bash
git clone https://github.com/JazzyTM/proxy-meneger.git
cd proxy-meneger
```

2. **Create directories:**
```bash
mkdir -p db certs nginx-configs src/www/.well-known/acme-challenge
chmod -R 777 db certs nginx-configs src/www/.well-known
```

3. **Create .env file:**
```bash
DOCKER_GID=$(getent group docker | cut -d: -f3)
cat > .env << EOF
DOCKER_GID=${DOCKER_GID:-999}
APP_ENV=production
APP_DEBUG=false
DB_PATH=/db/db.db
CERTS_PATH=/certs
NGINX_CONFIGS_PATH=/nginx-configs
EOF
```

4. **Start services:**
```bash
docker compose up -d
```

5. **Access the web interface:**
- URL: `http://your-server-ip:8080`
- Create admin user on first run

### Management Scripts

#### Update Script

Update to the latest version with automatic backup:

```bash
sudo ./update.sh
```

Features:
- ✓ Automatic backup of database and configuration
- ✓ Pull latest changes from GitHub
- ✓ Rebuild Docker images with latest code
- ✓ Zero-downtime update process
- ✓ Health check after update
- ✓ Cleanup old Docker images

#### Uninstall Script

Complete removal with data preservation option:

```bash
sudo ./uninstall.sh
```

Features:
- ✓ Stop and remove all containers
- ✓ Remove Docker images and networks
- ✓ Optional data removal (databases, certificates, configs)
- ✓ Optional .env file removal
- ✓ Safe confirmation prompts

### Common Commands

```bash
# View logs
docker compose logs -f

# View specific service logs
docker compose logs -f webui
docker compose logs -f reverse-proxy

# Restart services
docker compose restart

# Stop services
docker compose stop

# Start services
docker compose start

# Check service status
docker compose ps

# Rebuild and restart
docker compose up -d --build
```

### Architecture

- **Reverse Proxy Container** - Nginx server handling all incoming traffic
- **WebUI Container** - PHP-based management interface with:
  - Nginx for web server
  - PHP-FPM for application logic
  - Certbot for SSL certificates
  - SQLite for data storage
  - Docker CLI for container management

### Configuration Options

#### Domain Settings
- **Destination IP/Port** - Backend server address
- **TLS Version** - Choose from TLS 1.0 to 1.3
- **HTTP Version** - HTTP/1.1 or HTTP/2
- **Proxy Timeout** - Connection timeout settings
- **Buffer Size** - Proxy buffer configuration
- **Max Upload Size** - Client body size limit

#### Security Features
- **Block Common Exploits** - Automatic blocking of common attack patterns
- **Custom Headers** - Add security headers (HSTS, CSP, etc.)
- **WWW Subdomain** - Automatic www subdomain support

#### Performance
- **Gzip Compression** - Reduce bandwidth usage
- **Asset Caching** - Cache static files (images, CSS, JS)
- **WebSocket Support** - Enable for real-time applications

### SSL Certificate Management

- **Generate** - One-click SSL certificate generation
- **Renew** - Manual certificate renewal
- **View** - View certificate details
- **Revoke** - Revoke compromised certificates
- **Delete** - Remove certificates (reverts to HTTP)

### Requirements

- Docker 20.10+
- Docker Compose 2.0+
- Ports 80, 443, 8080 available

### Security Notes

- Change default admin credentials immediately
- Use strong passwords
- Keep Docker images updated
- Review activity logs regularly
- Limit access to port 8080

### License

MIT License - see LICENSE file for details

---

<a name="russian"></a>
## 🇷🇺 Русский

### Обзор

Мощный веб-менеджер обратного прокси Nginx с автоматическим управлением SSL-сертификатами через Let's Encrypt. Построен на PHP, SQLite и Docker для простого развертывания и управления.

**GitHub:** https://github.com/JazzyTM/proxy-meneger  
**Telegram:** @jazzytm

### Возможности

- 🌐 **Управление доменами** - Добавление и управление несколькими доменами с индивидуальными настройками
- 🔒 **Автоматический SSL** - Генерация SSL-сертификатов Let's Encrypt в один клик с автопродлением
- ⚙️ **Расширенная конфигурация** - Тонкая настройка прокси для каждого домена:
  - Выбор версии TLS (1.0, 1.1, 1.2, 1.3)
  - Поддержка HTTP/2
  - Поддержка WebSocket
  - Сжатие Gzip
  - Кэширование ресурсов
  - Блокировка эксплойтов
  - Пользовательские заголовки и директивы Nginx
- 🔄 **Автогенерация конфигов** - Автоматическая генерация и перезагрузка конфигурации Nginx
- 👥 **Управление пользователями** - Многопользовательская система с ролевым доступом
- � **Журнcал активности** - Отслеживание всех действий в системе
- 🎨 **Современный интерфейс** - Чистый, адаптивный интерфейс на Vue.js и Tailwind CSS

### Быстрый старт

#### Автоматическая установка (Рекомендуется)

```bash
git clone https://github.com/JazzyTM/proxy-meneger.git
cd proxy-meneger
chmod +x install.sh
sudo ./install.sh
```

Установщик выполнит:
- ✓ Определение вашей ОС (Ubuntu, Debian, CentOS, Fedora, RHEL)
- ✓ Проверку и установку Docker при необходимости
- ✓ Проверку доступности портов (80, 443, 8080)
- ✓ Создание необходимых директорий с правильными правами
- ✓ Генерацию конфигурации окружения
- ✓ Сборку и запуск всех сервисов
- ✓ Отображение информации для доступа

#### Ручная установка

1. **Клонируйте репозиторий:**
```bash
git clone https://github.com/JazzyTM/proxy-meneger.git
cd proxy-meneger
```

2. **Создайте директории:**
```bash
mkdir -p db certs nginx-configs src/www/.well-known/acme-challenge
chmod -R 777 db certs nginx-configs src/www/.well-known
```

3. **Создайте файл .env:**
```bash
DOCKER_GID=$(getent group docker | cut -d: -f3)
cat > .env << EOF
DOCKER_GID=${DOCKER_GID:-999}
APP_ENV=production
APP_DEBUG=false
DB_PATH=/db/db.db
CERTS_PATH=/certs
NGINX_CONFIGS_PATH=/nginx-configs
EOF
```

4. **Запустите сервисы:**
```bash
docker compose up -d
```

5. **Откройте веб-интерфейс:**
- URL: `http://ip-вашего-сервера:8080`
- Создайте администратора при первом запуске

### Скрипты управления

#### Скрипт обновления

Обновление до последней версии с автоматическим резервным копированием:

```bash
sudo ./update.sh
```

Возможности:
- ✓ Автоматическое резервное копирование базы данных и конфигурации
- ✓ Получение последних изменений с GitHub
- ✓ Пересборка Docker-образов с новым кодом
- ✓ Обновление без простоя
- ✓ Проверка работоспособности после обновления
- ✓ Очистка старых Docker-образов

#### Скрипт удаления

Полное удаление с возможностью сохранения данных:

```bash
sudo ./uninstall.sh
```

Возможности:
- ✓ Остановка и удаление всех контейнеров
- ✓ Удаление Docker-образов и сетей
- ✓ Опциональное удаление данных (базы данных, сертификаты, конфиги)
- ✓ Опциональное удаление файла .env
- ✓ Безопасные запросы подтверждения

### Основные команды

```bash
# Просмотр логов
docker compose logs -f

# Просмотр логов конкретного сервиса
docker compose logs -f webui
docker compose logs -f reverse-proxy

# Перезапуск сервисов
docker compose restart

# Остановка сервисов
docker compose stop

# Запуск сервисов
docker compose start

# Проверка статуса сервисов
docker compose ps

# Пересборка и перезапуск
docker compose up -d --build
```

### Архитектура

- **Контейнер Reverse Proxy** - Сервер Nginx, обрабатывающий весь входящий трафик
- **Контейнер WebUI** - Интерфейс управления на PHP с:
  - Nginx в качестве веб-сервера
  - PHP-FPM для логики приложения
  - Certbot для SSL-сертификатов
  - SQLite для хранения данных
  - Docker CLI для управления контейнерами

### Параметры конфигурации

#### Настройки домена
- **IP/Порт назначения** - Адрес backend-сервера
- **Версия TLS** - Выбор от TLS 1.0 до 1.3
- **Версия HTTP** - HTTP/1.1 или HTTP/2
- **Таймаут прокси** - Настройки таймаута соединения
- **Размер буфера** - Конфигурация буфера прокси
- **Макс. размер загрузки** - Лимит размера тела запроса

#### Функции безопасности
- **Блокировка эксплойтов** - Автоматическая блокировка распространенных атак
- **Пользовательские заголовки** - Добавление заголовков безопасности (HSTS, CSP и др.)
- **WWW поддомен** - Автоматическая поддержка www поддомена

#### Производительность
- **Сжатие Gzip** - Уменьшение использования трафика
- **Кэширование ресурсов** - Кэширование статических файлов (изображения, CSS, JS)
- **Поддержка WebSocket** - Включение для real-time приложений

### Управление SSL-сертификатами

- **Генерация** - Создание SSL-сертификата в один клик
- **Обновление** - Ручное обновление сертификата
- **Просмотр** - Просмотр деталей сертификата
- **Отзыв** - Отзыв скомпрометированных сертификатов
- **Удаление** - Удаление сертификатов (возврат к HTTP)

### Требования

- Docker 20.10+
- Docker Compose 2.0+
- Доступные порты 80, 443, 8080

### Примечания по безопасности

- Немедленно измените учетные данные администратора по умолчанию
- Используйте надежные пароли
- Обновляйте Docker-образы
- Регулярно проверяйте журналы активности
- Ограничьте доступ к порту 8080

### Лицензия

Лицензия MIT - подробности в файле LICENSE
