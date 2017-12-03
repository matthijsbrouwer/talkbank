<?php

$rootDir = "/data/resources/data-xml";

if(isset($_GET["path"])) {
  $path = $_GET["path"];
  if(preg_match("/^data\-orig\-xml/",$path)&&!preg_match("/\.\./",$path)) {
    $location = $rootDir.preg_replace("/^data\-orig\-xml/", "", $path);
    if(preg_match("/\.xml$/",$location)) {
      $location.=".gz";
      if(file_exists($location)&&is_readable($location)) {
        //handle gzip?
        if(strpos($_SERVER["HTTP_ACCEPT_ENCODING"],"gzip") !== false ){
          $viewCompressed = true;
        } else {
          $viewCompressed = false;
        }
        if($viewCompressed) {
          ini_set("zlib.output_compression","Off");
          header("Content-Encoding: gzip");
          header("Content-type: text/xml");
          header("Content-Length: ".filesize($location));
          header("Cache-Control: no-cache, no-store, max-age=0, must-revalidate");
          header("Pragma: no-cache");
        } else {
          header("Content-Disposition: attachment; filename=" . urlencode(basename($location)));
          header("Content-Type: application/force-download");
          header("Content-Type: application/octet-stream");
          header("Content-Type: application/download");
          header("Content-Description: File Transfer");
          header("Content-Length: " . filesize($location));
        }
        flush();
        $fp = fopen($location, "r");
        while (!feof($fp)) {
          echo fread($fp, 65536);
          flush();
        }
        fclose($fp); 
      } 
    } else if(preg_match("/\/$/",$location) || preg_match("/\/([^\.\/]+)$/",$location)) {
      if(file_exists($location)&&is_readable($location)&&is_dir($location)) {
        if(!preg_match("/\/$/",$location)) {
          $location = preg_replace("/(.*?)\/([^\/]*)$/","\\2",$location);
          header("location: ./".$location."/");
          exit();
        } else {
          $files = scandir($location, false);
          echo("<h1>".htmlentities($path)."</h1>\n");
          foreach($files AS $file) {
            if(is_file(realpath($location)."/".$file)) {
              if(preg_match("/\.xml\.gz$/",$file)) {
                $file = preg_replace("/\.gz$/","",$file);
                echo("<p><a href=\"./".$file."\">".htmlentities($file)."</a></p>");
              }
            } else if(is_dir(realpath($location)."/".$file)) {
              echo("<p><a href=\"".$file."\">".htmlentities($file)."</a></p>");
            }  
          }
          exit();
        }
      }  
    }
  }
}

//default action
header("HTTP/1.0 404 Not Found");
echo("<h1>NOT FOUND</h1>");
exit();


?>