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

### 🐳 Docker with Laravel Sail (Recommended) - Ready to Go!

This project is **pre-configured** for Docker! Just clone and run:

```bash
# 1. Clone the repository
git clone <repository-url>
cd FlashCardPractice

# 2. Start Docker containers (Docker Desktop must be running)
./vendor/bin/sail up -d

# 3. Run database migrations
./vendor/bin/sail artisan migrate

# 4. Start the flashcard application
./vendor/bin/sail artisan flashcard:interactive
```

**That's it!** 🎉 The `.env` file is already configured for Docker.

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

## 🐳 Docker Development with Laravel Sail

### ✅ Pre-configured Setup

**This project is ready for Docker out of the box!** No configuration needed.

**What's included:**
- ✅ `docker-compose.yml` - Laravel app + MySQL + Mailpit
- ✅ `.env` file - Pre-configured for Docker
- ✅ Laravel Sail - Installed and ready

### 🚀 Getting Started

```bash
# Make sure Docker Desktop is running, then:
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan flashcard:interactive
```

### Sail Alias (Recommended)

Add this alias to your shell configuration (`~/.zshrc` or `~/.bashrc`):

```bash
alias sail="./vendor/bin/sail"
```

Then reload your shell:
```bash
source ~/.zshrc  # or ~/.bashrc
```

Now you can use `sail` instead of `./vendor/bin/sail`:

```bash
# Start services
sail up -d

# Run artisan commands
sail artisan migrate
sail artisan flashcard:interactive

# Run composer commands
sail composer install

# Run tests
sail test
```

### ⚙️ Environment Configuration

✅ **No changes needed!** The `.env` file is pre-configured with:

```env
# Database (Docker-ready)
DB_CONNECTION=mysql
DB_HOST=mysql              # Docker service name
DB_USERNAME=sail           # Sail default
DB_PASSWORD=password       # Sail default

# Mail testing (Docker-ready)
MAIL_HOST=mailpit          # Docker service name
MAIL_PORT=1025             # Mailpit port
```

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
- `GetPracticeProgressQuery` - Gets current progress status
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
./vendor/bin/sail test

# Run only unit tests
./vendor/bin/sail test --testsuite=Unit

# Run only integration tests
./vendor/bin/sail test --testsuite=Integration

# Run with coverage (requires Xdebug)
./vendor/bin/sail test --coverage

# Run specific test class
./vendor/bin/sail test tests/Unit/CreateFlashcardCommandHandlerTest.php

# Run tests in parallel (faster)
./vendor/bin/sail test --parallel
```

### 💻 Running Tests Locally

```bash
# Run all tests
php artisan test
```

### 🗄️ Test Environment

**Docker**: Tests use MySQL test database automatically created by Sail
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