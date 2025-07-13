# PDF Masking Feature Documentation

## Overview

The PDF Masking feature allows users to upload PDF documents and mask/redact specific words using multiple algorithms and libraries. Each algorithm produces a separate output file, allowing users to compare different masking approaches.

## Features

- **Multiple Algorithms**: 5 different masking algorithms using various Python libraries
- **Batch Processing**: Process multiple algorithms simultaneously
- **Progress Tracking**: Real-time status updates for each algorithm
- **Download & Preview**: Download masked PDFs or preview them in browser
- **History Management**: View all previous masking jobs and results

## Available Algorithms

### 1. Regex Replace
- **Library**: Python `re` module
- **Method**: Simple text replacement using regular expressions
- **Best for**: Quick text substitution
- **Limitations**: May not handle complex PDF structures well

### 2. PyPDF Redaction  
- **Library**: PyPDF2/PyPDF4
- **Method**: PDF-level redaction using PyPDF library
- **Best for**: Basic PDF manipulation
- **Limitations**: Limited visual redaction capabilities

### 3. ReportLab Overlay
- **Library**: ReportLab
- **Method**: Creates black box overlays on top of text
- **Best for**: Visual masking with black rectangles
- **Limitations**: Requires precise text positioning

### 4. PyMuPDF Redaction (Recommended)
- **Library**: PyMuPDF (Fitz)
- **Method**: Professional-grade redaction with proper text removal
- **Best for**: Production use, complete text removal
- **Features**: True redaction, not just visual hiding

### 5. PDFPlumber Mask
- **Library**: PDFPlumber
- **Method**: Advanced text extraction and replacement
- **Best for**: Complex PDF layouts and detailed text analysis
- **Features**: Accurate text positioning and extraction

## Usage Instructions

### Frontend (Vue.js)

1. **Navigate to Masking Page**: Go to `/masking` in your application
2. **Upload PDF**: Drag and drop or select a PDF file (max 10MB)
3. **Enter Words**: Add up to 5 words you want to mask
4. **Select Algorithms**: Choose which algorithms to use (multiple selection allowed)
5. **Process**: Click "Process Masking" to start the operation
6. **Review Results**: View results in the table below
7. **Download/Preview**: Use action buttons to download or preview masked PDFs

### API Endpoints

#### Process Masking
```
POST /api/masking/process
Content-Type: multipart/form-data

Parameters:
- file: PDF file to process
- words_to_mask: JSON array of words to mask
- algorithms: JSON array of algorithm IDs to use
```

#### Get Results
```
GET /api/masking/results/{jobId}
```

#### Download Masked PDF
```
GET /api/masking/download/{resultId}
```

#### Get History
```
GET /api/masking/history?page=1&per_page=20
```

#### Get Available Algorithms
```
GET /api/masking/algorithms
```

## Setup Instructions

### Backend Setup

1. **Run Migrations**:
   ```bash
   php artisan migrate
   ```

2. **Install Python Dependencies**:
   ```bash
   cd pdf-parser-app
   ./setup_masking.sh
   ```

3. **Configure Python Path** (optional):
   Add to your `.env` file:
   ```
   PYTHON_EXECUTABLE=python3
   ```

### Frontend Setup

The masking feature is automatically available once the backend is set up. The route `/masking` will be accessible to authenticated users.

## File Structure

### Backend Files
```
app/
├── Http/Controllers/API/MaskingController.php
├── Models/
│   ├── MaskingJob.php
│   └── MaskingResult.php
└── Services/PDFMaskingService.php

database/migrations/
├── 2024_07_14_000001_create_masking_jobs_table.php
└── 2024_07_14_000002_create_masking_results_table.php

storage/app/masking_scripts/
├── regex_replace.py
├── pypdf_redaction.py
├── reportlab_overlay.py
├── fitz_redaction.py
└── pdfplumber_mask.py
```

### Frontend Files
```
src/
├── views/MaskingView.vue
├── stores/masking.ts
└── services/masking.ts
```

## Database Schema

### masking_jobs
- `id` (UUID, Primary Key)
- `user_id` (Foreign Key to users)
- `original_file_path` (String)
- `original_filename` (String)
- `words_to_mask` (JSON Array)
- `algorithms` (JSON Array)
- `status` (Enum: processing, completed, failed)
- `created_at`, `updated_at` (Timestamps)

### masking_results
- `id` (UUID, Primary Key)
- `masking_job_id` (Foreign Key to masking_jobs)
- `algorithm_name` (String)
- `library_used` (String)
- `status` (Enum: processing, completed, failed)
- `processing_time` (Integer, milliseconds)
- `file_size` (BigInteger, bytes)
- `words_masked_count` (Integer)
- `masked_file_path` (String, nullable)
- `error_message` (Text, nullable)
- `created_at`, `updated_at` (Timestamps)

## Storage Organization

```
storage/app/public/masking/
├── originals/           # Original uploaded PDFs
└── results/            # Masked PDF outputs
    └── {jobId}/        # Results grouped by job
        ├── uuid_regex_replace.pdf
        ├── uuid_pypdf_redaction.pdf
        ├── uuid_reportlab_overlay.pdf
        ├── uuid_fitz_redaction.pdf
        └── uuid_pdfplumber_mask.pdf
```

## Error Handling

- **File Size Limits**: Maximum 10MB PDF files
- **Word Limits**: Maximum 5 words per masking job
- **Algorithm Failures**: Individual algorithms can fail without affecting others
- **Python Dependencies**: Graceful handling of missing Python libraries
- **Storage Issues**: Proper cleanup of failed processing attempts

## Performance Considerations

- **Concurrent Processing**: Each algorithm runs independently
- **Memory Usage**: Large PDFs may require significant memory
- **Processing Time**: Varies by algorithm complexity and PDF size
- **Storage Cleanup**: Automated cleanup of old masked files (implement as needed)

## Security Notes

- **File Validation**: Only PDF files are accepted
- **User Isolation**: Users can only access their own masking jobs
- **Path Security**: All file operations use Laravel's Storage facade
- **Input Sanitization**: Words to mask are properly escaped in Python scripts

## Troubleshooting

### Common Issues

1. **Python Not Found**: Ensure Python 3 is installed and accessible
2. **Library Import Errors**: Run `./setup_masking.sh` to install dependencies
3. **Permission Errors**: Check storage directory permissions
4. **Large File Timeouts**: Increase PHP execution time limits
5. **Memory Errors**: Increase PHP memory limits for large PDFs

### Debug Mode

Enable debug logging by setting `APP_DEBUG=true` in your `.env` file to see detailed error messages.

## Future Enhancements

- **Queue Processing**: Move masking to background jobs for better performance
- **Real-time Updates**: WebSocket support for live progress updates
- **Custom Algorithms**: Allow users to upload custom masking scripts
- **Batch Operations**: Process multiple PDFs simultaneously
- **Preview Integration**: In-browser PDF preview with highlighting
- **Export Options**: Multiple output formats (images, text files)
- **Template Management**: Save and reuse masking configurations
