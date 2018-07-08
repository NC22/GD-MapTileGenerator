<?php

/* CLI version (CRON \ Command line) of execution script for GDMapTileGenerator class
  
   Usage example from command line : php /home/test/generateCli.php inputImage.png outputDir/ 1
	
   Where 
   
   "/home/test/generateCli.php" - script itself
   "inputImage.png" - image that will be used for tile generation
   "outputDir/" - directory to store result image tiles (if folder is not empty, old version will be removed before generate new)
   "1" - use relative from script folder patches for input image and output directory (ex. /home/test/outputDir/), If set 0 or unset - use absolute 
   
   
   Output : "ok" - on success and "fail : [error log output]" - on fail with detailed error description
   
*/

error_reporting(E_ALL | E_STRICT); 

class cliTileGenerator {

	private $argumentKeys = array( 'fileName' => 0, 'inputFile' => 1, 'outputDir' => 2, 'relative' => 3);
	private $params = false;
	private $root = false;
	private $log = '';
	
	public function __construct() {
	
		$this->root = dirname(__FILE__) . '/';
		require_once($this->root . 'GDMapTileGenerator.class.php');
	}
	
    public function clearDir($dirPath, $removeDir = true, $ext = 'png')
    {
        if (!is_dir($dirPath))
            return true;
			
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file))
                if (!$this->clearDir($file)) {
					return false;
				}
            else {
				if ($ext and strpos($file, '.' . $ext) === false) return false;
				else unlink($file);
			}
        }
        
        if ($removeDir and is_dir($dirPath)) rmdir($dirPath);
		
		return true;
    }

    public function log($err) {
        $this->log .= $err . '<br>';
    }
	
	public function exec($params = false){
		
		if (php_sapi_name() != 'cli') {
			return 'not in CLI mode';
		}
		
		if (empty($params) || sizeof($params) <= 1) return 'parametrs empty, [inputFile] [outputDir] [relative] root dir for relative path is ' . $this->root;
		
		$this->params = array();
		
		foreach ($this->argumentKeys as $keyName => $key) {
			
			$this->params[$keyName] = false;			
			$params[$key] = !empty($params[$key]) ? trim($params[$key]) : false;
			
			if (empty($params[$key])) {
				
				return $key . ' is empty';
				
			} else {
			
				$this->params[$keyName] = $params[$key];
				
				if ($keyName == 'outputDir') {					
		
					$latsChar = $this->params[$keyName][strlen($this->params[$keyName])-1];
					if ($latsChar != '/' || $latsChar != '\\') $this->params[$keyName] .= '/'; 					
				}
			}
			
		}
		
		if ($this->params['relative']) {
			$this->params['inputFile'] = $this->root . $this->params['inputFile'];
			$this->params['outputDir'] = $this->root . $this->params['outputDir'];
		}
				
		if (!file_exists($this->params['inputFile'])) return 'file not exist ' . $this->params['inputFile'];
		
		if (!$this->clearDir($this->params['outputDir'], true, 'png')) {
			return 'cant init output dir ' . $this->params['outputDir'] . ', dir must not contain anything except old media data that will be deleted during tile generation';
		}
		
		$generator = new Kelly\GDMapTileGenerator($this->params['inputFile'], false, true);
		$generator->set('callback', array($this, 'log')); 
		$generator->set('ext', 'png'); 
		$generator->set('storage', $this->params['outputDir']);
		
		ini_set("memory_limit", "-1");
		ini_set("max_execution_time", 0);
		
	   if ($generator->exec()) {
			
			return 'ok';
			
		} else return 'fail : ' . $this->log;
	}
}


$cliGen = new cliTileGenerator();
echo $cliGen->exec($argv);
