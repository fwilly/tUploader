<?php

/**
 * @author Michael Kirchner
 * @author Mario Gleichmann
 */
class tDirectory
{
    /** @var tUploader */
    private $tUploader = null;
    private $accessControlAllowOrigin = null;

    public function __construct($tUploader, $config)
    {
        $this->tUploader = $tUploader;
        if(isset($config['accessControlAllowOrigin'])) $this->accessControlAllowOrigin = $config['accessControlAllowOrigin'];
    }

    private function header() {
        if($this->accessControlAllowOrigin) {
            header("Access-Control-Allow-Origin: " . $this->accessControlAllowOrigin);
        }
    }

    public function delete($action, $path, $fileName)
    {
        $this->header();
        $name = $this->tUploader->getRootDirectory() . $this->addPathEnd($path, true) . $fileName;
        $result = array('action' => 'delete', 'success' => false, 'path' => $path, 'name' => $fileName);
        if (is_dir($name)) {
            $result['type'] = 'dir';
            $count = count(scandir($name));
            if ($count == 2) {
                $result['success'] = true;
                rmdir($name);
            }
        } else if (file_exists($name)) {
            $result['type'] = 'file';
            $result['success'] = true;
            unlink($name);
        }

        echo json_encode($result);
    }

    public function uploads($action, $path)
    {
        $this->header();

        $filesAndFolders = $this->getDirectory($path);
        $folders = array($this->simplifyPath(array($path)));
        $files = array();
        foreach ($filesAndFolders as $fileOrFolder) {
            $filePath = $this->simplifyPath(array($this->tUploader->getRootDirectory(), $path, $fileOrFolder), '/');
            if (is_dir($filePath)) {
                $folders[] = array('dir', $fileOrFolder);
            } else {
                $files[] = array('file', $fileOrFolder);
            }
        }
        $filesAndFolders = array_merge($folders, $files);
        $sJsonString = json_encode($filesAndFolders);
        echo $sJsonString;
    }

    public function create($action, $path, $fileName) {
        $this->header();

        $name = $this->tUploader->getRootDirectory() . '/' . $this->addPathEnd($path, true) . $fileName;
        if (!file_exists($name)) {
            if (!mkdir($name, 0777)) {
                $error = '{error:"cannot create folder \'' . $name . '\' in \'' .
                    getcwd() . '\'",message:"please check the folder rights"}';
                header("HTTP/1.0 500 internal server error");
                echo $error;
                return null;
            }
            $filesAndFolders = $this->getDirectory($path);
            $position = array_search($fileName, $filesAndFolders, true);
            echo json_encode(array('action' => 'create', 'type' => 'dir', 'success' => true, 'path' => $path, 'name' => $fileName, 'order' => $position));
        } else {
            echo json_encode(array('action' => 'create', 'type' => 'dir', 'success' => false, 'path' => $path, 'name' => $fileName));
        }
    }

    public function download($action, $path, $fileName) {
        $this->header();

        $name = realpath($this->tUploader->getRootDirectory() . $this->addPathEnd($path, true) . $fileName);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo file_get_contents($name);
    }

    /**
     * create file with content, and create folder structure if doesn't exist
     * I found this method on http://stackoverflow.com/questions/13372179/creating-a-folder-when-i-run-file-put-contents
     *
     * @param String $filepath
     * @param String $message
     */
    private function forceFilePutContents($filepath, $message)
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
            $alreadyExist = false;
            if (file_exists($filepath)) {
                $alreadyExist = true;
            }

            return array(
                'overwrite' => $alreadyExist,
                'success' => file_put_contents($filepath, $message) ? true : false
            );
        } catch (Exception $e) {
            logError(__DIR__ . '/error.log', "ERR: error writing '$message' to '$filepath', " . $e->getMessage());
            echo "ERR: error writing '$message' to '$filepath', " . $e->getMessage();
        }
    }

    private function simplifyPath($aPathArray, $aStart = '', $aEnd = '')
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

    private function addPathEnd($aPath, $notIfEmpty = false)
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

    public function basePage() {
        echo file_get_contents(__DIR__ . '/tDirectory.html');
    }

    private function getDirectory($path)
    {
        $realPath = $this->simplifyPath(array($this->tUploader->getRootDirectory(), $path), '/', '/');
        $filesAndFolders = scandir($realPath);
        array_shift($filesAndFolders); // remove '.'
        array_shift($filesAndFolders); // remove '..'

        $resultDirectories = array();
        $resultFiles = array();

        foreach($filesAndFolders as $item) {
            if(is_dir(join('/', array($realPath, $item)))) {
                $resultDirectories[] = $item;
            } else {
                $resultFiles[] = $item;
            }
        }

        return array_merge($resultDirectories, $resultFiles);
    }

    public function upload($action, $path)
    {
        $this->header();

        $name = null;
        if (isset($_FILES["files"])) {
            $errorMessage = false; // if true, the script will not return true; errors discribe themselve
            $saveResult = array();
            foreach ($_FILES["files"]["error"] as $key => $error) {
                if ($error == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES["files"]["tmp_name"][$key];
                    $name = $_FILES["files"]["name"][$key];
                    $saveResult = $this->forceFilePutContents($this->tUploader->getRootDirectory() . '/' . $this->addPathEnd($path, true) . "$name",
                        file_get_contents($tmp_name));
                } else if ($error == UPLOAD_ERR_FORM_SIZE) {
                    $errorMessage = '{error:' . $error . ',message:"' . codeToMessage($error) . '",maxFileSize:"' .
                        ini_get("post_max_size") . '"}';
                } else {
                    $errorMessage = '{error:' . $error . ',message:"' . codeToMessage($error) . '"}';
                }
            }
            if ($errorMessage === false) {
                $filesAndFolders = $this->getDirectory($path);
                $position = $name ? array_search($name, $filesAndFolders, true) : null;
                $result = array_merge(array('action' => 'upload', 'type' => 'file', 'path' => $path, 'name' => $name, 'order' => $position), $saveResult);
                echo json_encode($result);
            } else {
                header("HTTP/1.0 404 some Error on upload");
                echo $errorMessage;
            }
        } else {
            header("HTTP/1.0 404 missing file");
            echo '{error:"no File uploaded",message:"please check the file size",maxFileSize:"' .
                ini_get("post_max_size") . '"}';
        }
    }

    public function other($action, $path) {
        $this->header();

        $extension = explode('.', $action);
        $extension = strtolower($extension[count($extension) - 1]);

        header("Content-Type: " . $this->tUploader->getContentType($extension));

        $root = $this->tUploader->getRootDirectory();
        if (file_exists($this->simplifyPath(array($root, $path, $action), '/'))) {
            echo file_get_contents($this->simplifyPath(array($root, $path, $action), '/'));
        } else if (file_exists("./" . $action)) {
            echo file_get_contents("./" . $action);
        } else {
            echo "file" . "./" . $this->addPathEnd($path, true) . $action . " not found";
        };
    }
}