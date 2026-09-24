<?php
// ==========================================
// 1. إعدادات الحماية والباسورد
// ==========================================
$access_pass = "123456"; 

if (!isset($_GET['p']) || $_GET['p'] !== $access_pass) {
    die('<div style="color:#ef4444; font-family:tahoma; text-align:center; margin-top:50px; background:#1e1e28; padding:30px; border-radius:10px; max-width:400px; margin:50px auto;">
            <h1>⛔ دخول محظور</h1>
            <p>كلمة المرور في الرابط غير صحيحة.</p>
         </div>');
}

// ==========================================
// 2. طلبات AJAX الخلفية
// ==========================================

// أ) التحقق مما إذا كان اسم الملف موجوداً مسبقاً
if (isset($_GET['action']) && $_GET['action'] === 'check_file') {
    header('Content-Type: application/json');
    $filename = trim($_GET['filename'] ?? '');
    $exists = false;
    if (!empty($filename)) {
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $filename .= '.html';
        }
        if (file_exists($filename)) {
            $exists = true;
        }
    }
    echo json_encode(['exists' => $exists, 'filename' => $filename]);
    exit;
}

// ب) معالجة رفع الملفات عبر AJAX ورجوع الأسماء للإشعار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_upload') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'uploaded_files' => []];

    $is_unzip = isset($_POST['unzip']) && $_POST['unzip'] === '1';
    $custom_names = $_POST['custom_names'] ?? [];
    $uploaded_list = [];

    if (!empty($_FILES['files']['name'][0])) {
        foreach ($_FILES['files']['name'] as $i => $orig_name) {
            $tmp_name = $_FILES['files']['tmp_name'][$i];
            $filename = !empty($custom_names[$i]) ? trim($custom_names[$i]) : $orig_name;

            if ($is_unzip && strtolower(pathinfo($orig_name, PATHINFO_EXTENSION)) === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($tmp_name) === TRUE) {
                    $zip->extractTo('./');
                    $zip->close();
                    $uploaded_list[] = $filename;
                    $response['message'] = "تم رفع وفك ضغط ملف ZIP بنجاح!";
                } else {
                    $response['message'] = "فشل في فك ضغط ملف ZIP.";
                }
            } else {
                if (move_uploaded_file($tmp_name, $filename)) {
                    $uploaded_list[] = $filename;
                }
            }
        }
        $response['success'] = true;
        $response['uploaded_files'] = $uploaded_list;
        if (!$is_unzip) {
            $response['message'] = "تم رفع الملفات بنجاح!";
        }
    } else {
        $response['message'] = "لم يتم تحديد أي ملفات للرفع.";
    }

    echo json_encode($response);
    exit;
}

// ==========================================
// 3. معالجة العمليات العادية
// ==========================================
$msg = '';

// إعادة تسمية ملف أو مجلد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename_item') {
    $old_path = $_POST['old_path'];
    $new_name = trim($_POST['new_name']);
    
    if ($old_path && $new_name && file_exists($old_path)) {
        $parent = dirname($old_path);
        $new_path = ($parent === '.' || $parent === '') ? $new_name : $parent . '/' . $new_name;
        
        if (rename($old_path, $new_path)) {
            $msg = "✅ تم تغيير الاسم بنجاح إلى ($new_name)";
        }
    }
}

// حفظ كود فردي
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_single_code') {
    $file_path = trim($_POST['file_path']);
    $code_content = $_POST['code_content'] ?? '';

    if (!empty($file_path)) {
        file_put_contents($file_path, $code_content);
        $msg = "✅ تم حفظ التعديلات على ($file_path) بنجاح!";
    }
}

// حفظ أكواد متعددة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_multi_codes') {
    $names = $_POST['code_names'] ?? [];
    $contents = $_POST['code_contents'] ?? [];
    $saved_files = [];

    for ($i = 0; $i < count($names); $i++) {
        $code_name = trim($names[$i]);
        $code_content = $contents[$i] ?? '';

        if (!empty($code_name)) {
            if (!pathinfo($code_name, PATHINFO_EXTENSION)) {
                $code_name .= '.html';
            }
            file_put_contents($code_name, $code_content);
            $saved_files[] = $code_name;
        }
    }
    if (!empty($saved_files)) {
        $links = [];
        foreach ($saved_files as $f) {
            $links[] = '<a href="' . htmlspecialchars($f) . '" target="_blank" style="color:#38bdf8; text-decoration:underline; font-weight:bold;">' . htmlspecialchars($f) . '</a>';
        }
        $msg = "✅ تم الحفظ بنجاح للملفات: " . implode(' ، ', $links);
    }
}

// حذف ملف أو مجلد
if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    if (file_exists($target)) {
        if (is_dir($target)) {
            function deleteDir($dirPath) {
                if (!is_dir($dirPath)) return;
                $files = scandir($dirPath);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $filePath = $dirPath . '/' . $file;
                        is_dir($filePath) ? deleteDir($filePath) : unlink($filePath);
                    }
                }
                rmdir($dirPath);
            }
            deleteDir($target);
            $msg = "🗑️ تم حذف المجلد ($target) ومحتوياته.";
        } else {
            unlink($target);
            $msg = "🗑️ تم حذف الملف ($target).";
        }
    }
}

// دالة عرض شجرة المجلدات والملفات (مع زر معاينة الكود)
function renderDirectoryTree($dir = '.') {
    $items = scandir($dir);
    $valid_items = array_filter($items, function($item) {
        return $item !== '.' && $item !== '..' && $item !== '.git';
    });

    if (empty($valid_items)) {
        echo '<div style="color:#64748b; font-size:12px; padding:6px 12px; font-style:italic;">📂 (مجلد فارغ)</div>';
        return;
    }

    echo '<ul class="tree-list">';
    foreach ($valid_items as $item) {
        $path = ($dir === '.') ? $item : $dir . '/' . $item;
        $access_pass = $GLOBALS['access_pass'];

        if (is_dir($path)) {
            echo '<li class="tree-item is-folder">';
            echo '<div class="folder-header">';
            echo '<span class="toggle-btn" onclick="toggleFolder(this)">📁 <b style="color:#f59e0b;">' . htmlspecialchars($item) . '</b></span>';
            echo '<div class="item-actions">';
            echo '<button class="btn btn-sm btn-outline" onclick="showRenameModal(\'' . addslashes($path) . '\', \'' . addslashes($item) . '\')">تسمية 📝</button>';
            echo '<a href="?p=' . $access_pass . '&delete=' . urlencode($path) . '" class="btn btn-sm btn-danger" onclick="return confirm(\'تأكيد حذف المجلد بالكامل؟\')">حذف 🗑️</a>';
            echo '</div>';
            echo '</div>';
            echo '<div class="folder-content" style="display:none; margin-right:12px; border-right:2px dashed #334155; padding-right:8px;">';
            renderDirectoryTree($path);
            echo '</div>';
            echo '</li>';
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $is_img = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg']);
            $icon = $is_img ? '🖼️' : (($ext === 'zip') ? '📦' : '📄');

            echo '<li class="tree-item is-file">';
            echo '<div class="file-header">';
            echo '<a href="' . htmlspecialchars($path) . '" target="_blank" class="file-name">' . $icon . ' ' . htmlspecialchars($item) . '</a>';
            echo '<div class="item-actions">';
            // إضافة زر معاينة الكود/الملف هنا
            echo '<a href="' . htmlspecialchars($path) . '" target="_blank" class="btn btn-sm btn-outline" style="color:#38bdf8; border-color:#38bdf8;">معاينة 👁️</a>';
            if (!$is_img) {
                echo '<a href="?p=' . $access_pass . '&edit=' . urlencode($path) . '#section-editor" class="btn btn-sm btn-green">تعديل ✏️</a>';
            }
            echo '<button class="btn btn-sm btn-outline" onclick="showRenameModal(\'' . addslashes($path) . '\', \'' . addslashes($item) . '\')">تسمية 📝</button>';
            echo '<a href="?p=' . $access_pass . '&delete=' . urlencode($path) . '" class="btn btn-sm btn-danger" onclick="return confirm(\'تأكيد حذف الملف؟\')">حذف 🗑️</a>';
            echo '</div>';
            echo '</div>';
            echo '</li>';
        }
    }
    echo '</ul>';
}

$current_file = $_GET['edit'] ?? '';
$file_content = '';
$is_image = false;

if ($current_file && file_exists($current_file)) {
    $ext = strtolower(pathinfo($current_file, PATHINFO_EXTENSION));
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'])) {
        $is_image = true;
    } else {
        $file_content = file_get_contents($current_file);
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>مدير الملفات والأكواد</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Fira+Code:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0b0f19;
            --bg-card: #151c2c;
            --bg-input: #000000;
            --accent-blue: #38bdf8;
            --accent-green: #22c55e;
            --accent-gold: #f59e0b;
            --accent-red: #ef4444;
            --text-main: #f1f5f9;
            --border-color: #243048;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            font-family: 'Cairo', sans-serif;
            margin: 0;
            padding: 12px;
            -webkit-tap-highlight-color: transparent;
        }

        .nav-bar {
            display: flex;
            gap: 8px;
            background: var(--bg-card);
            padding: 8px;
            border-radius: 10px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            overflow-x: auto;
            white-space: nowrap;
        }

        .nav-link {
            color: #94a3b8;
            text-decoration: none;
            background: #1e293b;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        /* شريط سجل الأسماء المقترحة المنزلق */
        .history-bar-container {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 8px 4px;
            margin-bottom: 12px;
            background: var(--bg-main);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            scrollbar-width: thin;
        }

        .history-chip {
            background: #2563eb;
            color: #ffffff;
            padding: 6px 16px;
            border-radius: 8px;
            font-family: 'Fira Code', monospace;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            border: none;
            transition: background 0.2s;
            direction: ltr;
        }

        .history-chip:active { background: #1d4ed8; }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .card-title {
            margin: 0 0 12px 0;
            color: var(--accent-blue);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 8px;
            font-family: 'Fira Code', monospace;
            font-size: 13px;
            margin-bottom: 10px;
            outline: none;
            direction: ltr;
            text-align: left;
            transition: border-color 0.2s;
        }

        .form-control:focus { border-color: var(--accent-blue); }

        textarea.form-control {
            height: 220px;
            resize: vertical;
            line-height: 1.5;
            direction: ltr;
            text-align: left;
        }

        .btn {
            background: var(--accent-blue);
            color: #000;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Cairo', sans-serif;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            transition: opacity 0.2s;
        }

        .btn:active { opacity: 0.8; }
        .btn-green { background: var(--accent-green); color: #fff; }
        .btn-gold { background: var(--accent-gold); color: #000; }
        .btn-danger { background: var(--accent-red); color: #fff; }
        .btn-outline { background: #1e293b; color: #cbd5e1; border: 1px solid var(--border-color); }
        .btn-sm { padding: 4px 8px; font-size: 11px; width: auto; border-radius: 5px; }

        .code-card-item {
            background: #0d121d;
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            position: relative;
        }

        .status-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            margin-bottom: 8px;
            display: none;
            font-weight: 600;
            direction: rtl;
            text-align: right;
        }
        .status-badge.exists { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid var(--accent-red); display: block; }
        .status-badge.new { background: rgba(34, 197, 94, 0.2); color: #86efac; border: 1px solid var(--accent-green); display: block; }

        /* شجرة المجلدات */
        .tree-list { list-style: none; padding-right: 0; margin: 0; }
        .tree-item { margin: 6px 0; font-family: 'Fira Code', 'Cairo', monospace; }
        .folder-header, .file-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-input);
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            gap: 8px;
        }

        .toggle-btn { cursor: pointer; font-size: 13px; }
        .file-name { color: var(--accent-blue); text-decoration: none; font-size: 12px; word-break: break-all; direction: ltr; }
        .item-actions { display: flex; gap: 4px; flex-shrink: 0; }

        /* شريط التقدّم للرفع */
        .progress-box {
            display: none;
            margin-top: 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
        }

        .progress-bar {
            width: 0%;
            height: 12px;
            background: linear-gradient(90deg, #38bdf8, #22c55e);
            border-radius: 6px;
            transition: width 0.2s;
        }

        /* نظام الإشعارات الداخلي المتطور */
        #toast-container {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            width: min(90%, 420px);
        }

        .toast {
            background: #1e293b;
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--accent-blue);
            box-shadow: 0 10px 25px rgba(0,0,0,0.6);
            font-size: 13px;
            text-align: center;
            margin-top: 8px;
            line-height: 1.6;
        }

        .toast a {
            color: #38bdf8;
            font-weight: bold;
            text-decoration: underline;
            margin: 0 4px;
            direction: ltr;
            display: inline-block;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }

        .modal-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 18px;
            border-radius: 12px;
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>

    <div id="toast-container"></div>

    <!-- شريط التنقل -->
    <div class="nav-bar">
        <a href="#section-codes" class="nav-link">📝 إضافة أكواد</a>
        <a href="#section-upload-direct" class="nav-link">📤 رفع ملفات مباشرة</a>
        <a href="#section-upload-zip" class="nav-link">📦 رفع ZIP</a>
        <a href="#section-tree" class="nav-link">📁 المجلدات والملفات</a>
    </div>

    <!-- شريط سجل الأسماء المقترحة (يسار/يمين) -->
    <div class="history-bar-container" id="historyBar"></div>

    <?php if ($msg): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                showToast("<?php echo addslashes($msg); ?>");
            });
        </script>
    <?php endif; ?>

    <!-- 1. قسم إضافة أكواد متعددة -->
    <div class="card" id="section-codes">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 class="card-title" style="margin:0;">📝 إضافة وحفظ أكواد برمجية</h3>
            <button type="button" class="btn btn-gold btn-sm" onclick="addCodeCard()">➕ إضافة كود آخر</button>
        </div>

        <form method="POST" onsubmit="saveEnteredNames()">
            <input type="hidden" name="action" value="save_multi_codes">
            <div id="codesContainer">
                <div class="code-card-item">
                    <div id="status_0" class="status-badge"></div>
                    <label style="font-size:12px; color:#94a3b8; display:block; margin-bottom:4px; text-align:right;">اسم الملف:</label>
                    <input type="text" name="code_names[]" class="form-control filename-input" placeholder="c21.php" onfocus="setActiveInput(this)" oninput="checkFileExists(this, 0)" required>
                    
                    <label style="font-size:12px; color:#94a3b8; display:block; margin-bottom:4px; text-align:right;">الكود:</label>
                    <textarea name="code_contents[]" class="form-control" placeholder="ضع الكود هنا..." required></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-green">حفظ / تحديث السجل 💾</button>
        </form>
    </div>

    <!-- 2. قسم رفع ملفات مباشرة -->
    <div class="card" id="section-upload-direct">
        <h3 class="card-title">📤 رفع ملفات مباشرة (تعديل الأسماء)</h3>
        <label style="font-size:12px; color:#94a3b8; display:block; margin-bottom:6px;">اختر الملفات من جهازك:</label>
        <input type="file" id="directFileInput" multiple class="form-control" style="direction:rtl;" onchange="renderUploadFilesList(this)">
        
        <form id="directUploadForm">
            <div id="selectedFilesContainer" style="display:grid; gap:8px; margin-bottom:12px;"></div>
            <button type="button" id="startDirectUploadBtn" class="btn btn-green" style="display:none;" onclick="uploadDirectFilesAjax()">📤 بدء رفع الملفات المختارة</button>
        </form>

        <div class="progress-box" id="directProgressBox">
            <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:4px;">
                <span id="directStatus">جاري الرفع...</span>
                <span id="directPercent">0%</span>
            </div>
            <div style="background:#000; border-radius:6px; overflow:hidden;">
                <div class="progress-bar" id="directProgressBar"></div>
            </div>
        </div>
    </div>

    <!-- 3. قسم رفع فك ضغط ZIP -->
    <div class="card" id="section-upload-zip">
        <h3 class="card-title">📦 رفع ملف مضغوط (ZIP) وفك ضغطه</h3>
        <form id="zipUploadForm">
            <input type="file" id="zipInput" accept=".zip" class="form-control" style="direction:rtl;" required>
            <button type="button" class="btn btn-green" onclick="uploadZipAjax()">📤 رفع وفك الضغط تلقائياً</button>
        </form>

        <div class="progress-box" id="zipProgressBox">
            <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:4px;">
                <span id="zipStatus">جاري الرفع...</span>
                <span id="zipPercent">0%</span>
            </div>
            <div style="background:#000; border-radius:6px; overflow:hidden;">
                <div class="progress-bar" id="zipProgressBar"></div>
            </div>
        </div>
    </div>

    <!-- 4. شجرة المجلدات والملفات -->
    <div class="card" id="section-tree">
        <h3 class="card-title">📁 شجرة المجلدات والملفات</h3>
        <?php renderDirectoryTree('.'); ?>
    </div>

    <!-- 5. قسم التعديل المباشر -->
    <?php if ($current_file): ?>
    <div class="card" id="section-editor">
        <h3 class="card-title">✏️ تحرير: <span style="color:#38bdf8; word-break:break-all; direction:ltr;"><?php echo htmlspecialchars($current_file); ?></span></h3>
        <?php if ($is_image): ?>
            <div style="text-align:center;">
                <img src="<?php echo htmlspecialchars($current_file); ?>" style="max-width:100%; max-height:300px; border-radius:8px;" alt="صورة">
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="save_single_code">
                <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($current_file); ?>">
                <textarea name="code_content" class="form-control" style="height:320px;" required><?php echo htmlspecialchars($file_content); ?></textarea>
                <button type="submit" class="btn btn-green" style="margin-top:8px;">💾 حفظ التعديلات</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Modal إعادة التسمية -->
    <div class="modal-overlay" id="renameModal">
        <div class="modal-box">
            <h4 style="margin-top:0; color:var(--accent-blue);">📝 إعادة تسمية</h4>
            <form method="POST">
                <input type="hidden" name="action" value="rename_item">
                <input type="hidden" name="old_path" id="modalOldPath">
                <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:4px;">الاسم الجديد:</label>
                <input type="text" name="new_name" id="modalNewName" class="form-control" required>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <button type="submit" class="btn btn-green" style="flex:1;">حفظ ✅</button>
                    <button type="button" class="btn btn-danger" onclick="closeRenameModal()" style="flex:1;">إلغاء ✕</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let cardCounter = 1;
        let activeInputEl = null;

        document.addEventListener("DOMContentLoaded", function() {
            renderHistoryChips();
            const firstInput = document.querySelector('.filename-input');
            if (firstInput) activeInputEl = firstInput;
        });

        function setActiveInput(el) {
            activeInputEl = el;
        }

        // عرض إشعار تفاعلي يدعم HTML والروابط
        function showToast(htmlContent) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = htmlContent;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 6000);
        }

        // سجل أسماء الملفات المقترحة
        function getSavedNames() {
            const saved = localStorage.getItem('code_file_history');
            return saved ? JSON.parse(saved) : ['c21.php'];
        }

        function saveEnteredNames() {
            const inputs = document.querySelectorAll('.filename-input');
            let history = getSavedNames();
            inputs.forEach(input => {
                const val = input.value.trim();
                if (val && !history.includes(val)) {
                    history.unshift(val);
                }
            });
            localStorage.setItem('code_file_history', JSON.stringify(history.slice(0, 15)));
        }

        function renderHistoryChips() {
            const bar = document.getElementById('historyBar');
            const names = getSavedNames();
            bar.innerHTML = '';
            names.forEach(name => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'history-chip';
                chip.innerText = name;
                chip.onclick = function() {
                    if (activeInputEl) {
                        activeInputEl.value = name;
                        const idx = activeInputEl.getAttribute('oninput')?.match(/\d+/)?.[0] || 0;
                        checkFileExists(activeInputEl, idx);
                    }
                };
                bar.appendChild(chip);
            });
        }

        function toggleFolder(el) {
            const content = el.parentElement.nextElementSibling;
            if (content.style.display === 'none') {
                content.style.display = 'block';
                el.innerHTML = el.innerHTML.replace('📁', '📂');
            } else {
                content.style.display = 'none';
                el.innerHTML = el.innerHTML.replace('📂', '📁');
            }
        }

        function showRenameModal(oldPath, currentName) {
            document.getElementById('modalOldPath').value = oldPath;
            document.getElementById('modalNewName').value = currentName;
            document.getElementById('renameModal').style.display = 'flex';
        }

        function closeRenameModal() {
            document.getElementById('renameModal').style.display = 'none';
        }

        function addCodeCard() {
            const container = document.getElementById('codesContainer');
            const idx = cardCounter++;
            const card = document.createElement('div');
            card.className = 'code-card-item';
            card.innerHTML = `
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()" style="position:absolute; left:8px; top:8px; width:auto;">✕ إلغاء</button>
                <div id="status_${idx}" class="status-badge"></div>
                <label style="font-size:12px; color:#94a3b8; display:block; margin-bottom:4px; text-align:right;">اسم الملف:</label>
                <input type="text" name="code_names[]" class="form-control filename-input" placeholder="اسم الملف..." onfocus="setActiveInput(this)" oninput="checkFileExists(this, ${idx})" required>
                <label style="font-size:12px; color:#94a3b8; display:block; margin-bottom:4px; text-align:right;">الكود:</label>
                <textarea name="code_contents[]" class="form-control" placeholder="ضع الكود هنا..." required></textarea>
            `;
            container.appendChild(card);
            activeInputEl = card.querySelector('.filename-input');
            activeInputEl.focus();
        }

        let timerMap = {};
        function checkFileExists(input, idx) {
            clearTimeout(timerMap[idx]);
            const val = input.value.trim();
            const badge = document.getElementById(`status_${idx}`);

            if (!val) {
                if(badge) badge.style.display = 'none';
                return;
            }

            timerMap[idx] = setTimeout(() => {
                fetch(`?p=123456&action=check_file&filename=${encodeURIComponent(val)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (badge) {
                            if (data.exists) {
                                badge.className = 'status-badge exists';
                                badge.innerText = `⚠️ تنبيه: الملف (${data.filename}) موجود مسبقاً وسوف يتم تعديله/استبداله!`;
                            } else {
                                badge.className = 'status-badge new';
                                badge.innerText = `✨ ملف جديد: (${data.filename}) غير موجود مسبقاً.`;
                            }
                        }
                    });
            }, 300);
        }

        function renderUploadFilesList(input) {
            const container = document.getElementById('selectedFilesContainer');
            const btn = document.getElementById('startDirectUploadBtn');
            container.innerHTML = '';

            if (input.files.length > 0) {
                btn.style.display = 'block';
                Array.from(input.files).forEach((file, idx) => {
                    const item = document.createElement('div');
                    item.style.cssText = 'background:var(--bg-input); padding:10px; border-radius:8px; border:1px solid var(--border-color);';
                    item.innerHTML = `
                        <label style="font-size:11px; color:#38bdf8; display:block; margin-bottom:4px; text-align:right;">اسم الملف عند الرفع:</label>
                        <input type="text" class="form-control custom-file-name" data-idx="${idx}" value="${file.name}" style="margin:0;">
                    `;
                    container.appendChild(item);
                });
            } else {
                btn.style.display = 'none';
            }
        }

        function uploadDirectFilesAjax() {
            const input = document.getElementById('directFileInput');
            if (input.files.length === 0) return;

            const formData = new FormData();
            formData.append('action', 'ajax_upload');
            formData.append('unzip', '0');

            const customInputs = document.querySelectorAll('.custom-file-name');
            Array.from(input.files).forEach((file, idx) => {
                formData.append('files[]', file);
                formData.append('custom_names[]', customInputs[idx].value);
            });

            sendAjaxUpload(formData, 'directProgressBox', 'directProgressBar', 'directPercent', 'directStatus');
        }

        function uploadZipAjax() {
            const input = document.getElementById('zipInput');
            if (input.files.length === 0) return;

            const formData = new FormData();
            formData.append('action', 'ajax_upload');
            formData.append('unzip', '1');
            formData.append('files[]', input.files[0]);

            sendAjaxUpload(formData, 'zipProgressBox', 'zipProgressBar', 'zipPercent', 'zipStatus');
        }

        function sendAjaxUpload(formData, boxId, barId, percentId, statusId) {
            const xhr = new XMLHttpRequest();
            const box = document.getElementById(boxId);
            const bar = document.getElementById(barId);
            const percent = document.getElementById(percentId);
            const status = document.getElementById(statusId);

            box.style.display = 'block';

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const done = e.loaded;
                    const total = e.total;
                    const pct = Math.round((done / total) * 100);

                    bar.style.width = pct + '%';
                    percent.innerText = pct + '%';
                    status.innerText = 'جاري الرفع... (' + Math.round(done / 1024) + ' / ' + Math.round(total / 1024) + ' KB)';
                }
            };

            xhr.onload = function() {
                if (xhr.status === 200) {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        let fileLinks = res.uploaded_files.map(f => `<a href="${f}" target="_blank">${f}</a>`).join(' ، ');
                        showToast(`🚀 تم رفع الملفات بنجاح:<br>${fileLinks}`);
                        setTimeout(() => { window.location.reload(); }, 2500);
                    } else {
                        showToast(`❌ ${res.message}`);
                    }
                } else {
                    showToast('❌ حدث خطأ أثناء الرفع!');
                }
            };

            xhr.open('POST', '?p=123456', true);
            xhr.send(formData);
        }
    </script>
</body>
</html>
