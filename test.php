<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$roots = require __DIR__ . "/config.php";

header("Content-Type: text/plain; charset=utf-8");

foreach ($roots as $label => $root) {
    echo "== $label ==\n";
    echo "root: $root\n";
    echo "is_dir: " . (is_dir($root) ? "yes" : "no") . "\n";
    echo "is_readable: " . (is_readable($root) ? "yes" : "no") . "\n";
    $real = realpath($root);
    echo "realpath: " . ($real ?: "(false)") . "\n";

    $count = 0;
    $sample = [];
    if (is_dir($root) && is_readable($root)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $fileinfo) {
            if ($fileinfo->isFile() && preg_match('/\.pdf$/i', $fileinfo->getFilename())) {
                $count++;
                if (count($sample) < 5) {
                    $sample[] = $fileinfo->getFilename();
                }
            }
            if ($count >= 2000) {
                break; // avoid huge traversal
            }
        }
    }
    echo "pdf_count (up to 2000): $count\n";
    if ($sample) {
        echo "sample:\n";
        foreach ($sample as $s) {
            echo "  - $s\n";
        }
    }
    echo "\n";
}
