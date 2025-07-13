#!/bin/bash

# PDF Masking Setup Script

echo "Setting up PDF Masking dependencies..."

# Check if Python 3 is installed
if ! command -v python3 &> /dev/null; then
    echo "Error: Python 3 is not installed. Please install Python 3 first."
    exit 1
fi

# Check if pip is installed
if ! command -v pip3 &> /dev/null; then
    echo "Error: pip3 is not installed. Please install pip3 first."
    exit 1
fi

# Install Python dependencies
echo "Installing Python dependencies..."
pip3 install -r requirements.txt

# Check if installation was successful
if [ $? -eq 0 ]; then
    echo "✅ PDF Masking setup completed successfully!"
    echo ""
    echo "Available algorithms:"
    echo "- regex_replace: Simple text replacement using regular expressions"
    echo "- pypdf_redaction: PDF-level redaction using PyPDF library"
    echo "- reportlab_overlay: Black box overlay using ReportLab"
    echo "- fitz_redaction: Advanced redaction using PyMuPDF (Fitz)"
    echo "- pdfplumber_mask: Text extraction and replacement with PDFPlumber"
    echo ""
    echo "You can now use the PDF masking feature in your application."
else
    echo "❌ Error installing Python dependencies."
    echo "Please check the error messages above and try again."
    exit 1
fi
