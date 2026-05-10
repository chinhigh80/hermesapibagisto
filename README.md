# Hermes-API: AI-Powered Bagisto Control System

## Overview

Hermes-API is an autonomous AI-driven control layer for Bagisto e-commerce platforms. It receives natural language prompts, validates them via a policy engine, and executes corresponding Bagisto operations (products, images, orders, inventory, store config) without altering Bagisto core.

This repository contains two versions:
- **Version 1 (master branch)**: The current Hermes-Bagisto AI Control System
- **Version 2 (enterprise-upgrade branch)**: Enterprise-grade AI commerce operating system with NVIDIA AI integration, queues, SaaS readiness, and advanced features

## Quick Start (Version 2 - Enterprise Upgrade)

### Prerequisites
- PHP 8.1+
- Composer
- MySQL/PostgreSQL
- Redis
- Node.js & NPM (for Laravel Horizon)
- Working Bagisto installation (v1.4+)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/chinhigh80/hermesapibagisto.git
   cd hermesapibagisto
   ```

2. **Checkout the enterprise-upgrade branch**
   ```bash
   git checkout enterprise-upgrade
   ```

3. **Install dependencies**
   ```bash
   composer install
   npm install && npm run dev
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   ```
   Edit `.env` to configure:
   - Database connection (matching your Bagisto setup)
   - Redis connection
   - AI provider keys (NVIDIA_API_KEY, OPENAI_API_KEY, etc.)
   - APP_URL (e.g., http://localhost:8000)

5. **Generate application key**
   ```bash
   php artisan key:generate
   ```

6. **Run migrations**
   ```bash
   php artisan migrate
   ```

7. **Start services**
   ```bash
   # Start Laravel server
   php artisan serve --host=0.0.0.0 --port=8000
   
   # In another terminal, start Redis server (if not already running)
   redis-server
   
   # In another terminal, start Horizon queue workers
   php artisan horizon
   ```

### Usage

#### API Authentication
All Hermes endpoints are protected by Laravel Sanctum. Obtain a token via:
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Operator","email":"operator@example.com","password":"secret","password_confirmation":"secret"}'

# Then login to get token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"operator@example.com","password":"secret"}'
```
Use the returned token in the `Authorization: Bearer <token>` header.

#### Main AI Endpoint
Send natural language commands to:
```
POST /api/hermes/command
Headers: Authorization: Bearer <token>
Body: { "prompt": "Your natural language command here" }
```

**Example prompts:**
- `Create 10 Nike shoes priced at $80 with images and 200 stock each`
- `Set store name to "My Shop"`
- `Upload images to product ID 12 from https://ex.com/i1.jpg, https://ex.com/i2.jpg`
- `Create luxury variants of the previous product` (will ask follow-up questions)
- `Reduce prices of low-selling products by 10%`
- `Generate SEO descriptions for all Nike products`

#### Direct Endpoints (Bypass AI Parser)
For programmatic access, you can also use:
- `POST /api/hermes/products/create`
- `POST /api/hermes/products/bulk`
- `POST /api/hermes/images/upload`
- `POST /api/hermes/store/config/update`
- `POST /api/hermes/orders/manage`
- `POST /api/hermes/inventory/update`

### Version 1 (Original)

To use the original Hermes-Bagisto AI Control System:
```bash
git checkout master
# Then follow the same installation steps above
```

### Architecture

#### Core Components
- **AIParserService**: Uses LLM (NVIDIA/OpenAI/Claude) to convert natural language to structured actions
- **PolicyEngineService**: Validates all actions against safety rules
- **Service Layer**: ProductService, ImageService, OrderService, InventoryService, ConfigService
- **Queue System**: Laravel Horizon with Redis for heavy operations (image processing, bulk creates, SEO generation)
- **Event System**: Laravel events and listeners for decoupled processing
- **Webhook System**: Outbound webhooks to Shopify, Slack, Telegram, Discord
- **RBAC**: Role-based access control with permissions and API scopes
- **Multi-tenant**: SaaS-ready architecture with tenant isolation
- **Observability**: Laravel Telescope, Horizon dashboard, OpenTelemetry, structured logging

#### AI Providers
Supports multiple AI providers with automatic fallback:
- NVIDIA AI/NIM (primary)
- OpenAI
- Anthropic Claude
- Local LLM (for air-gapped environments)

Configuration in `config/ai.php` and `.env`:
```
AI_PROVIDER=nvidia
AI_FALLBACK_PROVIDER=openai
NVIDIA_API_KEY=your_key_here
AI_MODEL=nemotron-3-super-120b-a12b
AI_TIMEOUT=120
AI_MAX_TOKENS=4096
```

### Monitoring & Operations

#### Health Checks
- `GET /api/hermes/health` - Basic health status
- `GET /api/hermes/metrics` - Prometheus-formatted metrics
- `GET /api/hermes/status` - Detailed system status

#### Dashboards
- **Horizon Dashboard**: `/horizon` (queue monitoring)
- **Telescope**: `/telescope` (requests, exceptions, logs)
- **Admin Dashboard**: `/admin` (Filament-based enterprise dashboard)

#### Logs
- Storage logs: `storage/logs/laravel.log`
- Channel-specific logs: Configure in `config/logging.php`

### Security Features
- Input sanitization and validation
- Prompt injection protection
- Encrypted secrets storage
- Secure file uploads with MIME validation
- Rate limiting (10 req/sec by default)
- CSRF protection
- Signed webhook URLs
- OWASP compliance

### Testing
Run the test suite:
```bash
php artisan test
```
Target: 80%+ code coverage

### Deployment
See `docker-compose.yml` for production deployment with:
- Nginx
- PHP-FPM
- Redis
- MySQL/PostgreSQL
- Horizon workers
- Health checks and restart policies

### Support
For issues and feature requests, please use the GitHub issue tracker.

### License
MIT License