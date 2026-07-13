# fpdf2 `PdfAttachmentTrait` attachment bug — minimal reproduction

`laurentmuller/fpdf2` v4.3.10 (and, as of this writing, the library's `main`
branch — the bug isn't fixed upstream yet) ships a `fpdf\Traits\PdfAttachmentTrait`
that embeds a file into a PDF (`addAttachment()`), but the PDF it produces has
an invalid `/Names` array, so PDF readers fail to open/list the embedded file.

## The bug

A PDF name tree's `/Names` array must alternate **name string**, **indirect
reference**, e.g.:

```plain
/Names [(000) 3 0 R]
```

`PdfAttachmentTrait::putAttachments()` builds this array like so (source:
[`src/Traits/PdfAttachmentTrait.php`](https://github.com/laurentmuller/fpdf2/blob/4.3.10/src/Traits/PdfAttachmentTrait.php#L131)):

```php
$names = \array_map(
    fn (int $index, PdfAttachment $attachment): string => $this->encoder->textString(
        PdfWriter::sprintf('%03d %s', $index, $attachment->formatNumber())
    ),
    \array_keys($this->attachments),
    \array_values($this->attachments)
);
```

`$attachment->formatNumber()` already returns the indirect reference (e.g.
`"3 0 R"`). Formatting it together with the index *before* passing the whole
thing to `textString()` wraps **both** the name and the reference inside one
string literal:

```plain
/Names [(000 3 0 R)]
```

instead of the required name/reference pair. The `3 0 R` part is no longer a
real indirect reference at all — it's now just characters inside a string —
so the name tree is structurally broken.

## Reproducing

This demo keeps `laurentmuller/fpdf2` pinned to the exact buggy version (`4.3.10`, no `^`),
so it keeps reproducing the bug even after it's eventually fixed upstream.

```bash
cd demo
composer install
php reproduce.php
```

`reproduce.php` uses `PdfAttachmentTrait` completely unmodified, exactly as the
library's own documentation shows — then generates `output.pdf` with one
attached file and prints the raw `/Names` bytes from the generated file.

Expected output:

```plain
Raw /Names array from the generated PDF:
/Names [(000 7 0 R)]
```

(the object number will vary run to run) — note everything, including the
`0 R` reference, is inside a single `(...)` string.

Verify with any PDF tool that actually parses the name tree, e.g.
[poppler-utils](https://poppler.freedesktop.org/)' `pdfdetach`:

```bash
pdfdetach -list output.pdf
```

```plain
Syntax Error: Invalid FileSpec
1 embedded files
1:
```

It reports the file as embedded (poppler can see the catalog `/AF` entry) but
fails to resolve its name/reference pair, and the attachment can't be listed
or extracted (`pdfdetach -saveall` also fails the same way).

---

Disclaimer: I created this demo with Claude Code.
