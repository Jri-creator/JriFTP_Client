<?php
session_start();
// FTP Configuration
$ftp_connected = isset($_SESSION['ftp_connection']) && $_SESSION['ftp_connection'];
$current_directory = isset($_SESSION['current_directory']) ? $_SESSION['current_directory'] : '/';
// Handle FTP operations
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'connect':
                $host = $_POST['host'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                $port = !empty($_POST['port']) ? intval($_POST['port']) : 21;
                $ftp_conn = ftp_connect($host, $port);
                if ($ftp_conn && ftp_login($ftp_conn, $username, $password)) {
                    ftp_pasv($ftp_conn, true);
                    $_SESSION['ftp_connection'] = true;
                    $_SESSION['ftp_host'] = $host;
                    $_SESSION['ftp_username'] = $username;
                    $_SESSION['ftp_password'] = $password;
                    $_SESSION['ftp_port'] = $port;
                    $_SESSION['current_directory'] = '/';
                    $message = "Connected successfully!";
                    $ftp_connected = true;
                } else {
                    $error = "Failed to connect to FTP server.";
                }
                break;
            case 'disconnect':
                session_destroy();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
            case 'change_directory':
                if ($ftp_connected) {
                    $_SESSION['current_directory'] = $_POST['directory'];
                }
                break;
            case 'delete_file':
                if ($ftp_connected) {
                    $ftp_conn = ftp_connect($_SESSION['ftp_host'], $_SESSION['ftp_port']);
                    ftp_login($ftp_conn, $_SESSION['ftp_username'], $_SESSION['ftp_password']);
                    ftp_pasv($ftp_conn, true);
                    $file_path = $_SESSION['current_directory'] . '/' . $_POST['filename'];
                    if (ftp_delete($ftp_conn, $file_path)) {
                        $message = "File deleted successfully!";
                    } else {
                        $error = "Failed to delete file.";
                    }
                    ftp_close($ftp_conn);
                }
                break;
            case 'upload_file':
                if ($ftp_connected && isset($_FILES['file'])) {
                    $ftp_conn = ftp_connect($_SESSION['ftp_host'], $_SESSION['ftp_port']);
                    ftp_login($ftp_conn, $_SESSION['ftp_username'], $_SESSION['ftp_password']);
                    ftp_pasv($ftp_conn, true);
                    $local_file = $_FILES['file']['tmp_name'];
                    $remote_file = $_SESSION['current_directory'] . '/' . $_FILES['file']['name'];
                    if (ftp_put($ftp_conn, $remote_file, $local_file, FTP_BINARY)) {
                        $message = "File uploaded successfully!";
                    } else {
                        $error = "Failed to upload file.";
                    }
                    ftp_close($ftp_conn);
                }
                break;
        }
    }
}
// Get directory listing
$files = array();
if ($ftp_connected) {
    $ftp_conn = ftp_connect($_SESSION['ftp_host'], $_SESSION['ftp_port']);
    ftp_login($ftp_conn, $_SESSION['ftp_username'], $_SESSION['ftp_password']);
    ftp_pasv($ftp_conn, true);
    $current_dir = $_SESSION['current_directory'];
    if (substr($current_dir, -1) !== '/') {
        $current_dir .= '/';
    }
    $files_list = ftp_rawlist($ftp_conn, $current_dir);
    if ($files_list) {
        foreach ($files_list as $file) {
            if (trim($file) == '') continue;
            // Parse the directory listing line
            $parts = preg_split('/\s+/', $file, 9);
            if (count($parts) < 9) continue;
            $permissions = $parts[0];
            $size = intval($parts[4]);
            $file_name = $parts[8];
            // Skip current and parent directory entries
            if ($file_name == '.' || $file_name == '..') continue;
            $is_dir = (substr($permissions, 0, 1) == 'd');
            $files[] = array(
                'name' => $file_name,
                'size' => $is_dir ? '-' : formatBytes($size),
                'is_directory' => $is_dir,
                'permissions' => $permissions,
                'raw_size' => $size
            );
        }
    }
    ftp_close($ftp_conn);
}
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JriFTP Client</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/theme/dracula.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/theme/solarized.min.css">
    <style>
:root {
    --bg-color: #f8f9fa;
    --text-color: #333333;
    --header-bg: #ffffff;
    --sidebar-bg: #ffffff;
    --file-item-hover: #f1f3f5;
    --border-color: #dee2e6;
    --primary-color: #667eea;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --file-item-bg: #ffffff;
    --file-item-border: #dee2e6;
    --editor-theme: default;
}
[data-theme="dark"] {
    --bg-color: #121212;
    --text-color: #e0e0e0;
    --header-bg: #1e1e1e;
    --sidebar-bg: #1e1e1e;
    --file-item-hover: #2d2d2d;
    --border-color: #444;
    --primary-color: #7c8ff5;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --file-item-bg: #1e1e1e;
    --file-item-border: #444;
    --editor-theme: dracula;
}
[data-theme="jri-default"] {
    --bg-color: #ffffff;
    --text-color: #333333;
    --header-bg: #ffffff;
    --sidebar-bg: #f8f9fa;
    --file-item-hover: #e9ecef;
    --border-color: #dee2e6;
    --primary-color: #007bff;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --file-item-bg: #ffffff;
    --file-item-border: #dee2e6;
    --editor-theme: default;
}
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
}
[data-theme="jri-default"] body {
    background: linear-gradient(135deg, #74b9ff, #81ecec);
}
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.header {
    background: var(--header-bg);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.header h1 {
    color: var(--text-color);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.connection-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}
.form-group {
    display: flex;
    flex-direction: column;
}
.form-group label {
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--text-color);
}
.form-group input {
    padding: 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
    background-color: var(--header-bg);
    color: var(--text-color);
}
.form-group input:focus {
    outline: none;
    border-color: var(--primary-color);
}
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    font-size: 14px;
}
.btn-primary {
    background: var(--primary-color);
    color: white;
}
.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}
.btn-danger {
    background: var(--danger-color);
    color: white;
}
.btn-danger:hover {
    opacity: 0.9;
}
.btn-success {
    background: var(--success-color);
    color: white;
}
.btn-success:hover {
    opacity: 0.9;
}
.btn-warning {
    background: var(--warning-color);
    color: white;
}
.btn-warning:hover {
    opacity: 0.9;
}
.main-content {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 20px;
}
.file-browser {
    background: var(--header-bg);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.file-browser h2 {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-color);
}
.breadcrumb {
    background: var(--sidebar-bg);
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-family: monospace;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-color);
    border: 1px solid var(--border-color);
}
.file-upload {
    margin-bottom: 20px;
    padding: 15px;
    background: var(--sidebar-bg);
    border-radius: 8px;
    border: 2px dashed var(--border-color);
}
.file-list {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}
.file-item {
    display: grid;
    grid-template-columns: 40px 1fr 80px 120px;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s;
    gap: 10px;
    color: var(--text-color);
    background-color: var(--file-item-bg);
    cursor: pointer;
}
.file-item:hover {
    background: var(--file-item-hover);
}
.file-item:last-child {
    border-bottom: none;
}
.file-icon {
    font-size: 18px;
    text-align: center;
    color: var(--primary-color);
}
.file-name {
    font-weight: 500;
    cursor: pointer;
}
.file-name:hover {
    color: var(--primary-color);
}
.file-size {
    font-size: 12px;
    color: var(--text-color);
    text-align: right;
}
.file-actions {
    display: flex;
    gap: 5px;
}
.file-actions button {
    padding: 4px 8px;
    font-size: 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}
.sidebar {
    background: var(--sidebar-bg);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    height: fit-content;
}
.connection-status {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.status-connected {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(40, 167, 69, 0.3);
}
.status-disconnected {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(220, 53, 69, 0.3);
}
.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(40, 167, 69, 0.3);
}
.alert-error {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(220, 53, 69, 0.3);
}
.file-editor {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: var(--bg-color);
    z-index: 1000;
    display: none;
    flex-direction: column;
}
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 1001;
    display: none;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: var(--header-bg);
    border-radius: 12px;
    padding: 20px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease-out;
    color: var(--text-color);
}
@keyframes modalSlideIn {
    from {
        transform: scale(0.8) translateY(20px);
        opacity: 0;
    }
    to {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}
.modal-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}
.modal-header h3 {
    margin: 0;
    color: var(--text-color);
}
.modal-body {
    margin-bottom: 20px;
}
.modal-body p {
    margin-bottom: 15px;
    line-height: 1.5;
    white-space: pre-line;
}
.modal-body input, .modal-body select {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 14px;
    margin-top: 10px;
    background-color: var(--header-bg);
    color: var(--text-color);
}
.modal-body input:focus, .modal-body select:focus {
    outline: none;
    border-color: var(--primary-color);
}
.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.modal-info .modal-header {
    color: var(--info-color);
}
.modal-success .modal-header {
    color: var(--success-color);
}
.modal-error .modal-header {
    color: var(--danger-color);
}
.modal-warning .modal-header {
    color: var(--warning-color);
}
.editor-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    background: var(--header-bg);
}
.editor-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    color: var(--text-color);
}
.editor-content {
    flex: 1;
    overflow: auto;
}
.editor-textarea {
    width: 100%;
    height: 100%;
    border: none;
    resize: none;
}
.editor-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    flex-shrink: 0;
    background: var(--header-bg);
}
.CodeMirror {
    height: 100%;
    border: none;
    font-size: 14px;
}
.CodeMirror-scroll {
    overflow: auto;
    height: 100%;
}
.layout-toggle {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}
.layout-toggle button {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    background: var(--header-bg);
    color: var(--text-color);
    border-radius: 4px;
    cursor: pointer;
}
.layout-toggle button.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}
.search-bar {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
}
.search-bar input {
    flex: 1;
    padding: 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background-color: var(--header-bg);
    color: var(--text-color);
}
.search-bar button {
    padding: 12px 20px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}
.file-list.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}
.file-list.grid .file-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 15px;
    border: 1px solid var(--file-item-border);
    border-radius: 8px;
    grid-template-columns: none;
    background-color: var(--file-item-bg);
}
.file-list.grid .file-icon {
    font-size: 40px;
    margin-bottom: 10px;
}
.file-list.grid .file-name {
    word-break: break-all;
}
.file-list.grid .file-size {
    font-size: 12px;
    margin-top: 5px;
}
.file-list.grid .file-actions {
    margin-top: 10px;
    display: flex;
    gap: 5px;
    justify-content: center;
}
#colorPreview, #bgColorPreview {
    display: inline-block;
    width: 30px;
    height: 30px;
    border-radius: 4px;
    margin-left: 10px;
    vertical-align: middle;
    border: 1px solid var(--border-color);
}
@media (max-width: 768px) {
    .main-content {
        grid-template-columns: 1fr;
    }
    .connection-form {
        grid-template-columns: 1fr;
    }
    .file-item {
        grid-template-columns: 40px 1fr 60px;
        gap: 5px;
    }
    .file-size {
        display: none;
    }
    .file-list.grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
}
    </style>
</head>
<body>
    <div class="container" id="mainContainer">
        <div class="header">
            <h1><i class="fas fa-server"></i> JriFTP Client</h1>
            <?php if (isset($message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if (!$ftp_connected): ?>
                <form method="POST" class="connection-form">
                    <input type="hidden" name="action" value="connect">
                    <div class="form-group">
                        <label>Host</label>
                        <input type="text" name="host" value="ftpupload.net" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Port (optional)</label>
                        <input type="number" name="port" value="21">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plug"></i> Connect
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($ftp_connected): ?>
            <div class="main-content">
                <div class="file-browser">
                    <h2><i class="fas fa-folder-open"></i> JriFTP File Browser</h2>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i>
                        Current Directory: <?php echo htmlspecialchars($_SESSION['current_directory']); ?>
                    </div>
                    <div class="layout-toggle">
                        <button id="listLayout" class="active" onclick="setLayout('list')">
                            <i class="fas fa-list"></i> List
                        </button>
                        <button id="gridLayout" onclick="setLayout('grid')">
                            <i class="fas fa-th-large"></i> Grid
                        </button>
                    </div>
                    <div class="search-bar">
                        <input type="text" id="searchInput" placeholder="Search files...">
                        <button onclick="searchFiles()">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <div class="file-upload">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center;">
                                    <input type="hidden" name="action" value="upload_file">
                                    <input type="file" name="file" required style="flex: 1;">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-upload"></i> Upload
                                    </button>
                                </form>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button onclick="createNewFile()" class="btn btn-primary" style="flex: 1;">
                                    <i class="fas fa-file-medical"></i> New File
                                </button>
                                <button onclick="createNewDirectory()" class="btn btn-primary" style="flex: 1;">
                                    <i class="fas fa-folder-plus"></i> New Folder
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="file-list" id="fileList">
                        <?php if ($_SESSION['current_directory'] != '/'): ?>
                            <div class="file-item" onclick="changeDirectory('<?php echo dirname($_SESSION['current_directory']); ?>')">
                                <div class="file-icon">
                                    <i class="fas fa-level-up-alt" style="color: var(--primary-color);"></i>
                                </div>
                                <div class="file-name">
                                    .. (Parent Directory)
                                </div>
                                <div class="file-size">-</div>
                                <div class="file-actions"></div>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($files as $file): ?>
                            <div class="file-item" onclick="<?php echo $file['is_directory'] ? "changeDirectory('" . rtrim($_SESSION['current_directory'], '/') . '/' . $file['name'] . "')" : "getFileInfo('" . addslashes($file['name']) . "')"; ?>">
                                <div class="file-icon">
                                    <?php if ($file['is_directory']): ?>
                                        <i class="fas fa-folder" style="color: #f6ad55;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-file" style="color: #4299e1;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="file-name">
                                    <?php echo htmlspecialchars($file['name']); ?>
                                </div>
                                <div class="file-size"><?php echo $file['size']; ?></div>
                                <div class="file-actions">
                                    <?php if (!$file['is_directory']): ?>
                                        <button onclick="event.stopPropagation(); editFile('<?php echo addslashes($file['name']); ?>')" class="btn-warning" title="Edit" style="background: var(--warning-color); color: white; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="event.stopPropagation(); downloadFile('<?php echo addslashes($file['name']); ?>')" class="btn-success" title="Download" style="background: var(--success-color); color: white; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer;">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="event.stopPropagation(); renameItem('<?php echo addslashes($file['name']); ?>')" class="btn-primary" title="Rename" style="background: var(--primary-color); color: white; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer;">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="event.stopPropagation(); event.preventDefault(); confirmDelete('<?php echo addslashes($file['name']); ?>', <?php echo $file['is_directory'] ? 'true' : 'false'; ?>); return false;">
                                        <input type="hidden" name="action" value="delete_file">
                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                        <button type="submit" title="Delete" style="background: var(--danger-color); color: white; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sidebar">
                    <form method="POST">
                        <input type="hidden" name="action" value="disconnect">
                        <button type="submit" class="btn btn-danger" style="width: 100%;">
                            <i class="fas fa-sign-out-alt"></i> Disconnect
                        </button>
                    </form>
                    <div style="margin-top: 30px;">
                        <h3 style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-info-circle"></i> Connection Info
                        </h3>
                        <div style="background: var(--sidebar-bg); padding: 15px; border-radius: 8px; font-size: 14px; border: 1px solid var(--border-color);">
                            <div><strong>Host:</strong> <?php echo htmlspecialchars($_SESSION['ftp_host']); ?></div>
                            <div><strong>User:</strong> <?php echo htmlspecialchars($_SESSION['ftp_username']); ?></div>
                            <div><strong>Port:</strong> <?php echo htmlspecialchars($_SESSION['ftp_port']); ?></div>
                        </div>
                    </div>
                    <div style="margin-top: 30px;">
                        <h3 style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-keyboard"></i> Quick Actions
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <button onclick="location.reload()" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                            <button onclick="goToRoot()" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-home"></i> Go to Root
                            </button>
                            <button onclick="openSettings()" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-cog"></i> Settings
                            </button>
                        </div>
                    </div>
                    <div style="margin-top: 30px;">
                        <div class="connection-status status-connected">
                            <i class="fas fa-smile-beam"></i>
                            JriFTP - A new WebFTP File Manager
                        </div>
                        <p>Jri developed this to be a better FTP client than the one that iFastNet provided at the time, but iFastNet has improved its File Manager. This will remain as Jri's new File Manager.</p>
                        <br>
                        <p>This File Manager is a work in progress. Report bugs to <b>Jri-creator at JriMail</b>.</p>
                        <br>
                        <p><b>Want to improve on this yourself? Check it out on GitHub: https://github.com/Jri-creator/JriFTP_Client</b></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="sidebar" style="max-width: 600px; margin: 0 auto;">
                <div class="connection-status status-connected">
                    <i class="fas fa-smile-beam"></i>
                    JriFTP - A new WebFTP File Manager
                </div>
                <p>Jri developed this to be a better FTP client than the one that iFastNet provided at the time, but iFastNet has improved its File Manager. This will remain as Jri's new File Manager.</p>
                <br>
                <p>This File Manager is a work in progress. Report bugs to <b>Jri-creator at JriMail</b>.</p>
                <br>
                <p><b>Want to improve on this yourself? Check it out on GitHub: https://github.com/Jri-creator/JriFTP_Client</b></p>
            </div>
        <?php endif; ?>
    </div>
    <!-- File Editor - Fullscreen -->
    <div id="fileEditor" class="file-editor">
        <div class="editor-header">
            <h3>
                <i class="fas fa-edit"></i>
                <span id="editorTitle">Edit File</span>
            </h3>
            <div style="display: flex; gap: 10px;">
                <button onclick="saveFile()" class="btn btn-success">
                    <i class="fas fa-save"></i> Save File
                </button>
                <button onclick="closeEditor()" class="btn btn-danger">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
        <div class="editor-content">
            <textarea id="editorTextarea" class="editor-textarea" placeholder="Loading file content..."></textarea>
        </div>
    </div>
    <!-- Custom Modals -->
    <div id="customModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i id="modalIcon" class="fas fa-info-circle"></i>
                <h3 id="modalTitle">Information</h3>
            </div>
            <div class="modal-body">
                <p id="modalMessage"></p>
                <input type="text" id="modalInput" style="display: none;" placeholder="Enter value...">
            </div>
            <div class="modal-footer">
                <button id="modalCancel" class="btn" style="background: #718096; color: white; display: none;">Cancel</button>
                <button id="modalConfirm" class="btn btn-primary">OK</button>
            </div>
        </div>
    </div>
    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-cog"></i>
                <h3>Settings</h3>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Theme</label>
                    <select id="themeSelect" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background-color: var(--header-bg); color: var(--text-color);">
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                        <option value="jri-default">Jri Default</option>
                        <option value="custom">Custom Color</option>
                    </select>
                </div>
                <div id="customColorDiv" style="display: none; margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Primary Color</label>
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <input type="color" id="primaryColor" value="#667eea" style="width: 50px; height: 50px; border: none; cursor: pointer;">
                        <span id="colorPreview" style="display: inline-block; width: 30px; height: 30px; border-radius: 4px; margin-left: 10px; vertical-align: middle; border: 1px solid var(--border-color); background-color: #667eea;"></span>
                    </div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Background Color</label>
                    <div style="display: flex; align-items: center;">
                        <input type="color" id="backgroundColor" value="#f8f9fa" style="width: 50px; height: 50px; border: none; cursor: pointer;">
                        <span id="bgColorPreview" style="display: inline-block; width: 30px; height: 30px; border-radius: 4px; margin-left: 10px; vertical-align: middle; border: 1px solid var(--border-color); background-color: #f8f9fa;"></span>
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Code Editor Theme</label>
                    <select id="editorThemeSelect" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background-color: var(--header-bg); color: var(--text-color);">
                        <option value="default">Default</option>
                        <option value="dracula">Dracula</option>
                        <option value="solarized">Solarized</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="saveSettings()" class="btn btn-primary">Save</button>
                <button onclick="hideSettingsModal()" class="btn" style="background: #718096; color: white;">Cancel</button>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/search/searchcursor.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/search/search.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/dialog/dialog.min.js"></script>
    <script>
        let currentEditingFile = '';
        let modalResolve = null;
        let modalReject = null;
        let deleteForm = null;
        let editor = null;
        // Initialize CodeMirror
        document.addEventListener('DOMContentLoaded', () => {
            editor = CodeMirror.fromTextArea(document.getElementById('editorTextarea'), {
                mode: 'text/x-php',
                lineNumbers: true,
                theme: 'default',
                extraKeys: {
                    "Ctrl-S": () => saveFile(),
                    "Cmd-S": () => saveFile(),
                    "Ctrl-F": "findPersistent",
                    "Cmd-F": "findPersistent",
                },
            });
            // Load settings
            loadSettings();
            // Update color previews
            document.getElementById('primaryColor').addEventListener('input', function() {
                document.getElementById('colorPreview').style.backgroundColor = this.value;
            });
            document.getElementById('backgroundColor').addEventListener('input', function() {
                document.getElementById('bgColorPreview').style.backgroundColor = this.value;
            });
            // Show/hide custom color picker
            document.getElementById('themeSelect').addEventListener('change', function() {
                document.getElementById('customColorDiv').style.display = this.value === 'custom' ? 'block' : 'none';
            });
        });
        function changeDirectory(directory) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="change_directory">
                <input type="hidden" name="directory" value="${directory}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        // Custom Modal Functions
        function showModal(title, message, type = 'info', showInput = false, inputValue = '', showCancel = false) {
            return new Promise((resolve, reject) => {
                modalResolve = resolve;
                modalReject = reject;
                const modal = document.getElementById('customModal');
                const modalContent = modal.querySelector('.modal-content');
                const modalIcon = document.getElementById('modalIcon');
                const modalTitle = document.getElementById('modalTitle');
                const modalMessage = document.getElementById('modalMessage');
                const modalInput = document.getElementById('modalInput');
                const modalCancel = document.getElementById('modalCancel');
                const modalConfirm = document.getElementById('modalConfirm');
                // Reset classes
                modalContent.className = 'modal-content modal-' + type;
                // Set icon based on type
                const icons = {
                    'info': 'fas fa-info-circle',
                    'success': 'fas fa-check-circle',
                    'error': 'fas fa-exclamation-circle',
                    'warning': 'fas fa-exclamation-triangle',
                    'question': 'fas fa-question-circle'
                };
                modalIcon.className = icons[type] || icons.info;
                modalTitle.textContent = title;
                modalMessage.textContent = message;
                if (showInput) {
                    modalInput.style.display = 'block';
                    modalInput.value = inputValue;
                    modalInput.focus();
                } else {
                    modalInput.style.display = 'none';
                }
                modalCancel.style.display = showCancel ? 'block' : 'none';
                modalConfirm.textContent = showCancel ? 'Confirm' : 'OK';
                modal.style.display = 'flex';
            });
        }
        function hideModal() {
            document.getElementById('customModal').style.display = 'none';
        }
        // Modal event handlers
        document.getElementById('modalConfirm').onclick = function() {
            const input = document.getElementById('modalInput');
            const result = input.style.display === 'none' ? true : input.value;
            hideModal();
            if (modalResolve) modalResolve(result);

            // Submit the delete form if it exists
            if (deleteForm) {
                deleteForm.submit();
                deleteForm = null;
            }
        };
        document.getElementById('modalCancel').onclick = function() {
            hideModal();
            if (modalReject) modalReject(false);
            deleteForm = null;
            event.stopPropagation();
        };
        // Handle Enter and Escape keys in modal
        document.getElementById('modalInput').onkeydown = function(e) {
            if (e.key === 'Enter') {
                document.getElementById('modalConfirm').click();
            } else if (e.key === 'Escape') {
                // Only close if there's a cancel button visible
                const cancelBtn = document.getElementById('modalCancel');
                if (cancelBtn.style.display !== 'none') {
                    document.getElementById('modalCancel').click();
                }
            }
        };
        // Custom confirm function for delete
        function confirmDelete(filename, isDirectory) {
            event.stopPropagation();
            event.preventDefault();
            const itemType = isDirectory ? 'directory' : 'file';
            deleteForm = event.target.closest('form');
            showModal(
                'Confirm Delete',
                `Are you sure you want to delete this ${itemType}: "${filename}"?`,
                'warning',
                false,
                '',
                true
            );
            return false;
        }
        function editFile(filename) {
            currentEditingFile = filename;
            document.getElementById('editorTitle').textContent = 'Edit File: ' + filename;
            document.getElementById('fileEditor').style.display = 'flex';
            document.getElementById('mainContainer').style.display = 'none';
            editor.setValue('Loading file content...');
            // Load file content via AJAX
            fetch('file_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=load_file&filename=' + encodeURIComponent(filename)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.text();
            })
            .then(content => {
                editor.setValue(content);
                editor.refresh();
            })
            .catch(error => {
                console.error('Error loading file:', error);
                editor.setValue('Error loading file: ' + error.message);
                showModal('Error', 'Error loading file: ' + error.message, 'error');
            });
        }
        function closeEditor() {
            document.getElementById('fileEditor').style.display = 'none';
            document.getElementById('mainContainer').style.display = 'block';
            currentEditingFile = '';
        }
        function saveFile() {
            if (!currentEditingFile) return;
            const content = editor.getValue();
            const formData = new FormData();
            formData.append('action', 'save_file');
            formData.append('filename', currentEditingFile);
            formData.append('content', content);
            fetch('file_operations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(errorText => {
                        throw new Error(`HTTP ${response.status}: ${errorText}`);
                    });
                }
                return response.text();
            })
            .then(result => {
                if (result.trim() === 'success') {
                    showModal('Success', 'File saved successfully!', 'success');
                } else {
                    showModal('Error', 'Error saving file: ' + result, 'error');
                }
            })
            .catch(error => {
                console.error('Error saving file:', error);
                showModal('Error', 'Error saving file: ' + error.message, 'error');
            });
        }
        async function createNewFile() {
            try {
                const filename = await showModal('Create New File', 'Enter new file name:', 'question', true, '', true);
                if (filename && filename.trim()) {
                    const response = await fetch('file_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=create_file&filename=' + encodeURIComponent(filename.trim())
                    });
                    const result = await response.text();
                    if (result.trim() === 'success') {
                        await showModal('Success', 'File created successfully!', 'success');
                        location.reload();
                    } else {
                        showModal('Error', 'Error creating file: ' + result, 'error');
                    }
                }
            } catch (cancelled) {
                // User cancelled, do nothing
            }
        }
        async function createNewDirectory() {
            try {
                const dirname = await showModal('Create New Directory', 'Enter new directory name:', 'question', true, '', true);
                if (dirname && dirname.trim()) {
                    const response = await fetch('file_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=create_directory&dirname=' + encodeURIComponent(dirname.trim())
                    });
                    const result = await response.text();
                    if (result.trim() === 'success') {
                        await showModal('Success', 'Directory created successfully!', 'success');
                        location.reload();
                    } else {
                        showModal('Error', 'Error creating directory: ' + result, 'error');
                    }
                }
            } catch (cancelled) {
                // User cancelled, do nothing
            }
        }
        async function renameItem(currentName) {
            try {
                const newName = await showModal('Rename Item', 'Enter new name:', 'question', true, currentName, true);
                if (newName && newName.trim() && newName.trim() !== currentName) {
                    const response = await fetch('file_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=rename_file&old_name=' + encodeURIComponent(currentName) + '&new_name=' + encodeURIComponent(newName.trim())
                    });
                    const result = await response.text();
                    if (result.trim() === 'success') {
                        await showModal('Success', 'Item renamed successfully!', 'success');
                        location.reload();
                    } else {
                        showModal('Error', 'Error renaming item: ' + result, 'error');
                    }
                }
            } catch (cancelled) {
                // User cancelled, do nothing
            }
        }
        function downloadFile(filename) {
            // Create a form to trigger file download
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'file_operations.php';
            form.innerHTML = `
                <input type="hidden" name="action" value="download_file">
                <input type="hidden" name="filename" value="${filename}">
            `;
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        function getFileInfo(filename) {
            fetch('file_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_file_info&filename=' + encodeURIComponent(filename)
            })
            .then(response => response.json())
            .then(info => {
                if (info.error) {
                    showModal('Error', 'Error getting file info: ' + info.error, 'error');
                } else {
                    const message = `File: ${filename}\nSize: ${info.size >= 0 ? formatBytes(info.size) : 'Unknown'}\nModified: ${info.modified}\nType: ${info.is_directory ? 'Directory' : 'File'}`;
                    showModal('File Information', message, 'info');
                }
            })
            .catch(error => {
                showModal('Error', 'Error getting file info: ' + error.message, 'error');
            });
        }
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        function goToRoot() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="change_directory"><input type="hidden" name="directory" value="/">';
            document.body.appendChild(form);
            form.submit();
        }
        function setLayout(layout) {
            const fileList = document.getElementById('fileList');
            const listBtn = document.getElementById('listLayout');
            const gridBtn = document.getElementById('gridLayout');
            if (layout === 'grid') {
                fileList.classList.add('grid');
                listBtn.classList.remove('active');
                gridBtn.classList.add('active');
                localStorage.setItem('jriFTPLayout', 'grid');
            } else {
                fileList.classList.remove('grid');
                listBtn.classList.add('active');
                gridBtn.classList.remove('active');
                localStorage.setItem('jriFTPLayout', 'list');
            }
        }
        function searchFiles() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const fileItems = document.querySelectorAll('.file-item');
            fileItems.forEach(item => {
                const fileName = item.querySelector('.file-name').textContent.toLowerCase();
                item.style.display = fileName.includes(searchTerm) ? '' : 'none';
            });
        }
        function openSettings() {
            document.getElementById('settingsModal').style.display = 'flex';
            const theme = localStorage.getItem('jriFTPTheme') || 'light';
            document.getElementById('themeSelect').value = theme;
            if (theme === 'custom') {
                document.getElementById('customColorDiv').style.display = 'block';
                const savedPrimaryColor = localStorage.getItem('jriFTPPrimaryColor') || '#667eea';
                const savedBgColor = localStorage.getItem('jriFTPBgColor') || '#f8f9fa';
                document.getElementById('primaryColor').value = savedPrimaryColor;
                document.getElementById('backgroundColor').value = savedBgColor;
                document.getElementById('colorPreview').style.backgroundColor = savedPrimaryColor;
                document.getElementById('bgColorPreview').style.backgroundColor = savedBgColor;
            }
            const savedEditorTheme = localStorage.getItem('jriFTPEditorTheme') || 'default';
            document.getElementById('editorThemeSelect').value = savedEditorTheme;
        }
        function hideSettingsModal() {
            document.getElementById('settingsModal').style.display = 'none';
        }
        function saveSettings() {
            const theme = document.getElementById('themeSelect').value;
            const primaryColor = document.getElementById('primaryColor').value;
            const backgroundColor = document.getElementById('backgroundColor').value;
            const editorTheme = document.getElementById('editorThemeSelect').value;
            document.documentElement.setAttribute('data-theme', theme);
            if (theme === 'custom') {
                document.documentElement.style.setProperty('--primary-color', primaryColor);
                document.documentElement.style.setProperty('--bg-color', backgroundColor);
                localStorage.setItem('jriFTPPrimaryColor', primaryColor);
                localStorage.setItem('jriFTPBgColor', backgroundColor);
            } else {
                document.documentElement.style.removeProperty('--primary-color');
                document.documentElement.style.removeProperty('--bg-color');
            }
            localStorage.setItem('jriFTPTheme', theme);
            localStorage.setItem('jriFTPEditorTheme', editorTheme);
            // Update CodeMirror theme
            editor.setOption('theme', editorTheme);
            hideSettingsModal();
        }
        function loadSettings() {
            const savedTheme = localStorage.getItem('jriFTPTheme') || 'light';
            const savedLayout = localStorage.getItem('jriFTPLayout') || 'list';
            const savedPrimaryColor = localStorage.getItem('jriFTPPrimaryColor') || '#667eea';
            const savedBgColor = localStorage.getItem('jriFTPBgColor') || '#f8f9fa';
            const savedEditorTheme = localStorage.getItem('jriFTPEditorTheme') || 'default';
            document.documentElement.setAttribute('data-theme', savedTheme);
            if (savedTheme === 'custom') {
                document.documentElement.style.setProperty('--primary-color', savedPrimaryColor);
                document.documentElement.style.setProperty('--bg-color', savedBgColor);
            }
            document.getElementById('themeSelect').value = savedTheme;
            if (savedTheme === 'custom') {
                document.getElementById('customColorDiv').style.display = 'block';
                document.getElementById('primaryColor').value = savedPrimaryColor;
                document.getElementById('backgroundColor').value = savedBgColor;
                document.getElementById('colorPreview').style.backgroundColor = savedPrimaryColor;
                document.getElementById('bgColorPreview').style.backgroundColor = savedBgColor;
            }
            if (savedLayout === 'grid') {
                setLayout('grid');
            } else {
                setLayout('list');
            }
            // Set CodeMirror theme
            editor.setOption('theme', savedEditorTheme);
        }
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('fileEditor').style.display === 'flex') {
                    closeEditor();
                } else if (document.getElementById('customModal').style.display === 'flex') {
                    // Only close modal on escape if there's a cancel button visible
                    const cancelBtn = document.getElementById('modalCancel');
                    if (cancelBtn.style.display !== 'none') {
                        document.getElementById('modalCancel').click();
                    }
                }
            }
            if (e.ctrlKey && e.key === 's' && document.getElementById('fileEditor').style.display === 'flex') {
                e.preventDefault();
                saveFile();
            }
            if (e.ctrlKey && e.key === 'f' && document.getElementById('fileEditor').style.display === 'flex') {
                e.preventDefault();
                editor.execCommand('findPersistent');
            }
        });
    </script>
</body>
</html>
