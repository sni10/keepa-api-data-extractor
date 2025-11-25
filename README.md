# Keepa API Data Extractor - Symfony сервис для поиска товаров через Keepa API и выгрузки в RabbitMQ
> **Консольное приложение на Symfony 7 / PHP 8.3+, которое выполняет поиск товаров через Keepa API по бренду и временному диапазону, с последующей публикацией результатов в RabbitMQ**

[![Release](https://img.shields.io/github/v/release/sni10/keepa-api-data-extractor?style=for-the-badge&logo=github&logoColor=white)](https://github.com/sni10/keepa-api-data-extractor/releases)
[![Release Workflow](https://img.shields.io/github/actions/workflow/status/sni10/keepa-api-data-extractor/release.yml?style=for-the-badge&logo=githubactions&logoColor=white&label=Release)](https://github.com/sni10/keepa-api-data-extractor/actions/workflows/release.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/sni10/keepa-api-data-extractor/tests.yml?style=for-the-badge&logo=githubactions&logoColor=white&label=Tests)](https://github.com/sni10/keepa-api-data-extractor/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/badge/Coverage-89%25-yellow?style=for-the-badge&logo=codecov&logoColor=white)](https://github.com/sni10/keepa-api-data-extractor/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7.2-000000?style=for-the-badge&logo=Symfony&logoColor=white)](https://symfony.com/)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://www.docker.com/)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

## Основные возможности

- Поиск товаров в Keepa API по бренду и временному диапазону
- Контроль лимитов токенов Keepa с автоматическими ретраями
- Постраничная пагинация результатов поиска
- Получение детализированной информации по найденным ASIN'ам
- Валидация входящих и исходящих DTO через Symfony Validator
- Публикация результатов в выходную очередь RabbitMQ
- Логирование и мониторинг через Monolog
- Автоперезапуск обработчика очередей через supervisord

## Архитектура в двух словах

- **DTO** (KeepaInputDto, KeepaOutputDto) — структура данных с валидацией
- **Сервисы** (KeepaService, FinderProductsService) — оркестрация и бизнес-логика
- **MessageHandler** (KeepaInputMessageHandler) — обработка входящих сообщений из RabbitMQ
- **Serializers** (InputMessageSerializer, OutputMessageSerializer) — сериализация/десериализация
- **Validators** (MessageValidator) — централизованная валидация DTO

![architecture-Keepa_API_Data_Extractor___Architecture.png](architecture-Keepa_API_Data_Extractor___Architecture.png)

### Применяемые паттерны проектирования

В проекте активно используются следующие паттерны:

- **DTO (Data Transfer Object)**: Для передачи типизированных данных между слоями (`KeepaInputDto`, `KeepaOutputDto`).
- **Facade (Фасад)**: `KeepaService` скрывает сложность взаимодействия с внешней библиотекой Keepa API и управления токенами.
- **Service Layer (Сервисный слой)**: Разделение бизнес-логики (`FinderProductsService`) и логики работы с API (`KeepaService`).
- **Orchestrator (Оркестратор)**: `FinderProductsService` управляет потоком: валидация -> поиск -> отправка.
- **Command Handler**: Обработка сообщений через `KeepaInputMessageHandler` (CQRS-style в Symfony Messenger).
- **Iterator / Generator**: Использование `yield` для экономии памяти при переборе больших списков товаров.
- **Dependency Injection**: Внедрение зависимостей для обеспечения тестируемости и гибкости.

## Требования

- PHP 8.3+
- Symfony 7.2
- Docker и Docker Compose
- Расширения PHP: amqp, mbstring, pdo_pgsql, zip, sockets
- Keepa API key (https://keepa.com/#!api)
- RabbitMQ для входящих и исходящих сообщений

---

## Environments

Проект поддерживает два окружения, управляемых через Docker Compose:

### Production environment
- Использует `docker-compose.yml` как базовую конфигурацию
- Настроено для боевого развёртывания с оптимизированными параметрами
- Переменные окружения: `APP_ENV=prod`, `.env.prod`
- Supervisord автоматически запускает Keepa finder worker

### Development / Testing environment
- Использует `docker-compose.yml` + `docker/config-envs/test/docker-compose.override.yml`
- Включает отладку, покрытие кода и подробные сообщения об ошибках
- Переменные окружения: `APP_ENV=test`, `.env.test`
- Xdebug включён для покрытия и отладки

---

## Running the Application

### Production

1. Клонируйте репозиторий:
```bash
git clone https://github.com/sni10/keepa-api-data-extractor.git
cd keepa-api-data-extractor
```

2. Создайте файл `.env.prod` на основе `.env.example` с боевыми настройками:
```bash
cp .env.example .env
# Отредактируйте .env и установите:
# - KEEPA_API_KEY=your-production-key
# - RABBITMQ_INPUT_TRANSPORT_DSN=amqp://...
# - RABBITMQ_OUTPUT_TRANSPORT_DSN=amqp://...
```

3. Соберите и запустите контейнеры:
```bash
docker compose build
docker compose up -d
```

4. При старте контейнера `supervisord` автоматически запускает консольную команду:
```bash
php /var/www/keepa-api-data-extractor/bin/console app:keepa-finder
```

### Development / Testing

1. Создайте `.env.test` на основе `.env.example` с параметрами для test/dev:
```bash
cp .env.example .env.test
# Отредактируйте .env.test для тестового окружения
```

2. Соберите и поднимите окружение с тестовым override:
```bash
docker compose --env-file .env.test -f docker-compose.yml -f docker/config-envs/test/docker-compose.override.yml build

docker compose --env-file .env.test -f docker-compose.yml -f docker/config-envs/test/docker-compose.override.yml up -d
```

---

## Makefile

Для удобства доступны make-команды. По умолчанию `APP_ENV=test`.

```bash
make init                 # Полная инициализация (build + up)
make up / make down       # Запуск / остановка контейнеров
make test                 # Запуск тестов
make test-coverage        # Тесты с покрытием
make shell                # Bash в PHP-контейнере
make help                 # Список всех команд
```

Основные группы: `build`, `up`, `down`, `restart`, `logs`, `ps`, `shell` (Docker), `test-*` (тесты), `console`, `composer-*`.

Production: `make prod-build`, `make prod-up`, `make prod-down`.

| Команда | Описание |
|---------|----------|
| **Docker (Test/Dev)** | |
| `make build` | Сборка контейнеров |
| `make up` | Запуск контейнеров |
| `make down` | Остановка и очистка |
| `make restart` | Перезапуск |
| `make logs` | Логи docker-compose |
| `make ps` | Список контейнеров |
| `make shell` | Вход в контейнер |
| **Docker (Production)** | |
| `make prod-build` | Сборка production контейнеров |
| `make prod-up` | Запуск production |
| `make prod-down` | Остановка production |
| **Логи** | |
| `make logs-app` | Логи приложения (stdout) |
| `make logs-app-err` | Логи приложения (stderr) |
| `make logs-supervisor` | Логи supervisord |
| `make logs-php` | PHP ошибки |
| **Symfony** | |
| `make console CMD="..."` | Произвольная команда |
| `make cache-clear` | Очистка кэша |
| **Composer** | |
| `make composer-install` | Установка зависимостей |
| `make composer-update` | Обновление зависимостей |
| **Тесты** | |
| `make test` | Запуск тестов |
| `make test-coverage` | Тесты с покрытием |
| `make test-html` | HTML-отчёт покрытия |
| `make test-unit` | Только Unit тесты |
| `make test-filter FILTER="..."` | Фильтр тестов |

---

## Application composition (Services)

```
NAME               IMAGE                              SERVICE          STATUS    PORTS
php-keepa-api      keepa-api-data-extractor-php       php-keepa-api    Up        9000/tcp, 9003/tcp
```

---

## Формат входного сообщения RabbitMQ

Пример входящего сообщения из очереди:

```json
{
  "id": 123,
  "domain_id": 1,
  "brand": "adidas",
  "time_from": "2025-01-01",
  "time_to": "2025-01-31",
  "status": "PENDING",
  "step": 1,
  "version": 1
}
```

### Параметры:
- `id` — ID запроса в базе search_requests
- `domain_id` — Domain ID из Keepa API (1 = США, 2 = Великобритания и т.д.)
- `brand` — Бренд для поиска
- `time_from` / `time_to` — Временной диапазон (дата или Unix timestamp)
- `status` — Статус запроса (PENDING, IN_PROGRESS, COMPLETED, FAILED)
- `step` — Текущий шаг обработки
- `version` — Версия сообщения

### Формат выходного сообщения

Для каждого найденного товара отправляется сообщение:

```json
{
  "search_request_id": 123,
  "domain_id": 1,
  "brand": "adidas",
  "asin": "B08N5WRWNW",
  "title": "Adidas Men's Shoes",
  "upc_list": ["123456789012"],
  "ean_list": ["1234567890123"],
  "json_data": {},
  "time_from": "2025-01-01",
  "time_to": "2025-01-31",
  "version": 1
}
```

---

## Консольные команды

### Основная команда поиска (для работы с RabbitMQ)
```bash
php bin/console app:keepa-finder
```
Запускает обработчик очередей RabbitMQ, который слушает входящие сообщения и обрабатывает их через KeepaService.

### Тестовая команда прямого сканирования
```bash
php bin/console app:keepa-scan --domain=1 --brand=adidas --time_from=1738108800 --time_to=1738195200
```
Позволяет протестировать поиск напрямую, без очередей.

---

## Конфигурация окружения

Основные переменные в `.env.*`:

```dotenv
# Symfony
APP_ENV=prod
APP_SECRET=your-secret-key

# Keepa API
KEEPA_API_KEY=your-keepa-api-key
# секунд ожидания восстановления токенов
KEEPA_TOKEN_WAIT_TIMEOUT=60
# число попыток ожидания
KEEPA_TOKEN_MAX_RETRIES=5
# минимум токенов для запроса
KEEPA_TOKEN_MIN_LIMIT=100
# ретраи HTTP-запросов
KEEPA_REQUEST_MAX_RETRIES=5

# RabbitMQ
RABBITMQ_INPUT_TRANSPORT_DSN=amqp://user:pass@rabbitmq:5672/%2f/input_queue
RABBITMQ_OUTPUT_TRANSPORT_DSN=amqp://user:pass@rabbitmq:5672/%2f/output_queue

# Опционально
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

---

## Tests

Проект включает полноценное покрытие тестами на базе PHPUnit:
- **Unit-тесты** (`tests/Unit/`) — быстрые изолированные тесты для DTO, сервисов и бизнес-логики

### Running tests locally

Тесты запускаются внутри контейнера PHP, используя окружение разработки/тестирования.

1. Поднимите тестовое окружение:
```bash
docker compose --env-file .env.test -f docker-compose.yml -f docker/config-envs/test/docker-compose.override.yml up -d
```

2. Запустите все тесты:
```bash
docker compose --env-file .env.test exec php-keepa-api vendor/bin/phpunit --colors=always --testdox
```

Или используйте Makefile:
```bash
make test              # Запуск всех тестов
make test-coverage     # Тесты с покрытием
make test-html         # HTML-отчёт покрытия
make test-unit         # Только Unit-тесты
```

### Running individual tests

Запустить один файл тестов:
```bash
docker compose --env-file .env.test exec php-keepa-api vendor/bin/phpunit tests/Unit/Dto/KeepaInputDtoTest.php
```

Запустить конкретный тестовый метод:
```bash
make test-filter FILTER=testFromArrayNormalizesValues
```

### CI/CD testing

Тесты автоматически запускаются в GitHub Actions при создании pull request в ветку `dev`. В пайплайне выполняется:
1. Сборка Docker-контейнеров с тестовой конфигурацией
2. Запуск всех тестов с покрытием
3. Загрузка отчётов покрытия как артефактов

Полную конфигурацию CI/CD смотрите в `.github/workflows/tests.yml`.

---

## База данных

Проект использует PostgreSQL для хранения запросов и результатов поиска.

### Схема таблиц

**search_requests** — входящие запросы на поиск:
```sql
CREATE TABLE search_requests (
    id         SERIAL PRIMARY KEY,
    domain_id  INTEGER NOT NULL,
    brand      VARCHAR(255),
    status     VARCHAR(255),
    time_from  DATE,
    time_to    DATE,
    step       INTEGER,
    is_active  BOOLEAN DEFAULT FALSE,
    version    BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**find_products** — результаты поиска товаров:
```sql
CREATE TABLE find_products (
    search_request_id INTEGER,
    domain_id         INTEGER,
    brand             VARCHAR(255),
    asin              VARCHAR(20),
    title             TEXT,
    upc_list          TEXT[],
    ean_list          TEXT[],
    json_data         JSONB,
    time_from         DATE,
    time_to           DATE,
    version           BIGINT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP,
    is_deleted        TIMESTAMP,
    CONSTRAINT find_products_unique UNIQUE (search_request_id, domain_id, brand, asin)
);
```

---

## Отладка

Для отладки в IDE использовать удалённый интерпретатор из Docker-контейнера. Конфигурация Xdebug: `docker/configs-data/php.ini` (для test-окружения).

Логи доступны через Makefile: `make logs-app`, `make logs-php`, `make logs-supervisor`.

---

## Git Workflow and Releases

### Branching Strategy
- `main` — production-ready code
- `stage` — Staging (pre-production)
- `dev` — интеграционная ветка разработки

### Release Process
1. Фичи разрабатываются в feature-ветках и вливаются в `dev` через pull request
2. Тесты автоматически запускаются на каждый PR в `dev` (см. `.github/workflows/tests.yml`)
3. Когда `dev` стабилен, создайте PR из `dev` → `stage`
4. После валидации `stage` создайте PR из `stage` → `main`
5. После мержа PR `stage` → `main` автоматически создаётся новый релиз с инкрементом версии (см. `.github/workflows/release.yml`)

### Automated Release Creation
- Триггерится при мерже PR из `stage` в `main`
- Автоматически увеличивается patch-версия (например, v1.0.0 → v1.0.1)
- Создаётся релиз GitHub с changelog
- Теги в формате семантического версионирования: `vMAJOR.MINOR.PATCH`

---

## License

MIT License — see [LICENSE](LICENSE) for details