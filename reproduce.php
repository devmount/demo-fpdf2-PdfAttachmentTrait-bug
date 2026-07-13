<?php

/*
 * Minimal reproduction of a bug in laurentmuller/fpdf2 v4.3.10's
 * fpdf\Traits\PdfAttachmentTrait, still present on the library's `main`
 * branch at the time of writing.
 *
 * See README.md in this directory for the full explanation.
 *
 * Run with:
 *   php demo/reproduce.php
 *
 * Then inspect demo/output.pdf, e.g.:
 *   pdfdetach -list demo/output.pdf
 *   -> "Syntax Error: Invalid FileSpec"
 */

require __DIR__ . '/vendor/autoload.php';

use fpdf\PdfDocument;
use fpdf\Traits\PdfAttachmentTrait;

// A minimal PdfDocument subclass using the trait completely unmodified,
// exactly as the library's own documentation/examples show.
class DemoPdf extends PdfDocument
{
    use PdfAttachmentTrait;
}

$attachmentPath = __DIR__ . '/sample.txt';
file_put_contents($attachmentPath, "This is the file that gets embedded into the PDF.\n");

$pdf = new DemoPdf();
$pdf->addPage();
$pdf->setFont('Helvetica');
$pdf->text(10, 20, 'PdfAttachmentTrait bug demo - see README.md');

$pdf->addAttachment($attachmentPath);

$outputPath = __DIR__ . '/output.pdf';
$pdf->output(\fpdf\Enums\PdfDestination::FILE, $outputPath);

echo "Generated: {$outputPath}\n";

// Show the malformed bytes directly, no PDF tooling required.
$bytes = file_get_contents($outputPath);
$pos = strpos($bytes, '/Names [');
echo "\nRaw /Names array from the generated PDF:\n";
echo substr($bytes, $pos, 40) . "\n";
echo "\nExpected (valid) shape: /Names [(000) 3 0 R]\n";
echo "Actual (broken) shape:  /Names [(000 3 0 R)]  <- the whole \"name + reference\"\n";
echo "                                               got merged into ONE string literal.\n";
