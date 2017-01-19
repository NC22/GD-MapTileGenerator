<?php
namespace Kelly;

/**
 * GDMapTileGenerator v1.0 (С) 2014 NC22 | License : GPLv3 
 * 
 * check extension_loaded('gd') before use
 * 
 * Helpfull config options<br>
 * temporary set unlimited memory limit<br>
 * ini_set("memory_limit", "-1");<br>
 * temporary set unlimited execution time<br>
 * ini_set("max_execution_time", 0);<br>
 */

class GDMapTileGenerator 
{    
    private $map = null;
    
    private $deleteBuffer = false;
    private $skipExist = false;
    private $extended = false;
    
    private $tileSize = 256;
    private $storage = './tiles/';
    private $struct = '%d/%d/%d';
    
    private $ext = 'png'; // output extension
    private $quality = 0; // auto by maximal quality for extension
    
    private $minZoom = 0;
    private $maxZoom = 0; // auto by map image size, also auto corrects if map is smaller
    
    private $filler = array(255, 255, 255, 127);
    private $callback = false;
    private $extend = true; // extend map if map size is incorrect to max zoom pixels, if false cut map by smaller edge
        
    /**
     * RU<br>
     * Инвертировать ось Y<br>
     * По протоколу TMS (Tile Map Service) вывод тайлов идет снизу вверх (для него координата 0,0 находится снизу)<br>
     * <br>
     * EN<br>
     * If true, inverses Y axis numbering for tiles (turn this on for TMS services)<br>
     * http://www.maptiler.org/google-maps-coordinates-tile-bounds-projection/
     * @var boolean 
     */
    
    private $tms = false;
    
    private $lvls = array();
    
    /**
     * 
     * @param string $mapFile
     * @param boolean $skipExist skip file if zoom level file already generated
     * @param boolean $deleteBuffer delete zoom level image after generate tiles, any way it recreates after generateBaseZoomImages()
     */
    
    public function __construct($mapFile, $skipExist = false, $deleteBuffer = false) 
    {
        if (!file_exists($mapFile)) { 
            $this->log('File ' . $mapFile . ' unexist or unable to read');
            return; 
        }            
        
        $this->map = $mapFile;        
        
        $this->deleteBuffer = (bool) $deleteBuffer;
        $this->skipExist = (bool) $skipExist;
    }    
    
    private function getDir($dir) 
    {
        $result = true;
        if (!is_dir($dir)) {
           $back = umask(0);
           $result = mkdir($dir, 0775, true);
           umask($back);
        }
        
        return $result ? $dir : false;
    }
    
    public function set($param, $value) 
    {
        $this->{$param} = $value;
    }
    
    public function get($param) 
    {
        return $this->{$param};
    }
    
    private function cleanUp() 
    {
        foreach ($this->lvls as $file) {
            if (is_file($file)) unlink($file);
        }
     }


    public function exec() 
    {
        if (!$this->map) {
            return false;
        }
        
        if (!$this->quality) {
            if ($this->ext === 'jpg') {
                $this->quality = 90;
            } elseif ($this->ext === 'png') {
                $this->quality = 9;
            }
        }
        
        $this->lvls = array();
        
        if ($this->generateBaseZoomImages()) {
            for ($i = $this->minZoom; $i <= $this->maxZoom; $i++) {
                if (!$this->generateTiles($i)) {
                    $this->cleanUp();
                    return false;
                }
            }
        }
        
        return true;
    }
    
    public function generateTiles($zoom) 
    {
        $file = $this->storage . $zoom . '.' . $this->ext;
        if (!is_file($file) || !is_readable($file)) {
            $this->log('Cannot read image ' . $file . ', for zoom ' . $zoom);
            return false;
        }
        
        $base = $this->loadImage($file);
        $baseW = imagesx($base);
        $baseH = imagesy($base);
        
        // num of tiles in this zoom level, ceil round up so we need fill empty space after, for tiles with size 256 that not needed
        $x = ceil($baseW / $this->tileSize);
        $y = ceil($baseH / $this->tileSize);
        
        $w = $this->tileSize;
        $h = $w;
        
        $cutX = 0;
        $cutY = 0;
        
        for ($ix = 0; $ix < $x; $ix++) {
            $cutX = $ix * $w;
            for ($iy = 0; $iy < $y; $iy++) {
                
                $lvlFile = $this->storage . sprintf($this->struct, $zoom, $ix, $iy) . '.' . $this->ext;
                $dir = pathinfo($lvlFile, PATHINFO_DIRNAME) . '/';
                if (!$this->getDir($dir)) {
                    $this->log('generateTiles : Create dir fail : ' . $dir);
                }
                
                if ($this->skipExist && is_file($lvlFile)) {
                    continue;
                }
                
                $cutY = $this->tms ? $baseH - ($iy + 1) * $h : $iy * $h;
                $tile = $this->createImage($w, $h, $this->extended);

                imagecopy($tile, $base, 0, 0, $cutX, $cutY, $w, $h);
  
                if (!$this->saveImage($tile, $lvlFile)) {
                    return false;
                }
                imagedestroy($tile);
            }
        }
        
        if ($this->deleteBuffer) unlink($file);
        imagedestroy($base);
        $this->log('Generate tiles finished ' . $zoom);
        return true;
    }
    
    public function generateBaseZoomImages() 
    {
        $map = $this->loadImage($this->map);
        if (!$map) return false;
        $this->log('Map image file loaded');
        
        $wMap = imagesx($map);   
        $hMap = imagesy($map);         
        if ($this->extend) $mapSize = max($wMap, $hMap);
        else $mapSize = min($wMap, $hMap);
        
        $mapMaxZoom = log($mapSize / $this->tileSize, 2);
        $mapMaxZoom = $this->extend ? ceil($mapMaxZoom) : floor($mapMaxZoom);
        
        if ($mapMaxZoom <= 0) $mapMaxZoom = 0;
        
        $mapZoomSize = pow(2, $mapMaxZoom) * $this->tileSize; // pixels
        
        if ($this->extend and ($mapZoomSize > $hMap or $mapZoomSize > $wMap)) {
            $this->extended = true;
            $this->log('map size need to be extended');
        }
        
        if (!$this->maxZoom or $this->maxZoom > $mapMaxZoom) {               
            $this->log('Set auto zoom (zoom not set by default or maxZoom > map actual size) - ' . $this->maxZoom . ' | ' . $mapMaxZoom . ' | ' . $mapSize);
            $this->maxZoom = $mapMaxZoom;
        }
        
        if ($mapZoomSize != $hMap or $mapZoomSize != $wMap) {
            $this->log('Map is not quad or need to be cutted or extended '
                     . '(map size not match with correct max zoom size) - ' . $mapZoomSize . ' | ' . $hMap . ' | ' . $wMap);
            
            $newMap = $this->createImage($mapZoomSize, $mapZoomSize, $this->extended);
            
            if ($this->extended) { // need to extend
                              
                $mapHalfPoint = floor($mapZoomSize / 2);
                $halfByX = floor($wMap / 2);
                $halfByY = floor($hMap / 2);
                $pointX = $mapHalfPoint - $halfByX;
                $pointY = $mapHalfPoint - $halfByY;
                
                imagecopyresampled($newMap, $map, $pointX, $pointY, 0, 0, 
                    $wMap, $hMap, $wMap, $hMap);
                
                $this->log('Extend map size. Base image copyed to ' . $pointX . ' | ' . $pointY);
            } else { // copy map to mapZoomSize image (cut if it bigger)
                imagecopyresampled($newMap, $map, 0, 0, 0, 0, 
                    $mapZoomSize, $mapZoomSize, $mapZoomSize, $mapZoomSize); 
            }
            
            imagedestroy($map);
            $map = $newMap;
        }
        
        // $requiredMemoryMB = ( $imageInfo[0] * $imageInfo[1] * ($imageInfo['bits'] / 8) * $imageInfo['channels'] * 2.5 ) / 1024;
     
        $prevLvlImg = $map;
        for ($i = $this->maxZoom; $i >= $this->minZoom; $i--) {
            $lvlFile = $this->storage . $i . '.' . $this->ext;            
            $dir = pathinfo ($lvlFile, PATHINFO_DIRNAME);
            if (!$this->getDir($dir)) {
                $this->log('generateBaseZoomImages : Create dir fail : ' . $dir);
            }
            
            if (!$this->skipExist && is_file($lvlFile)) {
                continue;
            }
         
            $lvlW = pow(2, $i) * $this->tileSize;
            $lvlH = $lvlW;
                    
            $newLvlImg = $this->createImage($lvlW, $lvlH, $this->extended);
            imagecopyresampled($newLvlImg, $prevLvlImg, 0, 0, 0, 0, 
                    $lvlW, $lvlH, imagesx($prevLvlImg), imagesy($prevLvlImg));
            imagedestroy($prevLvlImg);
            
            $prevLvlImg = $newLvlImg;
            
            if (!$this->saveImage($prevLvlImg, $lvlFile)) {
                return false;
            }
            
            $this->log('Create base zoom image file ' . $i);
            $this->lvls[] = $lvlFile;
        }
        
        if ($prevLvlImg) {
            imagedestroy($prevLvlImg);
        }

        return true;
    }
    
    private function createImage($x, $y, $filler = true) 
    {
        $img = imagecreatetruecolor($x, $y); 
        
        if ($filler) {            
            if ($this->filler[3]) {                
                imagealphablending( $img, false );
                imagesavealpha( $img, true );
                $filler = imagecolorallocatealpha($img, $this->filler[0], $this->filler[1], $this->filler[2], $this->filler[3]);
            } else {
                $filler = imagecolorallocate($img, $this->filler[0], $this->filler[1], $this->filler[2]);
            }
            
            imagefill($img, 0, 0, $filler);    
        }
        
        return $img;
    }
    
    private function loadImage($imgWay) 
    {
        if (!is_file($imgWay) || !is_readable($imgWay)) {
            $this->log('Cannot read image file ' . $imgWay . ' - unexist or unreadable');
            return false;
        } 

        $size = getimagesize($imgWay);
        if ($size === false) {
            $this->log('Cannot read image file ' . $imgWay . ' - bad data');
            return false;
        }

        switch ($size[2]) {
            case 2: $img = imagecreatefromjpeg($imgWay);
                break;
            case 3: $img = imagecreatefrompng($imgWay);
                break;
            case 1: $img = imagecreatefromgif($imgWay);
                break;
            default : return false;
        }

        if (!$img) {
            $this->log('Cannot read image file ' . $imgWay . ' - imagecreate function return false');
            return false;
        }
        
        if ($size[2] == 3) imagesavealpha($img, true);
        imagealphablending($img, true);   
        
        return $img;
    }
    
    private function saveImage($img, $imgWay) 
    {
        switch ($this->ext) {
            case 'jpg':
            case 'jpeg': $result = imagejpeg($img, $imgWay, $this->quality); break;
            case 'png': $result = imagepng($img, $imgWay, $this->quality); break;
            case 'gif': $result = imagegif($img, $imgWay); break;
            default : return false;
        }   
        
        if (!$result) {
            $this->log('Cannot write image file ' . $imgWay);
            return false;
        }
        
        chmod($imgWay, 0664);
        return true;
    }
  
    private function log($text) 
    {
        if ($this->callback) {
            call_user_func_array($this->callback, array('GDMapTileGenerator : ' . $text));
        }
    }
}
