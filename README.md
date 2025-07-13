# PDF Parser Backend (Laravel)

A robust Laravel-based backend API for PDF upload, processing, and text extraction with user quota management and real-time processing.

## 🚀 Features

- **PDF Upload & Processing**: Upload PDFs with support for chunked uploads (large files)
- **Text Extraction**: Automatic text extraction from PDF files
- **User Quota Management**: Storage limits and usage tracking
- **Authentication**: Sanctum-based API authentication
- **Queue Processing**: Background job processing with Laravel Horizon
- **Real-time Updates**: WebSocket support for upload progress
- **Payment Integration**: Stripe integration for premium subscriptions

## 🛠 Tech Stack

- **Framework**: Laravel 10
- **Database**: SQLite (development) / PostgreSQL (production)
- **Queue**: Redis with Laravel Horizon
- **Authentication**: Laravel Sanctum
- **File Storage**: Local storage with organized directory structure
- **PDF Processing**: Custom PDF text extraction service

## 📋 Prerequisites

- PHP 8.1 or higher
- Composer
- Redis (for queue processing)
- SQLite or PostgreSQL

## 🔧 Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/ParseImagePdf_BE.git
   cd ParseImagePdf_BE
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure your `.env` file**
   ```env
   APP_NAME="PDF Parser API"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   # Database (SQLite for development)
   DB_CONNECTION=sqlite
   # DB_DATABASE=/path/to/database.sqlite

   # Queue Configuration
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis

   # Redis Configuration
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   ```

5. **Database setup**
   ```bash
   # Create SQLite database file
   touch database/database.sqlite
   
   # Run migrations
   php artisan migrate
   
   # Seed the database (optional)
   php artisan db:seed
   ```

6. **Create storage directories**
   ```bash
   php artisan storage:link
   mkdir -p storage/app/pdfs
   ```

## 🚀 Running the Application

1. **Start the Laravel server**
   ```bash
   php artisan serve --port=8000
   ```

2. **Start the queue worker (in a separate terminal)**
   ```bash
   php artisan horizon
   ```

3. **Start Redis (if not running as service)**
   ```bash
   redis-server
   ```

## 📡 API Endpoints

### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `POST /api/logout` - User logout
- `GET /api/me` - Get authenticated user

### PDF Management
- `GET /api/uploads` - List user uploads (paginated)
- `POST /api/uploads` - Upload PDF file
- `GET /api/uploads/{id}` - Get specific upload details
- `DELETE /api/uploads/{id}` - Delete upload
- `GET /api/uploads/{id}/download` - Download original PDF

### Chunked Uploads
- `POST /api/uploads/{id}/chunks` - Upload file chunk

### Quota Management
- `GET /api/quota` - Get user quota information
- `GET /api/quota/usage` - Get detailed usage statistics

### Payments (Premium Features)
- `GET /api/pricing` - Get pricing information
- `POST /api/payments/create-intent` - Create payment intent
- `POST /api/payments/confirm` - Confirm payment

## 🗃 Database Schema

### Users
- Standard Laravel user model with additional fields for quota management

### PDF Uploads
- `id`, `user_id`, `original_filename`, `stored_filename`
- `file_hash`, `file_size`, `mime_type`, `status`
- `is_chunked`, `total_chunks`, `uploaded_chunks`
- `extracted_text`, `metadata`, `error_message`

### User Quotas
- `id`, `user_id`, `used_storage`, `max_storage`
- `is_premium`, `premium_expires_at`

### PDF Chunks (for large file uploads)
- `id`, `pdf_upload_id`, `chunk_number`, `chunk_size`
- `chunk_hash`, `stored_path`, `status`

## 🔄 Queue Jobs

### ProcessPdfUpload
Handles PDF text extraction and processing after upload completion.

### CleanupFailedUploads
Removes failed upload files and database records.

## 🛡 Security Features

- **File Validation**: Strict PDF file type validation
- **Size Limits**: Configurable file size limits
- **Hash Verification**: File integrity verification using SHA-256
- **Quota Enforcement**: Prevents users from exceeding storage limits
- **Authentication**: All endpoints require valid API tokens

## 🎛 Configuration

### File Upload Limits
```php
// config/app.php
'max_file_size' => env('MAX_FILE_SIZE_MB', 100) * 1024 * 1024,
'chunk_size' => env('CHUNK_SIZE_MB', 20) * 1024 * 1024,
```

### Quota Settings
```php
// Default quota in bytes (100MB)
'default_quota' => 100 * 1024 * 1024,
'premium_quota' => -1, // Unlimited
```

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

## 📊 Monitoring

### Laravel Horizon
Access the Horizon dashboard at: `http://localhost:8000/horizon`

### Logs
- Application logs: `storage/logs/laravel.log`
- Queue processing logs available in Horizon dashboard

## 🚀 Deployment

### Production Environment Setup

1. **Environment Configuration**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   
   # Use PostgreSQL for production
   DB_CONNECTION=pgsql
   DB_HOST=your-db-host
   DB_DATABASE=your-db-name
   DB_USERNAME=your-db-user
   DB_PASSWORD=your-db-password
   ```

2. **Optimize for Production**
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan migrate --force
   ```

3. **Set up Supervisor for Queue Processing**
   ```ini
   [program:pdf-parser-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/your/app/artisan horizon
   autostart=true
   autorestart=true
   user=www-data
   numprocs=1
   redirect_stderr=true
   stdout_logfile=/path/to/your/app/storage/logs/worker.log
   ```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🐛 Troubleshooting

### Common Issues

1. **Queue not processing**
   - Ensure Redis is running
   - Check Horizon status: `php artisan horizon:status`
   - Restart Horizon: `php artisan horizon:terminate`

2. **File upload fails**
   - Check storage permissions: `chmod -R 775 storage/`
   - Verify storage directory exists: `storage/app/pdfs/`

3. **Database connection issues**
   - For SQLite: Ensure `database/database.sqlite` exists
   - Check `.env` database configuration

### Performance Optimization

- Use Redis for caching and sessions
- Enable OPcache in production
- Configure proper queue workers based on load
- Implement database indexing for large datasets

## 📞 Support

For support and questions, please open an issue on GitHub or contact the development team.

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
