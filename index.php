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
    <title>FTP Client</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #74b9ff, #81ecec);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #333;
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
            color: #555;
        }

        .form-group input {
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
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
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-danger:hover {
            background: #c53030;
        }

        .btn-success {
            background: #38a169;
            color: white;
        }

        .btn-success:hover {
            background: #2f855a;
        }

        .btn-warning {
            background: #d69e2e;
            color: white;
        }

        .btn-warning:hover {
            background: #b7791f;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }

        .file-browser {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .file-browser h2 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .breadcrumb {
            background: #f7fafc;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-upload {
            margin-bottom: 20px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            border: 2px dashed #cbd5e0;
        }

        .file-list {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .file-item {
            display: grid;
            grid-template-columns: 40px 1fr 80px 120px;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            transition: background-color 0.2s;
            gap: 10px;
        }

        .file-item:hover {
            background: #f7fafc;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-icon {
            font-size: 18px;
            text-align: center;
        }

        .file-name {
            font-weight: 500;
            cursor: pointer;
            color: #2d3748;
        }

        .file-name:hover {
            color: #667eea;
        }

        .file-size {
            font-size: 12px;
            color: #718096;
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
            background: white;
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
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .status-disconnected {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
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
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }

        .file-editor {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
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
            background: white;
            border-radius: 12px;
            padding: 20px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
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
            color: #333;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-body p {
            margin-bottom: 15px;
            line-height: 1.5;
            white-space: pre-line;
        }

        .modal-body input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 10px;
        }

        .modal-body input:focus {
            outline: none;
            border-color: #74b9ff;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .modal-info .modal-header {
            color: #2b6cb0;
        }

        .modal-success .modal-header {
            color: #2f855a;
        }

        .modal-error .modal-header {
            color: #c53030;
        }

        .modal-warning .modal-header {
            color: #d69e2e;
        }

        .editor-modal {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 1000px;
            height: 80%;
            display: flex;
            flex-direction: column;
        }

        .editor-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .editor-content {
            flex: 1;
            padding: 20px;
        }

        .editor-textarea {
            width: 100%;
            height: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            resize: none;
        }

        .editor-footer {
            padding: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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
        }
    </style>
</head>
<body>
    <div class="container">
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

                        <div class="file-list">
                        <?php if ($_SESSION['current_directory'] != '/'): ?>
                            <div class="file-item">
                                <div class="file-icon">
                                    <i class="fas fa-level-up-alt" style="color: #667eea;"></i>
                                </div>
                                <div class="file-name">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="change_directory">
                                        <input type="hidden" name="directory" value="<?php echo dirname($_SESSION['current_directory']); ?>">
                                        <button type="submit" style="background: none; border: none; color: #667eea; cursor: pointer; font-weight: 500;">
                                            .. (Parent Directory)
                                        </button>
                                    </form>
                                </div>
                                <div class="file-size">-</div>
                                <div class="file-actions"></div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($files as $file): ?>
                            <div class="file-item">
                                <div class="file-icon">
                                    <?php if ($file['is_directory']): ?>
                                        <i class="fas fa-folder" style="color: #f6ad55;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-file" style="color: #4299e1;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="file-name">
                                    <?php if ($file['is_directory']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="change_directory">
                                            <input type="hidden" name="directory" value="<?php echo rtrim($_SESSION['current_directory'], '/') . '/' . $file['name']; ?>">
                                            <button type="submit" style="background: none; border: none; color: #2d3748; cursor: pointer; font-weight: 500;">
                                                <?php echo htmlspecialchars($file['name']); ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span onclick="getFileInfo('<?php echo addslashes($file['name']); ?>')" style="cursor: pointer;" title="Click for file info">
                                            <?php echo htmlspecialchars($file['name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="file-size"><?php echo $file['size']; ?></div>
                                <div class="file-actions">
                                    <?php if (!$file['is_directory']): ?>
                                        <button onclick="editFile('<?php echo addslashes($file['name']); ?>')" 
                                                class="btn-warning" title="Edit" style="background: #d69e2e; color: white; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="downloadFile('<?php echo addslashes($file['name']); ?>')" 
                                                class="btn-success" title="Download" style="background: #38a169; color: white; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer;">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="renameItem('<?php echo addslashes($file['name']); ?>')" 
                                            class="btn-primary" title="Rename" style="background: #667eea; color: white; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer;">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete('<?php echo addslashes($file['name']); ?>', <?php echo $file['is_directory'] ? 'true' : 'false'; ?>)">
                                        <input type="hidden" name="action" value="delete_file">
                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                        <button type="submit" title="Delete" style="background: #e53e3e; color: white; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer;">
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
                        <div style="background: #f7fafc; padding: 15px; border-radius: 8px; font-size: 14px;">
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
                        </div>
<br>
                <div class="connection-status status-connected">
                    <i class="fas fa-smile-beam"></i>
                    JriFTP - A new WebFTP File Manager
                </div>
                <p>iFastNet's current File Manager (found at https://filemanager.ai/new3/) is not great, to say the least. System emojis everywhere, many bugs, too many colors. This File Manager was birthed from the fact the current one's bad, and MonstaFTP has let us down too many times.</p>
<br>
<p>This File Manager is "Incomplete", and needs some work to become better. Right now, the File Editor is just a Text Editor, the only theme available is White (no Dark Mode available), and Mobile support isn't great. </p>
<br>
<p>We're working on fixing many of the current issues. But we also need people to test our JriFTP Client for bugs. If you find a bug or issue, please report it to <b>Jri-creator at JriMail (https://jmail.rf.gd)</b>. </p>
            </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="sidebar" style="max-width: 600px; margin: 0 auto;">
                <div class="connection-status status-connected">
                    <i class="fas fa-smile-beam"></i>
                    JriFTP - A new WebFTP File Manager
                </div>
                <p>iFastNet's current File Manager (found at https://filemanager.ai/new3/) is not great, to say the least. System emojis everywhere, many bugs, too many colors. This File Manager was birthed from the fact the current one's bad, and MonstaFTP has let us down too many times.</p>
<br>
<p>This File Manager is "Incomplete", and needs some work to become better. Right now, the File Editor is just a Text Editor, the only theme available is White (no Dark Mode available), and Mobile support isn't great. </p>
<br>
<p>We're working on fixing many of the current issues. But we also need people to test our JriFTP Client for bugs. If you find a bug or issue, please report it to <b>Jri-creator at JriMail (https://jmail.rf.gd)</b>. </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- File Editor Modal -->
    <div id="fileEditor" class="file-editor">
        <div class="editor-modal">
            <div class="editor-header">
                <h3 style="display: flex; align-items: center; gap: 10px; margin: 0;">
                    <i class="fas fa-edit"></i>
                    <span id="editorTitle">Edit File</span>
                </h3>
                <button onclick="closeEditor()" class="btn btn-danger" style="padding: 8px 12px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="editor-content">
                <textarea id="editorTextarea" class="editor-textarea" placeholder="Loading file content..."></textarea>
            </div>
            <div class="editor-footer">
                <button onclick="closeEditor()" class="btn" style="background: #718096; color: white;">
                    <i class="fas fa-times"></i> Close
                </button>
                <button onclick="saveFile()" class="btn btn-success">
                    <i class="fas fa-save"></i> Save File
                </button>
            </div>
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

    <script>
        let currentEditingFile = '';
        let modalResolve = null;
        let modalReject = null;
        let deleteForm = null;

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

                // Focus input if shown
                if (showInput) {
                    setTimeout(() => modalInput.focus(), 100);
                }
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
            
            // If this was a delete confirmation, submit the form
            if (deleteForm) {
                deleteForm.submit();
                deleteForm = null;
            }
        };

        document.getElementById('modalCancel').onclick = function() {
            hideModal();
            if (modalReject) modalReject(false);
            deleteForm = null;
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
            event.preventDefault();
            const itemType = isDirectory ? 'directory' : 'file';
            deleteForm = event.target;
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
            document.getElementById('editorTextarea').value = 'Loading file content...';
            
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
                document.getElementById('editorTextarea').value = content;
            })
            .catch(error => {
                console.error('Error loading file:', error);
                document.getElementById('editorTextarea').value = 'Error loading file: ' + error.message;
                showModal('Error', 'Error loading file: ' + error.message, 'error');
            });
        }

        function closeEditor() {
            document.getElementById('fileEditor').style.display = 'none';
            currentEditingFile = '';
        }

        function saveFile() {
            if (!currentEditingFile) return;
            
            const content = document.getElementById('editorTextarea').value;
            
            // Debug logging
            console.log('Saving file:', currentEditingFile);
            console.log('Content length:', content.length);
            
            const formData = new FormData();
            formData.append('action', 'save_file');
            formData.append('filename', currentEditingFile);
            formData.append('content', content);
            
            fetch('file_operations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', [...response.headers.entries()]);
                
                if (!response.ok) {
                    return response.text().then(errorText => {
                        throw new Error(`HTTP ${response.status}: ${errorText}`);
                    });
                }
                return response.text();
            })
            .then(result => {
                console.log('Save result:', result);
                if (result.trim() === 'success') {
                    showModal('Success', 'File saved successfully!', 'success').then(() => {
                        //closeEditor();
                    });
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

        // Close editor when clicking outside
        //document.getElementById('fileEditor').addEventListener('click', function(e) {
        //    if (e.target === this) {
        //        closeEditor();
        //    }
        //});

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('fileEditor').style.display === 'flex') {
                closeEditor();
            }
            if (e.key === 'Escape' && document.getElementById('customModal').style.display === 'flex') {
                // Only close modal on escape if there's a cancel button visible
                const cancelBtn = document.getElementById('modalCancel');
                if (cancelBtn.style.display !== 'none') {
                    document.getElementById('modalCancel').click();
                }
            }
            if (e.ctrlKey && e.key === 's' && document.getElementById('fileEditor').style.display === 'flex') {
                e.preventDefault();
                saveFile();
            }
        });
    </script>
</body>
</html>
