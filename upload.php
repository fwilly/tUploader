<?php

/**
 * author: Tobias Nickel
 * an file Uplaod and sharing server, do demonstrate the usage of tUploader.js.
 * run this server, useing the PHP command:
 *        php -S 0.0.0.0:8080 upload.php
 * and then visit: http://localhost:8080/ in your borwser.
 * do not let this server run public, or some hacker could delete you all your files ! ! !
 * -> check the path "/delete.json".
 */

$UPLOAD_ROOT = __DIR__ . '/uploads/';
$LOG = __DIR__;
$ERROR_LOG = $LOG . '/error.log';

/**
 * create file with content, and create folder structure if doesn't exist
 * I found this method on http://stackoverflow.com/questions/13372179/creating-a-folder-when-i-run-file-put-contents
 *
 * @param String $filepath
 * @param String $message
 */
function forceFilePutContents($filepath, $message)
{
    try {
        $isInFolder = preg_match("/^(.*)\/([^\/]+)$/", $filepath, $filepathMatches);
        if ($isInFolder) {
            $folderName = $filepathMatches[1];
            $fileName = $filepathMatches[2];
            if (!is_dir($folderName)) {
                mkdir($folderName, 0777, true);
            }
        }
        file_put_contents($filepath, $message);
    } catch (Exception $e) {
        logError(__DIR__ . '/error.log', "ERR: error writing '$message' to '$filepath', " . $e->getMessage());
        echo "ERR: error writing '$message' to '$filepath', " . $e->getMessage();
    }
}

//method from http://php.net/manual/en/features.file-upload.errors.php
function codeToMessage($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
            break;
        case UPLOAD_ERR_PARTIAL:
            $message = "The uploaded file was only partially uploaded";
            break;
        case UPLOAD_ERR_NO_FILE:
            $message = "No file was uploaded";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $message = "Missing a temporary folder";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $message = "Failed to write file to disk";
            break;
        case UPLOAD_ERR_EXTENSION:
            $message = "File upload stopped by extension";
            break;
        default:
            $message = "Unknown upload error";
            break;
    }
    return $message;
}

function simplifyPath($aPathArray, $aStart = '', $aEnd = '')
{
    if(is_array($aPathArray) && !preg_match('/^[a-zA-Z]+:/',join('/', $aPathArray) )) {
        $aPathArray = join('/', $aPathArray);
    } else if(preg_match('/^[a-zA-Z]+\:/',join('/', $aPathArray) )) {
        return realpath(join('/', $aPathArray));
    }

    $sPath = str_replace('//', '/', $aStart . $aPathArray);

    if ($aStart == '' && isset($sPath[0]) && $sPath[0] == '/') {
        $sPath = substr($sPath, 1);
    }
    if ($aEnd == '' && substr($sPath, -1, 1) == '/') {
        $sPath = substr($sPath, 0, -1);
    }

    return $sPath;
}

function addPathEnd($aPath, $notIfEmpty = false)
{
    if ($aPath == "") {
        if (!$notIfEmpty) {
            return $aPath . '/';
        } else {
            return '/';
        }
    } else if (substr($aPath, -1, 1) != "/") {
        return $aPath . '/';
    }

    return $aPath;
}

function removePathEnd($aPath)
{
    if (substr($aPath, -1, 1) == "/") {
        return substr($aPath, 0, -1);
    }

    return $aPath;
}

function logError($path, $message) {
    $date = new DateTime();
    file_put_contents($path, "[" . $date->format('d.m.Y') . " - " . $date->format('H:i:s') . "] " . $message, FILE_APPEND);
}

// all request urls are rewritten per htaccess from
//  DOMAIN.TLD:PORT/PATH1/PATHX/FILE.EXT?PARAM1=VALUE1&PARAMX=VALUEX
// to
//  DOMAIN.TLD:PORT/upload.php?path=PATH1/PATHX&file=FILE.EXT&PARAM1=VALUE1&PARAMX=VALUEX
// start routing

$sQuery = $_SERVER["QUERY_STRING"];
parse_str($sQuery, $tQuery);
$path = $tQuery['path'];
$file = $tQuery['file'];
switch ($file) {
    case 'delete.json':
        $name = $UPLOAD_ROOT . addPathEnd($path, true) . $tQuery['name'];
        $result = array('action' => 'delete', 'success' => false, 'path' => $path, 'name' => $tQuery['name']);
        if (is_dir($name)) {
            $result['type'] = 'dir';
            $result['success'] = true;
            rmdir($name);
        } else if (file_exists($name)) {
            $result['type'] = 'file';
            $result['success'] = true;
            unlink($name);
        }

        echo json_encode($result);
        break;
    case 'download.json':
        $name = realpath($UPLOAD_ROOT . addPathEnd($path, true) . $tQuery['name']);
        header('Content-Disposition: attachment; filename="' . $tQuery['name'] . '"');
        echo file_get_contents($name);
        break;
    case 'create.json':
        $name = $UPLOAD_ROOT . addPathEnd($path, true) . $tQuery['name'];
        if (!file_exists($name)) {
            if (!mkdir($name, 0777)) {
                $error = '{error:"cannot create folder \'' . $name . '\' in \'' .
                    getcwd() . '\'",message:"please check the folder rights"}';
                logError($ERROR_LOG, $error);
                header("HTTP/1.0 500 internal server error");
                echo $error;
            }
        }
        header("Location: /" . addPathEnd($path));
        break;
    case 'upload.json':
        // $_FILES is from PHP
        // ['files'] is from the tUploader's .varName Property
        // and this code is from http://php.net/manual/en/function.move-uploaded-file.php
        if (isset($_FILES["files"])) {
            $errorMessage = false; // if true, the script will not return true; errors discribe themselve
            foreach ($_FILES["files"]["error"] as $key => $error) {
                if ($error == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES["files"]["tmp_name"][$key];
                    $name = $_FILES["files"]["name"][$key];
                    forceFilePutContents($UPLOAD_ROOT . addPathEnd($path, true) . "$name",
                        file_get_contents($tmp_name));
                } else if ($error == UPLOAD_ERR_FORM_SIZE) {
                    $errorMessage = '{error:' . $error . ',message:"' . codeToMessage($error) . '",maxFileSize:"' .
                        ini_get("post_max_size") . '"}';
                } else {
                    $errorMessage = '{error:' . $error . ',message:"' . codeToMessage($error) . '"}';
                    logError($ERROR_LOG, $errorMessage);
                }
            }
            if ($errorMessage === false) {
//                header("Location: /" . addPathEnd($path));
              echo true;
            } else {
            header("HTTP/1.0 404 some Error on upload");
            echo $errorMessage;
            }
        } else {
            header("HTTP/1.0 404 missing file");
            echo '{error:"no File uploaded",message:"please check the file size",maxFileSize:"' .
                ini_get("post_max_size") . '"}';
        }
        break;
    case 'maxFileSize.json':
        echo '{maxFileSize:' . ini_get("post_max_size") . '}';
        break;
    case 'uploads.json':
        $filesAndFolders = scandir(simplifyPath(array($UPLOAD_ROOT, $path), '/', '/'));
        array_shift($filesAndFolders); // remove '.'
        array_shift($filesAndFolders); // remove '..'
        $folders = array(simplifyPath(array($path)));
        $files = array();
        foreach ($filesAndFolders as $fileOrFolder) {
            if($fileOrFolder == 'null') continue;
            $filePath = simplifyPath(array($UPLOAD_ROOT, $path, $fileOrFolder), '/');
            if (is_dir($filePath)) {
                $folders[] = array('dir', $fileOrFolder);
            } else {
                $files[] = array('file', $fileOrFolder);
            }
        }
        $filesAndFolders = array_merge($folders, $files);
        $sJsonString = json_encode($filesAndFolders);
        echo $sJsonString;
        break;
    case '':
    case (substr($path, -1, 1) == '/' ? $path : !$path):
    case 'upload.html':
        header("Content-Type: text/html");
        echo file_get_contents('./upload.html');
        break;
    default:
            $extension = explode('.', $file);
            $extension = strtolower($extension[count($extension) - 1]);
        switch ($extension) {
            case 'txt':
                header("Content-Type: text/plain");
                break;
            case 'htm':
            case 'html':
                header("Content-Type: text/html");
                break;
            case 'css':
                header("Content-Type: text/css");
                break;
            case 'gif':
                header("Content-Type: image/gif");
                break;
            case 'png':
                header("Content-Type: image/png");
                break;
            case 'ico':
                header("Content-Type: image/x-icon");
                break;
            case 'jpg':
            case 'jpeg':
                header("Content-Type: image/jpg");
                break;
            case 'js':
                header("Content-Type: application/javascript");
                break;
            default:
                header("Content-Type: application/force-download");
        }
        if (file_exists(simplifyPath(array($UPLOAD_ROOT, $path, $file), '/'))) {
            echo file_get_contents(simplifyPath(array($UPLOAD_ROOT, $path, $file), '/'));
        } else if (file_exists("./" . $file)) {
            echo file_get_contents("./" . $file);
        } else {
            echo "file" . "./" . addPathEnd($path, true) . $file . " not found";
        };
}
