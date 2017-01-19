<?php
error_reporting(E_ALL | E_STRICT); 

// An example with include tile map by using Leaflet

class Example {

    private $mode;
    private $root; 
    private $mapFile = 'test';
    private $mapDir = 'tiles/'; // back slash at the end is important
    private $mapZoomMax;

    private $log = '';

    public function __construct() {
        $this->root = dirname(__FILE__) . '/';
        require_once($this->root . 'GDMapTileGenerator.class.php');
    }

    public function log($err) {
        $this->log .= $err . '<br>';
    }

    public function exec() {
            
        $this->mode = empty($_GET['mode']) ? 'default' : $_GET['mode'];
        if ($this->mode == 'upload') {
            
            if (empty($_POST['image-upload'])) $this->show('Error, form not sended');
            
            if (empty($_FILES['image-file']['tmp_name']) or !is_uploaded_file($_FILES['image-file']['tmp_name'])) $this->show('Upload file fail');
            
            $extension = strtolower(substr($_FILES['image-file']['name'], 1 + strrpos($_FILES['image-file']['name'], ".")));
            $file = $this->root . $this->mapFile . '.' . $extension;
            
            if (file_exists($file)) unlink($file);
            
            if (!move_uploaded_file($_FILES['image-file']['tmp_name'], $file)) $this->show('Move uploaded file fail');
            
            chmod($file, 0664);
            
            $generator = new Kelly\GDMapTileGenerator($file, false, true);
            $generator->set('callback', array($this, 'log')); //  '\Kelly\Tool::log'
            $generator->set('ext', 'png'); 
            $generator->set('storage', $this->mapDir);
            
            ini_set("memory_limit", "-1");
            ini_set("max_execution_time", 0);
            
            if ($generator->exec()) {
            
                $this->mapZoomMax = $generator->get('maxZoom');
                if (!$this->mapZoomMax) $this->mapZoomMax = 2;
                
            } else $this->show('Tile map generation fail');
        } 

        $this->show();
        
    }


    public function show($err = 'No errors') {

    ?>
    <!DOCTYPE html>
    <html lang="en">
        <head>        
            <title>GDMapTileGenerator example</title>
            
            <?php if ($this->mode == 'upload') { ?>
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.0.2/dist/leaflet.css" />
                <script src="https://unpkg.com/leaflet@1.0.2/dist/leaflet.js"></script>
            <?php } ?>
            
            <meta charset="UTF-8"> 

            <style>
                body, html {
                    padding : 0px;
                    margin : 0px;
                }
                
                body {
                    padding : 24px;
                    background : #e2e2e2;
                }
                
                #map-container {
                    margin-top : 24px;
                }
                
                .notice {
                    display : block;
                    background : rgba(242, 114, 114, 0.8);
                    height : 32px;
                    line-height : 32px;
                    border-radius : 6px;
                    color : #fff;
                    padding-left : 12px;
                }
            </style>
        </head>
        
        <body>
        
            <p class="notice">
                This file created for testing purposes only. It shows an example of creating map tile from image with help of GDMapTileGenerator class    
            </p>
            
            <form action="example.php?mode=upload" method="post" enctype='multipart/form-data'>
                <p>Choose an image (Upload max size : <?php echo ini_get('upload_max_filesize'); ?> | Post data max size : <?php echo ini_get('post_max_size'); ?>)</p>
                
                <input type="hidden" name="image-upload" value="1">
                <input type="file" name="image-file">
                <input type="submit" value="Upload">
            </form>
            
            <?php if ($this->mode == 'default') { ?>
            
            <?php } elseif ($this->mode == 'upload' and $err == 'No errors') { ?>
            
                <div id="map-container" style="width : 640px; height : 480px;"></div>    
                
                <script>
                    var maxZoom = <?php echo $this->mapZoomMax; ?>;
                    if (maxZoom <= 0) maxZoom = 0;
                    
                    var minZoom = 1;
                    if (maxZoom == 0) minZoom = 0;
                    
                    var map = L.map('map-container', {
                        center: [0.0, 0.0],
                        zoom: maxZoom,  
                    });
                    
                    var mapLayer = L.tileLayer(
                        '<?php echo $this->mapDir; ?>{z}/{x}/{y}.png', {
                        maxZoom: maxZoom,
                        minZoom: minZoom,
                        tms: false,
                        crs: L.CRS.Simple,
                        continuousWorld: false,
                        noWrap: true
                    });
                    
                    mapLayer.addTo(map);
                </script>
                
         <?php } ?>
         
               
        <?php if ($err or $this->log) { ?>
                
            <p>Upload errors : <?php echo $err; ?></p>
            <p>Tile generator log : <br><?php echo $this->log; ?></p>
        
        <?php } ?>
        
        </body>
        
    </html>

    <?

    exit;
    }
}

$page = new Example();
$page->exec();
