<?php
// ----------------------------------------------------------------------
// pdf_viewer.php - 客户端PDF查看器，不占用服务器资源
// ----------------------------------------------------------------------

// 获取要显示的PDF文件名
$file = isset($_GET['file']) ? $_GET['file'] : '';
if (empty($file)) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>错误：未指定PDF文件</h1>";
    exit;
}

// 安全检查：防止目录遍历攻击
$file = basename($file);
if (!preg_match('/\.pdf$/i', $file)) {
    header("HTTP/1.0 400 Bad Request");
    echo "<h1>错误：无效的文件类型</h1>";
    exit;
}

// PDF文件的实际路径
$pdf_path = "/var/www/jamesband.asia/" . $file;
$pdf_url = "http://jamesband.asia/" . urlencode($file);

// 检查文件是否存在
if (!file_exists($pdf_path)) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>错误：PDF文件不存在</h1>";
    exit;
}

// 获取文件信息
$file_size = filesize($pdf_path);
$file_modified = filemtime($pdf_path);
$display_name = htmlspecialchars(pathinfo($file, PATHINFO_FILENAME));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $display_name; ?> - PDF查看器</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cdefs%3E%3CradialGradient id='spacetime' cx='50%25' cy='50%25' r='60%25'%3E%3Cstop offset='0%25' style='stop-color:%23002244;stop-opacity:1' /%3E%3Cstop offset='70%25' style='stop-color:%23001122;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23000000;stop-opacity:1' /%3E%3C/radialGradient%3E%3ClinearGradient id='grid' x1='0%25' y1='0%25' x2='100%25' y2='0%25'%3E%3Cstop offset='0%25' style='stop-color:%2393c5fd;stop-opacity:0.8' /%3E%3Cstop offset='100%25' style='stop-color:%2393c5fd;stop-opacity:0.3' /%3E%3C/defs%3E%3Ccircle cx='16' cy='16' r='15' fill='url(%23spacetime)'/%3E%3Cpath d='M 4 16 Q 16 8 28 16' stroke='url(%23grid)' stroke-width='1' fill='none' opacity='0.7'/%3E%3Cpath d='M 4 20 Q 16 12 28 20' stroke='url(%23grid)' stroke-width='0.8' fill='none' opacity='0.5'/%3E%3Cpath d='M 4 12 Q 16 4 28 12' stroke='url(%23grid)' stroke-width='0.8' fill='none' opacity='0.5'/%3E%3Cpath d='M 4 24 Q 16 16 28 24' stroke='url(%23grid)' stroke-width='0.6' fill='none' opacity='0.4'/%3E%3Ccircle cx='16' cy='16' r='2' fill='%23ffffff' opacity='0.9'/%3E%3Ccircle cx='16' cy='16' r='1' fill='%23ffaa00' opacity='0.8'/%3E%3C/svg%3E">
    
    <!-- 引入PDF.js库 - 使用CDN，客户端处理 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500;700&display=swap');
        
        :root {
            --star-bg: #0f0f23;
            --text-color: #e0e8f0;
            --header-color: #8A2BE2;
            --border-color: rgba(138, 43, 226, 0.1);
            --control-bg: rgba(15, 15, 35, 0.9);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans SC', sans-serif;
            background-color: var(--star-bg);
            color: var(--text-color);
            overflow: hidden;
        }

        .pdf-header {
            background-color: var(--control-bg);
            border-bottom: 2px solid var(--header-color);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1000;
        }

        .pdf-title {
            font-size: 1.2em;
            font-weight: 500;
            color: var(--header-color);
            max-width: 50%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pdf-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .pdf-info {
            font-size: 0.85em;
            color: #cbd5e1;
        }

        .control-btn {
            background-color: var(--header-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .control-btn:hover {
            background-color: #9d34d1;
            transform: translateY(-1px);
        }

        .control-btn:disabled {
            background-color: #555;
            cursor: not-allowed;
            transform: none;
        }

        .page-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-input {
            background-color: var(--control-bg);
            border: 1px solid var(--header-color);
            color: var(--text-color);
            padding: 5px 8px;
            border-radius: 3px;
            width: 60px;
            text-align: center;
            font-size: 0.9em;
        }

        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .zoom-level {
            color: var(--text-color);
            font-size: 0.85em;
            min-width: 40px;
            text-align: center;
        }

        .pdf-container {
            height: calc(100vh - 60px);
            overflow: auto;
            background-color: #2a2a2a;
            position: relative;
        }

        .pdf-viewer {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            min-height: 100%;
        }

        .pdf-page {
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            border-radius: 4px;
            overflow: hidden;
            background-color: white;
        }

        .loading-message {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 300px;
            font-size: 1.1em;
            color: var(--text-color);
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--header-color);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            color: #ff6b6b;
            text-align: center;
            padding: 40px;
            font-size: 1.1em;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .pdf-header {
                padding: 10px;
                height: auto;
                flex-direction: column;
                gap: 10px;
            }
            
            .pdf-title {
                max-width: 100%;
                text-align: center;
            }
            
            .pdf-controls {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            .pdf-container {
                height: calc(100vh - 100px);
            }
        }
    </style>
</head>
<body>
    <div class="pdf-header">
        <div class="pdf-title">
            <i class="fas fa-file-pdf"></i> <?php echo $display_name; ?>
        </div>
        <div class="pdf-controls">
            <div class="pdf-info">
                大小: <?php echo number_format($file_size / 1024, 1); ?>KB | 
                修改: <?php echo date("Y-m-d", $file_modified); ?>
            </div>
            <div class="page-controls">
                <button class="control-btn" onclick="previousPage()" id="prevBtn">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <input type="number" class="page-input" id="pageInput" min="1" onchange="goToPage()">
                <span style="color: var(--text-color);">/</span>
                <span id="totalPages" style="color: var(--text-color);">0</span>
                <button class="control-btn" onclick="nextPage()" id="nextBtn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="zoom-controls">
                <button class="control-btn" onclick="zoomOut()">
                    <i class="fas fa-search-minus"></i>
                </button>
                <span class="zoom-level" id="zoomLevel">100%</span>
                <button class="control-btn" onclick="zoomIn()">
                    <i class="fas fa-search-plus"></i>
                </button>
            </div>
            <button class="control-btn" onclick="downloadPDF()">
                <i class="fas fa-download"></i> 下载
            </button>
            <button class="control-btn" onclick="window.history.back()">
                <i class="fas fa-arrow-left"></i> 返回
            </button>
        </div>
    </div>

    <div class="pdf-container" id="pdfContainer">
        <div class="pdf-viewer" id="pdfViewer">
            <div class="loading-message">
                <div class="loading-spinner"></div>
                正在加载PDF文件...
            </div>
        </div>
    </div>

    <script>
        // PDF.js配置 - 完全客户端处理
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let currentPage = 1;
        let totalPages = 0;
        let currentZoom = 1.0;
        const PDF_URL = 'serve_pdf.php?file=<?php echo urlencode($file); ?>';

        // 加载PDF
        async function loadPDF() {
            try {
                console.log('开始加载PDF:', PDF_URL);
                
                // 首先检查PDF文件是否可访问
                const response = await fetch(PDF_URL, { method: 'HEAD' });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                console.log('PDF文件可访问，开始加载...');
                const loadingTask = pdfjsLib.getDocument({
                    url: PDF_URL,
                    cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/',
                    cMapPacked: true
                });
                
                pdfDoc = await loadingTask.promise;
                totalPages = pdfDoc.numPages;
                
                document.getElementById('totalPages').textContent = totalPages;
                document.getElementById('pageInput').value = currentPage;
                document.getElementById('pageInput').max = totalPages;
                
                await renderCurrentPage();
                updateControls();
                
                console.log('PDF加载成功，总页数:', totalPages);
            } catch (error) {
                console.error('PDF加载失败:', error);
                let errorMsg = 'PDF加载失败: ' + error.message;
                if (error.message.includes('HTTP 404')) {
                    errorMsg = 'PDF文件不存在或已被移动';
                } else if (error.message.includes('HTTP 403')) {
                    errorMsg = 'PDF文件访问被拒绝';
                } else if (error.message.includes('Failed to fetch')) {
                    errorMsg = 'PDF文件加载失败，请检查网络连接或文件路径';
                }
                document.getElementById('pdfViewer').innerHTML = 
                    '<div class="error-message"><i class="fas fa-exclamation-triangle"></i> ' + errorMsg + '<br><small>URL: ' + PDF_URL + '</small></div>';
            }
        }

        // 渲染当前页面
        async function renderCurrentPage() {
            if (!pdfDoc) return;
            
            try {
                const page = await pdfDoc.getPage(currentPage);
                // 考虑高分屏提高清晰度
                const ratio = window.devicePixelRatio || 1;
                const viewport = page.getViewport({ scale: currentZoom * ratio });
                
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvas.style.width = (viewport.width / ratio) + 'px';
                canvas.style.height = (viewport.height / ratio) + 'px';
                context.scale(ratio, ratio);
                canvas.className = 'pdf-page';
                
                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };
                
                // 清空查看器并显示加载中
                const viewer = document.getElementById('pdfViewer');
                viewer.innerHTML = '<div class="loading-message"><div class="loading-spinner"></div>正在渲染页面...</div>';
                
                await page.render(renderContext).promise;
                
                // 渲染完成后替换内容
                viewer.innerHTML = '';
                viewer.appendChild(canvas);
                
                console.log('页面', currentPage, '渲染完成');
            } catch (error) {
                console.error('页面渲染失败:', error);
                document.getElementById('pdfViewer').innerHTML = 
                    '<div class="error-message">页面渲染失败: ' + error.message + '</div>';
            }
        }

        // 导航控制
        function previousPage() {
            if (currentPage > 1) {
                currentPage--;
                document.getElementById('pageInput').value = currentPage;
                renderCurrentPage();
                updateControls();
            }
        }

        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                document.getElementById('pageInput').value = currentPage;
                renderCurrentPage();
                updateControls();
            }
        }

        function goToPage() {
            const pageInput = document.getElementById('pageInput');
            const pageNum = parseInt(pageInput.value);
            
            if (pageNum >= 1 && pageNum <= totalPages) {
                currentPage = pageNum;
                renderCurrentPage();
                updateControls();
            } else {
                pageInput.value = currentPage;
            }
        }

        // 缩放控制
        function zoomIn() {
            if (currentZoom < 3.0) {
                currentZoom += 0.25;
                updateZoomDisplay();
                renderCurrentPage();
            }
        }

        function zoomOut() {
            if (currentZoom > 0.5) {
                currentZoom -= 0.25;
                updateZoomDisplay();
                renderCurrentPage();
            }
        }

        function updateZoomDisplay() {
            document.getElementById('zoomLevel').textContent = Math.round(currentZoom * 100) + '%';
        }

        // 更新控制按钮状态
        function updateControls() {
            document.getElementById('prevBtn').disabled = (currentPage <= 1);
            document.getElementById('nextBtn').disabled = (currentPage >= totalPages);
        }

        // 下载PDF
        function downloadPDF() {
            const link = document.createElement('a');
            link.href = PDF_URL;
            link.download = '<?php echo htmlspecialchars($file); ?>';
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // 键盘快捷键
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    previousPage();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    nextPage();
                    break;
                case '=':
                case '+':
                    e.preventDefault();
                    zoomIn();
                    break;
                case '-':
                    e.preventDefault();
                    zoomOut();
                    break;
                case 'Escape':
                    window.history.back();
                    break;
            }
        });

        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            loadPDF();
        });
    </script>
</body>
</html>