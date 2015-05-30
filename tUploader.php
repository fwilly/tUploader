<?php
/**
 * author: Tobias Nickel
 * author: Michael Kirchner
 *
 * an file Uplaod and sharing server, do demonstrate the usage of tUploader.js.
 * run this server, useing the PHP command:
 *        php -S 0.0.0.0:8080 upload.php
 * and then visit: http://localhost:8080/ in your borwser.
 * do not let this server run public, or some hacker could delete you all your files ! ! !
 * -> check the path "/delete.json".
 */

/**
 * create file with content, and create folder structure if doesn't exist
 * I found this method on http://stackoverflow.com/questions/13372179/creating-a-folder-when-i-run-file-put-contents
 * @param String $filepath
 * @param String $message
 */
class tUploader
{
    /**
     * a list with all action handling
     * @var array
     */
    private $actionHandling = null;
    private $extensionDirectory = null;
    private $rootDirectory = null;
    private $extensions = null;
    private $contentTypes = null;

    public function __construct($extensions = array(), $rootDirectory = './uploads/', $extensionDirectory = './ext/')
    {
        $this->rootDirectory = realpath($rootDirectory);
        $this->extensionDirectory = realpath($extensionDirectory);
        $this->extensions = $extensions;

        if(!$this->rootDirectory) throw new Exception("File Directory '$rootDirectory' not found");
        if(count($this->extensions) > 0 && !$this->extensionDirectory) throw new Exception("Extensions Directory '$extensionDirectory' not found");

        $this->contentTypes = array(
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'css' => 'text/css',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'jpg' => 'image/jpg',
            'jpeg' => 'image/jpg',
            'js' => 'application/javascript'
        );

        $this->actionHandling = array(
            'uploads.json' => array(
                'class' => $this,
                'function' => 'uploads'
            ),
            '/' => array(
                'class' => $this,
                'function' => 'basePage'
            ),
            'upload.html' => array(
                'class' => $this,
                'function' => 'basePage'
            ),
            'tUploader.min.js' => array(
                'class' => $this,
                'function' => 'getTUploaderJS'
            ),
            'tUploader.js' => array(
                'class' => $this,
                'function' => 'getTUploaderJS'
            ),
            'delete.json' => array(
                'class' => $this,
                'function' => 'delete'
            ),
            'upload.json' => array(
                'class' => $this,
                'function' => 'upload'
            ),
            'maxFileSize.json' => array(
                'class' => $this,
                'function' => 'maxFileSize'
            ),
            'default' => array(
                'class' => $this,
                'function' => 'other'
            )
        );

        $this->extend();
    }

    public function run()
    {
        $sQuery = $_SERVER["QUERY_STRING"];
        parse_str($sQuery, $tQuery);
        $path = $tQuery['path'];
        $file = $tQuery['file'];

        if (strlen($file) == 0) $file = '/';
        $action = null;
        if (isset($this->actionHandling[$file])) {
            $action = $this->actionHandling[$file];
        } else {
            $action = $this->actionHandling['default'];
        }

        $class = isset($action['class']) ? $action['class'] : null;
        $function = $action['function'];
        $fileName = isset($tQuery['name']) ? $tQuery['name'] : null;

        if($class) {
            $class->$function($file, $path, $fileName);
        } else {
            $function($file, $path, $fileName);
        }
    }

    public function delete($action, $path, $fileName) {
        $name = 'uploads/' . $fileName;
        if (file_exists($name)) {
            unlink($name);
        }
        echo "deleted " . $name;
    }

    public function getTUploaderJS() {
        echo file_get_contents('./tUploader.js');
    }

    public function basePage() {
        echo file_get_contents('./upload.html');
    }

    public function upload()
    {
        // $_FILES is from PHP
        // ['files'] is from the tUploader's .varName Property
        // and this code is from http://php.net/manual/en/function.move-uploaded-file.php
        if (isset($_FILES["files"])) {
            $errorMessage = false; // if true, the script will not return true; errors discribe themselve
            foreach ($_FILES["files"]["error"] as $key => $error) {
                if ($error == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES["files"]["tmp_name"][$key];
                    $name = $_FILES["files"]["name"][$key];
                    $this->forceFilePutContents("./uploads/$name", file_get_contents($tmp_name));
                } else if ($error == UPLOAD_ERR_FORM_SIZE) {
                    $errorMessage = '{error:' . $error . ',message:"' . $this->codeToMessage($error) . '",maxFileSize:"' . ini_get("post_max_size") . '"}';
                } else {

                    $errorMessage = '{error:' . $error . ',message:"' . $this->codeToMessage($error) . '"}';
                }
            }
            if ($errorMessage === false) {
                echo "true";
            } else {
                header("HTTP/1.0 404 some Error on upload");
                echo $errorMessage;
            }
        }
    }

    public function getContentType($extension) {
        $contentType = null;
        if(isset($this->contentTypes[$extension])) {
            $contentType = $this->contentTypes[$extension];
        } else {
            $contentType = 'application/force-download';
        }

        return $contentType;
    }

    public function other($action) {
        $extension = explode('.', $action);
        $extension = strtolower($extension[count($extension) - 1]);

        header("Content-Type: " . $this->getContentType($extension));

        if (file_exists("." . $action)) {
            echo file_get_contents("." . $action);
        } else {
            echo "file" . "." . $action . " not found";
        }
    }

    public function uploads()
    {
        $dir = scandir('./uploads/');
        // remove the first two folders "." and ".."
        array_shift($dir);
        array_shift($dir);
        echo json_encode($dir);
    }

    public function maxFileSize() {
        echo '{maxFileSize:' . ini_get("post_max_size") . '}';
    }

    public function forceFilePutContents($filepath, $message)
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
            echo "ERR: error writing '$message' to '$filepath', " . $e->getMessage();
        }
    }

    /**
     * //method from http://php.net/manual/en/features.file-upload.errors.php
     *
     * @param $code
     * @return string
     */
    public function codeToMessage($code)
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

    /**
     * @return null|string
     */
    public function getRootDirectory()
    {
        return $this->rootDirectory;
    }

    private function extend() {
        foreach ($this->extensions as $ext) {
            $extDir = join('/', array($this->extensionDirectory, $ext));
            $extFile = join('/', array($this->extensionDirectory, $ext, 'extend.php'));
            if (is_dir($extDir) && file_exists($extFile)) {
                include_once $extFile;
            }
        }
    }
}