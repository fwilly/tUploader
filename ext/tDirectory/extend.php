<?php
/**
 * @author Michael Kirchner
 */

include_once 'tDirectory.php';
include_once __DIR__ . '/config.php';

$tDirectory = new tDirectory($this, $config);

$this->actionHandling['uploads.json']['class'] = $tDirectory;
$this->actionHandling['/']['class'] = $tDirectory;
$this->actionHandling['tDirectory.html']['class'] = $tDirectory;
$this->actionHandling['upload.json']['class'] = $tDirectory;
$this->actionHandling['delete.json']['class'] = $tDirectory;
$this->actionHandling['default']['class'] = $tDirectory;

$this->actionHandling['delete.json'] = array(
    'class' => $tDirectory,
    'function' => 'delete'
);

$this->actionHandling['create.json'] = array(
    'class' => $tDirectory,
    'function' => 'create'
);

$this->actionHandling['download.json'] = array(
    'class' => $tDirectory,
    'function' => 'download'
);