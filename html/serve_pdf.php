<?php
// ----------------------------------------------------------------------
// serve_pdf.php - 安全地提供PDF文件服务，解决跨域问题
// ----------------------------------------------------------------------

// 获取要服务的PDF文件名
$file = isset($_GET['file']) ? $_GET['file'] : '';
if (empty($file)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// 安全检查：防止目录遍历攻击
$file = basename($file);
if (!preg_match('/\.pdf$/i', $file)) {
    header("HTTP/1.0 400 Bad Request");
    exit;
}

// PDF文件的实际路径
$pdf_path = "/var/www/jamesband.asia/" . $file;

// 检查文件是否存在
if (!file_exists($pdf_path)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// 设置CORS头，允许PDF.js访问
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// 设置PDF内容类型
header("Content-Type: application/pdf");
header("Content-Length: " . filesize($pdf_path));
header("Content-Disposition: inline; filename=\"" . $file . "\"");

// 禁用缓存以确保获取最新版本
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// 输出PDF文件内容
readfile($pdf_path);
?>