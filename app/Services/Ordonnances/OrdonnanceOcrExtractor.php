<?php

namespace App\Services\Ordonnances;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

class OrdonnanceOcrExtractor
{
    public function extractText(string $absolutePath, string $tesseractBinary = 'tesseract'): string
    {
        if (! is_readable($absolutePath)) {
            return '';
        }

        $mime = $this->guessMime($absolutePath);
        if ($mime === 'application/pdf') {
            return $this->extractFromPdf($absolutePath);
        }

        if (str_starts_with((string) $mime, 'image/')) {
            return $this->extractFromImageWithTesseract($absolutePath, $tesseractBinary);
        }

        return '';
    }

    private function guessMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $path) : false;
        if (is_resource($finfo)) {
            finfo_close($finfo);
        }

        return $mime !== false ? $mime : '';
    }

    private function extractFromPdf(string $path): string
    {
        try {
            $parser = new Parser;

            return trim($parser->parseFile($path)->getText());
        } catch (\Throwable $e) {
            Log::warning('Ordonnance PDF OCR: '.$e->getMessage());

            return '';
        }
    }

    private function extractFromImageWithTesseract(string $path, string $binary): string
    {
        if (! $this->tesseractAvailable($binary)) {
            return '';
        }

        try {
            $process = new Process([$binary, $path, 'stdout', '-l', 'fra+eng']);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('Tesseract failed: '.$process->getErrorOutput());

                return '';
            }

            return trim($process->getOutput());
        } catch (\Throwable $e) {
            Log::warning('Tesseract exception: '.$e->getMessage());

            return '';
        }
    }

    private function tesseractAvailable(string $binary): bool
    {
        try {
            $p = new Process([$binary, '--version']);
            $p->run();

            return $p->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }
}
