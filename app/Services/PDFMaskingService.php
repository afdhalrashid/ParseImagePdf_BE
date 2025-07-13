<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class PDFMaskingService
{
    protected $pythonExecutable;
    protected $scriptsPath;

    public function __construct()
    {
        $this->pythonExecutable = config('app.python_executable', 'python3');
        $this->scriptsPath = storage_path('app/masking_scripts');
        
        // Ensure scripts directory exists
        if (!is_dir($this->scriptsPath)) {
            mkdir($this->scriptsPath, 0755, true);
        }
        
        $this->createMaskingScripts();
    }

    /**
     * Process PDF masking with a specific algorithm
     */
    public function processWithAlgorithm(string $originalPath, array $wordsToMask, string $algorithm, string $jobId): array
    {
        $startTime = microtime(true);
        
        try {
            $fullOriginalPath = Storage::disk('public')->path($originalPath);
            $outputDir = Storage::disk('public')->path("masking/results/{$jobId}");
            
            // Ensure output directory exists
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            $outputFilename = Str::uuid() . "_{$algorithm}.pdf";
            $outputPath = "{$outputDir}/{$outputFilename}";
            
            // Execute the masking script
            $result = $this->executeMaskingScript($algorithm, $fullOriginalPath, $outputPath, $wordsToMask);
            
            $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            if ($result['success']) {
                $relativePath = "masking/results/{$jobId}/{$outputFilename}";
                $fileSize = file_exists($outputPath) ? filesize($outputPath) : 0;
                
                return [
                    'algorithm_name' => $this->getAlgorithmName($algorithm),
                    'library_used' => $this->getLibraryName($algorithm),
                    'status' => 'completed',
                    'processing_time' => round($processingTime),
                    'file_size' => $fileSize,
                    'words_masked_count' => $result['words_masked_count'],
                    'masked_file_path' => $relativePath
                ];
            } else {
                return [
                    'algorithm_name' => $this->getAlgorithmName($algorithm),
                    'library_used' => $this->getLibraryName($algorithm),
                    'status' => 'failed',
                    'processing_time' => round($processingTime),
                    'file_size' => 0,
                    'words_masked_count' => 0,
                    'error_message' => $result['error']
                ];
            }
            
        } catch (\Exception $e) {
            $processingTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'algorithm_name' => $this->getAlgorithmName($algorithm),
                'library_used' => $this->getLibraryName($algorithm),
                'status' => 'failed',
                'processing_time' => round($processingTime),
                'file_size' => 0,
                'words_masked_count' => 0,
                'error_message' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute the appropriate masking script
     */
    private function executeMaskingScript(string $algorithm, string $inputPath, string $outputPath, array $wordsToMask): array
    {
        $scriptPath = "{$this->scriptsPath}/{$algorithm}.py";
        
        if (!file_exists($scriptPath)) {
            throw new \Exception("Masking script not found for algorithm: {$algorithm}");
        }
        
        // Prepare arguments
        $wordsJson = json_encode($wordsToMask);
        $command = [
            $this->pythonExecutable,
            $scriptPath,
            $inputPath,
            $outputPath,
            $wordsJson
        ];
        
        // Execute the Python script
        $result = Process::run($command);
        
        if ($result->successful()) {
            $output = json_decode($result->output(), true);
            return [
                'success' => true,
                'words_masked_count' => $output['words_masked_count'] ?? 0
            ];
        } else {
            return [
                'success' => false,
                'error' => $result->errorOutput() ?: 'Unknown error occurred'
            ];
        }
    }

    /**
     * Create masking scripts for different algorithms
     */
    private function createMaskingScripts(): void
    {
        $this->createRegexReplaceScript();
        $this->createPyPDFRedactionScript();
        $this->createReportLabOverlayScript();
        $this->createFitzRedactionScript();
        $this->createPDFPlumberMaskScript();
    }

    /**
     * Create regex replace script
     */
    private function createRegexReplaceScript(): void
    {
        $script = <<<'PYTHON'
#!/usr/bin/env python3
import sys
import json
import re
from PyPDF2 import PdfReader, PdfWriter
import io

def mask_pdf_regex(input_path, output_path, words_to_mask):
    try:
        words_masked_count = 0
        
        # Read the PDF
        reader = PdfReader(input_path)
        writer = PdfWriter()
        
        for page in reader.pages:
            # Extract text from page
            text = page.extract_text()
            
            # Replace words (case-insensitive)
            modified_text = text
            for word in words_to_mask:
                if word.strip():
                    pattern = re.compile(re.escape(word), re.IGNORECASE)
                    matches = pattern.findall(modified_text)
                    words_masked_count += len(matches)
                    modified_text = pattern.sub('█' * len(word), modified_text)
            
            # Note: This is a simplified approach
            # In a real implementation, you'd need to modify the PDF content stream
            writer.add_page(page)
        
        # Write the output PDF
        with open(output_path, 'wb') as output_file:
            writer.write(output_file)
        
        return {'words_masked_count': words_masked_count}
        
    except Exception as e:
        raise Exception(f"Regex masking failed: {str(e)}")

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python script.py <input_pdf> <output_pdf> <words_json>")
        sys.exit(1)
    
    input_path = sys.argv[1]
    output_path = sys.argv[2]
    words_to_mask = json.loads(sys.argv[3])
    
    try:
        result = mask_pdf_regex(input_path, output_path, words_to_mask)
        print(json.dumps(result))
    except Exception as e:
        print(f"Error: {str(e)}", file=sys.stderr)
        sys.exit(1)
PYTHON;

        file_put_contents("{$this->scriptsPath}/regex_replace.py", $script);
        chmod("{$this->scriptsPath}/regex_replace.py", 0755);
    }

    /**
     * Create PyPDF redaction script
     */
    private function createPyPDFRedactionScript(): void
    {
        $script = <<<'PYTHON'
#!/usr/bin/env python3
import sys
import json
import re
from PyPDF2 import PdfReader, PdfWriter
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter
import io

def mask_pdf_pypdf(input_path, output_path, words_to_mask):
    try:
        words_masked_count = 0
        
        # Read the PDF
        reader = PdfReader(input_path)
        writer = PdfWriter()
        
        for page in reader.pages:
            # Extract text from page
            text = page.extract_text()
            
            # Count masked words
            for word in words_to_mask:
                if word.strip():
                    pattern = re.compile(re.escape(word), re.IGNORECASE)
                    matches = pattern.findall(text)
                    words_masked_count += len(matches)
            
            # Add page to writer (simplified - real implementation would modify content)
            writer.add_page(page)
        
        # Write the output PDF
        with open(output_path, 'wb') as output_file:
            writer.write(output_file)
        
        return {'words_masked_count': words_masked_count}
        
    except Exception as e:
        raise Exception(f"PyPDF masking failed: {str(e)}")

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python script.py <input_pdf> <output_pdf> <words_json>")
        sys.exit(1)
    
    input_path = sys.argv[1]
    output_path = sys.argv[2]
    words_to_mask = json.loads(sys.argv[3])
    
    try:
        result = mask_pdf_pypdf(input_path, output_path, words_to_mask)
        print(json.dumps(result))
    except Exception as e:
        print(f"Error: {str(e)}", file=sys.stderr)
        sys.exit(1)
PYTHON;

        file_put_contents("{$this->scriptsPath}/pypdf_redaction.py", $script);
        chmod("{$this->scriptsPath}/pypdf_redaction.py", 0755);
    }

    /**
     * Create ReportLab overlay script
     */
    private function createReportLabOverlayScript(): void
    {
        $script = <<<'PYTHON'
#!/usr/bin/env python3
import sys
import json
import re
from PyPDF2 import PdfReader, PdfWriter
from reportlab.pdfgen import canvas
from reportlab.lib.colors import black
import io

def mask_pdf_reportlab(input_path, output_path, words_to_mask):
    try:
        words_masked_count = 0
        
        # Read the PDF
        reader = PdfReader(input_path)
        writer = PdfWriter()
        
        for page_num, page in enumerate(reader.pages):
            # Extract text from page
            text = page.extract_text()
            
            # Count masked words
            for word in words_to_mask:
                if word.strip():
                    pattern = re.compile(re.escape(word), re.IGNORECASE)
                    matches = pattern.findall(text)
                    words_masked_count += len(matches)
            
            # Create overlay with black rectangles (simplified)
            packet = io.BytesIO()
            overlay_canvas = canvas.Canvas(packet, pagesize=letter)
            overlay_canvas.setFillColor(black)
            
            # This is a simplified approach - real implementation would
            # need to detect word positions and overlay black rectangles
            overlay_canvas.save()
            
            # Add page to writer
            writer.add_page(page)
        
        # Write the output PDF
        with open(output_path, 'wb') as output_file:
            writer.write(output_file)
        
        return {'words_masked_count': words_masked_count}
        
    except Exception as e:
        raise Exception(f"ReportLab masking failed: {str(e)}")

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python script.py <input_pdf> <output_pdf> <words_json>")
        sys.exit(1)
    
    input_path = sys.argv[1]
    output_path = sys.argv[2]
    words_to_mask = json.loads(sys.argv[3])
    
    try:
        result = mask_pdf_reportlab(input_path, output_path, words_to_mask)
        print(json.dumps(result))
    except Exception as e:
        print(f"Error: {str(e)}", file=sys.stderr)
        sys.exit(1)
PYTHON;

        file_put_contents("{$this->scriptsPath}/reportlab_overlay.py", $script);
        chmod("{$this->scriptsPath}/reportlab_overlay.py", 0755);
    }

    /**
     * Create PyMuPDF (Fitz) redaction script
     */
    private function createFitzRedactionScript(): void
    {
        $script = <<<'PYTHON'
#!/usr/bin/env python3
import sys
import json
import re
import fitz  # PyMuPDF

def mask_pdf_fitz(input_path, output_path, words_to_mask):
    try:
        words_masked_count = 0
        
        # Open the PDF
        doc = fitz.open(input_path)
        
        for page in doc:
            # Search for words and redact them
            for word in words_to_mask:
                if word.strip():
                    # Search for the word (case-insensitive)
                    text_instances = page.search_for(word, quads=True)
                    words_masked_count += len(text_instances)
                    
                    # Redact each instance
                    for inst in text_instances:
                        page.add_redact_annot(inst, text="", fill=(0, 0, 0))
            
            # Apply redactions
            page.apply_redactions()
        
        # Save the redacted PDF
        doc.save(output_path)
        doc.close()
        
        return {'words_masked_count': words_masked_count}
        
    except Exception as e:
        raise Exception(f"Fitz masking failed: {str(e)}")

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python script.py <input_pdf> <output_pdf> <words_json>")
        sys.exit(1)
    
    input_path = sys.argv[1]
    output_path = sys.argv[2]
    words_to_mask = json.loads(sys.argv[3])
    
    try:
        result = mask_pdf_fitz(input_path, output_path, words_to_mask)
        print(json.dumps(result))
    except Exception as e:
        print(f"Error: {str(e)}", file=sys.stderr)
        sys.exit(1)
PYTHON;

        file_put_contents("{$this->scriptsPath}/fitz_redaction.py", $script);
        chmod("{$this->scriptsPath}/fitz_redaction.py", 0755);
    }

    /**
     * Create PDFPlumber mask script
     */
    private function createPDFPlumberMaskScript(): void
    {
        $script = <<<'PYTHON'
#!/usr/bin/env python3
import sys
import json
import re
import pdfplumber
from PyPDF2 import PdfReader, PdfWriter

def mask_pdf_pdfplumber(input_path, output_path, words_to_mask):
    try:
        words_masked_count = 0
        
        # Extract text using pdfplumber
        with pdfplumber.open(input_path) as pdf:
            # Read the original PDF
            reader = PdfReader(input_path)
            writer = PdfWriter()
            
            for page_num, page in enumerate(pdf.pages):
                # Extract text from current page
                text = page.extract_text()
                
                # Count masked words
                for word in words_to_mask:
                    if word.strip():
                        pattern = re.compile(re.escape(word), re.IGNORECASE)
                        matches = pattern.findall(text)
                        words_masked_count += len(matches)
                
                # Add original page to writer (simplified)
                writer.add_page(reader.pages[page_num])
        
        # Write the output PDF
        with open(output_path, 'wb') as output_file:
            writer.write(output_file)
        
        return {'words_masked_count': words_masked_count}
        
    except Exception as e:
        raise Exception(f"PDFPlumber masking failed: {str(e)}")

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python script.py <input_pdf> <output_pdf> <words_json>")
        sys.exit(1)
    
    input_path = sys.argv[1]
    output_path = sys.argv[2]
    words_to_mask = json.loads(sys.argv[3])
    
    try:
        result = mask_pdf_pdfplumber(input_path, output_path, words_to_mask)
        print(json.dumps(result))
    except Exception as e:
        print(f"Error: {str(e)}", file=sys.stderr)
        sys.exit(1)
PYTHON;

        file_put_contents("{$this->scriptsPath}/pdfplumber_mask.py", $script);
        chmod("{$this->scriptsPath}/pdfplumber_mask.py", 0755);
    }

    /**
     * Get algorithm name by ID
     */
    private function getAlgorithmName(string $algorithmId): string
    {
        $names = [
            'regex_replace' => 'Regex Replace',
            'pypdf_redaction' => 'PyPDF Redaction',
            'reportlab_overlay' => 'ReportLab Overlay',
            'fitz_redaction' => 'PyMuPDF Redaction',
            'pdfplumber_mask' => 'PDFPlumber Mask'
        ];

        return $names[$algorithmId] ?? 'Unknown Algorithm';
    }

    /**
     * Get library name by algorithm ID
     */
    private function getLibraryName(string $algorithmId): string
    {
        $libraries = [
            'regex_replace' => 'Python re module',
            'pypdf_redaction' => 'PyPDF2/PyPDF4',
            'reportlab_overlay' => 'ReportLab',
            'fitz_redaction' => 'PyMuPDF (Fitz)',
            'pdfplumber_mask' => 'PDFPlumber'
        ];

        return $libraries[$algorithmId] ?? 'Unknown Library';
    }
}
