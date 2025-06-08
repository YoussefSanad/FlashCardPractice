# Laravel Flashcard Practice Application

A comprehensive flashcard practice application built with Laravel using CQRS (Command Query Responsibility Segregation) architecture pattern. The application provides an interactive command-line interface for creating, practicing, and tracking progress on flashcards.

## Features

- 🎯 **Interactive CLI Interface** - User-friendly command-line experience
- 📚 **Flashcard Management** - Create and manage flashcards with questions and answers
- 🏃 **Practice Mode** - Interactive practice sessions with immediate feedback
- 📊 **Progress Tracking** - Track individual attempts and current status per question
- 📈 **Statistics** - Comprehensive practice statistics and completion tracking
- 🔄 **Reset Functionality** - Clear progress while preserving flashcards
- 🏗️ **CQRS Architecture** - Clean separation of commands and queries
- ✅ **Comprehensive Testing** - Full unit test coverage for business logic

## 🚀 Quick Start

### 🐳 Docker Setup (Recommended) - Ready to Go!

This project is **pre-configured** for Docker! Just clone and run:

```bash
# 1. Clone the repository
git clone <repository-url>
cd FlashCardPractice

# 2. Copy Docker environment configuration
cp .env.docker .env

# 3. Build and start Docker containers (Docker Desktop must be running)
./docker/scripts/docker-up

# 4. Run database migrations
./docker/scripts/migrate

# 5. Start the flashcard application
./docker/scripts/artisan flashcard:interactive
```

**That's it!** 🎉 The application will be available at `http://localhost:8080`

> **Note**: If you get a "Docker Compose not found" error, please install [Docker Desktop](https://www.docker.com/products/docker-desktop/) which includes Docker Compose.

### 💻 Local Development (Alternative)

If you prefer to run without Docker:

```bash
# Clone and setup
git clone <repository-url>
cd FlashCardPractice

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Setup database (SQLite for development)
touch database/database.sqlite
# Update .env to use SQLite
sed -i '' 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
sed -i '' 's/DB_HOST=mysql/#DB_HOST=mysql/' .env
sed -i '' 's/DB_DATABASE=flash_card_practice/DB_DATABASE=database\/database.sqlite/' .env

# Run migrations
php artisan migrate

# Start the interactive application
php artisan flashcard:interactive
```

## 📋 Requirements

### 🐳 Docker Development (Recommended & Pre-configured)
- **Docker Desktop** - Download from [docker.com](https://www.docker.com/products/docker-desktop/)
- **Git** - For cloning the repository

### 💻 Local Development (Alternative)
- **PHP 8.1+** with extensions: PDO, MySQL, SQLite
- **Composer** - PHP dependency manager
- **Laravel 10.x** (included in dependencies)
- **Database**: SQLite (development) or MySQL (production)

## Installation & Setup

### 1. Environment Configuration

The application supports both SQLite (development/testing) and MySQL (production):

**For SQLite (Recommended for development):**
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

**For MySQL (Production):**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flashcard_practice
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 2. Database Setup

```bash
# Create SQLite database file
touch database/database.sqlite

# Run migrations
php artisan migrate
```

### 3. Dependencies

The application uses the following key dependencies:

- **league/tactician** - CQRS command bus implementation
- **Laravel Framework** - Core application framework
- **PHPUnit** - Testing framework

## 🐳 Docker Development Setup

### ✅ Pre-configured Setup

**This project is ready for Docker out of the box!** Clean vanilla Docker setup without Laravel Sail.

**What's included:**
- ✅ `docker-compose.yml` - Laravel app + Nginx + MySQL + Redis
- ✅ `Dockerfile` - Custom PHP 8.4-FPM container
- ✅ `docker/nginx/nginx.conf` - Nginx configuration for Laravel
- ✅ `docker/scripts/` - Helper scripts for common tasks
- ✅ `.env.docker` - Pre-configured environment for Docker

### 🚀 Getting Started

```bash
# Make sure Docker Desktop is running, then:
cp .env.docker .env
./docker/scripts/docker-up
./docker/scripts/migrate
./docker/scripts/artisan flashcard:interactive
```

### 🛠️ Docker Helper Scripts

The project includes convenient helper scripts in `docker/scripts/`:

```bash
# Run artisan commands
./docker/scripts/artisan migrate
./docker/scripts/artisan flashcard:interactive

# Run composer commands
./docker/scripts/composer install
./docker/scripts/composer update

# Database operations
./docker/scripts/migrate
./docker/scripts/seed

# Laravel Tinker
./docker/scripts/tinker

# Verify setup
./docker/scripts/verify

# Check for port conflicts
./docker/scripts/check-ports
```

### 🐳 Docker Services

The setup includes the following services:

- **app** - Laravel application (PHP 8.4-FPM)
- **web** - Nginx web server (port 8080)
- **mysql** - MySQL 8.0 database (port 3307)*
- **redis** - Redis cache/session store (port 6380)*

*External ports are mapped to non-standard ports to avoid conflicts with local services

### ⚙️ Environment Configuration

✅ **Copy `.env.docker` to `.env`** for Docker-ready configuration:

```env
# Application
APP_URL=http://localhost:8080

# Database (Docker-ready)
DB_CONNECTION=mysql
DB_HOST=mysql              # Docker service name
DB_USERNAME=laravel        # Docker MySQL user
DB_PASSWORD=password       # Docker MySQL password

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=redis           # Docker service name
```

### 🔧 Common Docker Commands

```bash
# Build and start all services
./docker/scripts/docker-up

# View logs
docker-compose logs -f  # or: docker compose logs -f

# Stop all services
./docker/scripts/docker-down

# Rebuild a specific service
docker-compose build app  # or: docker compose build app

# Access container shell
docker-compose exec app bash  # or: docker compose exec app bash

# View running containers
docker-compose ps  # or: docker compose ps
```

> **Note**: The helper scripts automatically detect whether to use `docker-compose` or `docker compose` based on your installation.

### 🐛 Troubleshooting

#### Port Conflicts
If you get "port already in use" errors:

```bash
# Check what's using the ports
./docker/scripts/check-ports

# Stop local MySQL/Redis if running
brew services stop mysql
brew services stop redis

# Or manually check specific ports
lsof -i :3306
lsof -i :6379
```

#### Common Issues
- **MySQL port 3306 in use**: Our setup uses port 3307 externally to avoid conflicts
- **Redis port 6379 in use**: Our setup uses port 6380 externally to avoid conflicts
- **Permission errors**: Make sure Docker Desktop is running
- **Build failures**: Try `docker system prune` to clean up Docker cache

## Usage

### Interactive CLI Application

Start the interactive application:

```bash
php artisan flashcard:interactive
```

### Available Menu Options

1. **Create a flashcard** - Add new questions and answers
2. **List all flashcards** - View all existing flashcards
3. **Practice** - Interactive practice sessions
4. **Stats** - View practice statistics
5. **Reset** - Clear all practice progress
6. **Exit** - Close the application

## Architecture

### CQRS Implementation

The application implements CQRS (Command Query Responsibility Segregation) using the Tactician library:

#### Commands (Write Operations)
- `CreateFlashcardCommand` - Creates new flashcards
- `SubmitAnswerCommand` - Submits practice answers
- `ResetProgressCommand` - Clears practice progress

#### Queries (Read Operations)
- `GetAllFlashcardsQuery` - Retrieves all flashcards
- `GetQuestionsWithStatus` - Gets all quesitons with status
- `GetStatsQuery` - Calculates practice statistics
- `GetPracticeableQuestionsQuery` - Gets questions available for practice

#### Architecture Benefits

1. **Separation of Concerns** - Clear distinction between read and write operations
2. **Testability** - Each handler can be tested in isolation
3. **Maintainability** - Business logic is encapsulated in dedicated handlers
4. **Scalability** - Easy to optimize read and write operations independently

### Project Structure

```
app/
├── Commands/           # CQRS Commands & Handlers
├── Queries/           # CQRS Queries & Handlers
├── Models/            # Eloquent models
├── Console/Commands/  # Artisan commands
└── Providers/         # Service providers
```

## Database Structure

### Tables

#### 1. flashcards
Stores flashcard questions and answers.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| question | text | Question text (unique) |
| answer | text | Correct answer |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

**Constraints:**
- Unique constraint on `question` to prevent duplicates

#### 2. practice_attempts
Audit trail of all practice attempts.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| flashcard_id | bigint | Foreign key to flashcards |
| user_answer | text | User's submitted answer |
| is_correct | boolean | Whether answer was correct |
| created_at | timestamp | Attempt timestamp |
| updated_at | timestamp | Last update timestamp |

**Relationships:**
- `flashcard_id` → `flashcards.id` (CASCADE DELETE)

**Indexes:**
- Composite index on `(flashcard_id, created_at)` for efficient querying

#### 3. practice_statuses
Current progress status per question.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| flashcard_id | bigint | Foreign key to flashcards (unique) |
| status | enum | Current status: 'not_answered', 'correct', 'incorrect' |
| last_attempted_at | timestamp | Last attempt timestamp |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

**Relationships:**
- `flashcard_id` → `flashcards.id` (CASCADE DELETE)

**Constraints:**
- Unique constraint on `flashcard_id` (one progress record per flashcard)

**Indexes:**
- Index on `status` for efficient filtering

### Database Design Decisions

1. **Separation of Concerns** - Practice attempts (audit trail) are separate from current progress (status tracking)
2. **Data Integrity** - Foreign key constraints ensure referential integrity
3. **Performance** - Strategic indexes for common query patterns
4. **Audit Trail** - Complete history of all practice attempts is preserved
5. **Multi-User Ready** - Schema designed to support future user isolation

## Business Rules

### Core Logic
- Questions marked "Correct" cannot be practiced again
- Individual attempts are tracked while maintaining current status
- Completion percentage = (correctly answered / total questions) * 100
- Reset removes progress but preserves flashcards
- All users can access all flashcards (no user isolation currently)

### Validation Rules
- Questions and answers cannot be empty
- Questions must be unique
- Case-insensitive answer comparison
- Whitespace is trimmed from inputs

### Data Integrity
- Handle concurrent access gracefully
- Maintain referential integrity through foreign keys
- Atomic operations using database transactions

## 🧪 Testing

### 🐳 Running Tests with Docker

```bash
# Run all tests
./docker/scripts/artisan test

# Run only unit tests
./docker/scripts/artisan test --testsuite=Unit

# Run only integration tests
./docker/scripts/artisan test --testsuite=Integration

# Run with coverage (requires Xdebug)
./docker/scripts/artisan test --coverage

# Run specific test class
./docker/scripts/artisan test tests/Unit/CreateFlashcardCommandHandlerTest.php

# Run tests in parallel (faster)
./docker/scripts/artisan test --parallel
```

### 💻 Running Tests Locally

```bash
# Run all tests
php artisan test
```

### 🗄️ Test Environment

**Docker**: Tests use MySQL test database in the Docker container
**Local**: Tests use SQLite in-memory database for:
- Fast execution
- Isolation between tests
- No external dependencies
- Consistent test environment

### Testing Patterns

- **Arrange-Act-Assert** pattern for clear test structure
- **Database transactions** for test isolation
- **Edge case testing** for validation logic
- **Happy path and error scenarios** for comprehensive coverage

### Adding New Features

1. **Commands** - Create command class and handler in respective directories
2. **Queries** - Create query class and handler in respective directories
3. **Register** - Add to `CqrsServiceProvider` for dependency injection
4. **Test** - Create comprehensive unit tests for new functionality

### Code Quality

- Follow PSR-12 coding standards
- Use type hints and return types
- Write descriptive method and variable names
- Include comprehensive PHPDoc comments
- Maintain high test coverage

### Performance Considerations

- Database indexes for common query patterns
- Eager loading for relationships when needed
- Efficient pagination for large datasets
- Query optimization for statistics calculations