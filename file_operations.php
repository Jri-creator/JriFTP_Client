<?php
session_start();

// Check if user is connected to FTP
if (!isset($_SESSION['ftp_connection']) || !$_SESSION['ftp_connection']) {
    http_response_code(403);
    echo "Not connected to FTP server";
    exit;
}

// Establish FTP connection
function getFTPConnection() {
    $ftp_conn = ftp_connect($_SESSION['ftp_host'], $_SESSION['ftp_port']);
    if ($ftp_conn && ftp_login($ftp_conn, $_SESSION['ftp_username'], $_SESSION['ftp_password'])) {
        ftp_pasv($ftp_conn, true);
        return $ftp_conn;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'load_file':
            if (isset($_POST['filename'])) {
                $filename = $_POST['filename'];
                $remote_path = rtrim($_SESSION['current_directory'], '/') . '/' . $filename;
                
                $ftp_conn = getFTPConnection();
                if ($ftp_conn) {
                    // Create temporary file to download content
                    $temp_file = tempnam(sys_get_temp_dir(), 'ftp_edit_');
                    
                    if (ftp_get($ftp_conn, $temp_file, $remote_path, FTP_ASCII)) {
                        $content = file_get_contents($temp_file);
                        // Set proper content type for text
                        header('Content-Type: text/plain; charset=utf-8');
                        echo $content;
                    } else {
                        http_response_code(500);
                        echo "Error: Could not download file for editing";
                    }
                    
                    unlink($temp_file);
                    ftp_close($ftp_conn);
                } else {
                    http_response_code(500);
                    echo "Error: Could not connect to FTP server";
                }
            } else {
                http_response_code(400);
                echo "Error: No filename specified";
            }
            break;
            
        case 'save_file':
            if (isset($_POST['filename']) && isset($_POST['content'])) {
                $filename = $_POST['filename'];
                $content = $_POST['content'];
                $remote_path = rtrim($_SESSION['current_directory'], '/') . '/' . $filename;
                
                $ftp_conn = getFTPConnection();
                if ($ftp_conn) {
                    // Create temporary file with new content
                    $temp_file = tempnam(sys_get_temp_dir(), 'ftp_save_');
                    
                    // Write content to temp file
                    if (file_put_contents($temp_file, $content) !== false) {
                        if (ftp_put($ftp_conn, $remote_path, $temp_file, FTP_ASCII)) {
                            echo "success";
                        } else {
                            http_response_code(500);
                            echo "Error: Could not upload modified file";
                        }
                    } else {
                        http_response_code(500);
                        echo "Error: Could not write to temporary file";
                    }
                    
                    unlink($temp_file);
                    ftp_close($ftp_conn);
                } else {
                    http_response_code(500);
                    echo "Error: Could not connect to FTP server";
                }
            } else {
                http_response_code(400);
                echo "Error: Missing filename or content. Received: " . json_encode($_POST);
            }
            break;
            
        case 'create_file':
            if (isset($_POST['filename'])) {
                $filename = $_POST['filename'];
                $remote_path = rtrim($_SESSION['current_directory'], '/') . '/' . $filename;
                
                $ftp_conn = getFTPConnection();
                if ($ftp_conn) {
                    // Create empty temporary file
                    $temp_file = tempnam(sys_get_temp_dir(), 'ftp_new_');
                    file_put_contents($temp_file, '');
                    
                    if (ftp_put($ftp_conn, $remote_path, $temp_file, FTP_ASCII)) {
                        echo "success";
                    } else {
                        http_response_code(500);
                        echo "Error: Could not create new file";
                    }
                    
                    unlink($temp_file);
                    ftp_close($ftp_conn);
                } else {
                    http_response_code(500);
                    echo "Error: Could not connect to FTP server";
                }
            } else {
                http_response_code(400);
                echo "Error: No filename specified";
            }
            break;
            
        case 'create_directory':
            if (isset($_POST['dirname'])) {
                $dirname = $_POST['dirname'];
                $remote_path = rtrim($_SESSION['current_directory'], '/') . '/' . $dirname;
                
                $ftp_conn = getFTPConnection();
                if ($ftp_conn) {
                    if (ftp_mkdir($ftp_conn, $remote_path)) {
                        echo "success";
                    } else {
                        http_response_code(500);
                        echo "Error: Could not create directory";
                    }
                    ftp_close($ftp_conn);
                } else {
                    http_response_code(500);
                    echo "Error: Could not connect to FTP server";
                }
            } else {
                http_response_code(400);
                echo "Error: No directory name specified";
            }
            break;
            
        case 'rename_file':
            if (isset($_POST['old_name']) && isset($_POST['new_name'])) {
                $old_path = rtrim($_SESSION['current_directory'], '/') . '/' . $_POST['old_name'];
                $new_path = rtrim($_SESSION['current_directory'], '/') . '/' . $_POST['new_name'];
                
                $ftp_conn = getFTPConnection();
                if ($ftp_conn) {
                    if (ftp_rename($ftp_conn, $old_path, $new_path)) {
                        echo "success";
                    } else {
                        http_response_code(500);
                        echo "Error: Could not rename file/directory";
                    }
                    ftp_close($ftp_conn);
                } else {
                    http_response_code(500);
                    echo "Error: Could not connect to FTP server";
                }
            } else {
                http_response_code(400);
                echo "Error: Missing old name or new name";
            }
            break;
            
        case 'download_file':
            if (isset($_POST['filename'])) {
                $filename = $_POST['filename'];
                $remote_path = rtrim($_SESSION['current_directory'], '/') . '/' . $filename;
                
                $ftp_conn = getFTPConnection();
                if ($ftp_conn) {
                    // Create temporary file to download content
                    $temp_file = tempnam(sys_get_temp_dir(), 'ftp_download_');
                    
                    if (ftp_get($ftp_conn, $temp_file, $remote_path, FTP_BINARY)) {
                        // Set headers for download
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($temp_file));
                        
                        readfile($temp_file);
                    } else {
                        http_response_code(500);
                        echo "Error: Could not download file";
                    }
                    
                    unlink($temp_file);
                    ftp_close($ftp_conn);
                } else {
                    http_response_code(500);
                    echo "Error: Could not connect to FTP server";
                }
            } else {
                http_response_code(400);
                echo "Error: No filename specified";
            }
            break;
            
        case 'get_file_info':
            if (isset($_POST['filename'])) {
                $filename = $_POST['filename'];
                $remote_path = rtrim($_SESSION['current_directory'], '/') . '/' . $filename;
                
                $ftp_conn = getFTPConnection();
                if ($ftp_conn) {
                    $size = ftp_size($ftp_conn, $remote_path);
                    $mdtm = ftp_mdtm($ftp_conn, $remote_path);
                    
                    $info = array(
                        'size' => $size,
                        'modified' => $mdtm != -1 ? date('Y-m-d H:i:s', $mdtm) : 'Unknown',
                        'is_directory' => $size == -1
                    );
                    
                    header('Content-Type: application/json');
                    echo json_encode($info);
                    
                    ftp_close($ftp_conn);
                } else {
                    http_response_code(500);
                    echo json_encode(array('error' => 'Could not connect to FTP server'));
                }
            } else {
                http_response_code(400);
                echo json_encode(array('error' => 'No filename specified'));
            }
            break;
            
        default:
            http_response_code(400);
            echo "Invalid action: " . $action;
            break;
    }
} else {
    http_response_code(400);
    echo "No action specified or invalid request method";
}
?>
