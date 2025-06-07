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

## Quick Start

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
php artisan migrate

# Start the interactive application
php artisan flashcard:interactive
```

## Requirements

- PHP 8.1+
- Laravel 10.x
- SQLite (development) / MySQL (production)
- Composer

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

# (Optional) Seed with sample data
php artisan db:seed
```

### 3. Dependencies

The application uses the following key dependencies:

- **league/tactician** - CQRS command bus implementation
- **Laravel Framework** - Core application framework
- **PHPUnit** - Testing framework

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

### Practice Flow

The practice mode follows a structured flow:

1. **Progress Overview** - Shows current status of all questions
2. **Question Selection** - Choose from available questions (excludes correctly answered)
3. **Answer Submission** - Submit your answer and receive immediate feedback
4. **Continuous Practice** - Return to progress overview for continued practice

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
├── Commands/                 # CQRS Commands
│   ├── CreateFlashcardCommand.php
│   ├── SubmitAnswerCommand.php
│   ├── ResetProgressCommand.php
│   └── Handlers/            # Command handlers
│       ├── CreateFlashcardCommandHandler.php
│       ├── SubmitAnswerCommandHandler.php
│       └── ResetProgressCommandHandler.php
├── Queries/                 # CQRS Queries
│   ├── GetAllFlashcardsQuery.php
│   ├── GetPracticeProgressQuery.php
│   ├── GetStatsQuery.php
│   ├── GetPracticeableQuestionsQuery.php
│   └── Handlers/           # Query handlers
│       ├── GetAllFlashcardsQueryHandler.php
│       ├── GetPracticeProgressQueryHandler.php
│       ├── GetStatsQueryHandler.php
│       └── GetPracticeableQuestionsQueryHandler.php
├── Models/                 # Eloquent models
│   ├── Flashcard.php
│   ├── PracticeAttempt.php
│   └── QuestionProgress.php
├── Console/Commands/       # Artisan commands
│   └── FlashcardInteractiveCommand.php
├── Providers/             # Service providers
│   └── CqrsServiceProvider.php
└── Services/              # Application services
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
- Maximum length: 65,535 characters for questions and answers
- Case-insensitive answer comparison
- Whitespace is trimmed from inputs

### Data Integrity
- Handle concurrent access gracefully
- Maintain referential integrity through foreign keys
- Atomic operations using database transactions

## Testing

### Test Coverage

The application includes comprehensive unit tests covering:

- **Command Handlers** - All business logic, validation, and database interactions
- **Query Handlers** - Data retrieval, filtering, and calculations
- **Models** - Relationships, scopes, and model behavior
- **Validation** - Input validation and error handling

### Running Tests

```bash
# Run all tests
php artisan test

# Run only unit tests
php artisan test --testsuite=Unit

# Run with coverage (requires Xdebug)
php artisan test --coverage

# Run specific test class
php artisan test tests/Unit/CreateFlashcardCommandHandlerTest.php
```

### Test Environment

Tests use SQLite in-memory database for:
- Fast execution
- Isolation between tests
- No external dependencies
- Consistent test environment

### Testing Patterns

- **Arrange-Act-Assert** pattern for clear test structure
- **Database transactions** for test isolation
- **Mocking** for external dependencies
- **Edge case testing** for validation logic
- **Happy path and error scenarios** for comprehensive coverage

## Development

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

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Verify `.env` configuration
   - Ensure database file exists (SQLite)
   - Check database permissions

2. **Migration Errors**
   - Clear migration cache: `php artisan migrate:reset`
   - Re-run migrations: `php artisan migrate`

3. **Command Not Found**
   - Clear application cache: `php artisan cache:clear`
   - Regenerate autoload files: `composer dump-autoload`

4. **Test Failures**
   - Ensure test database is properly configured
   - Check for conflicting data in test environment
   - Verify all dependencies are installed

### Debug Mode

Enable debug mode for detailed error information:

```env
APP_DEBUG=true
APP_ENV=local
```
