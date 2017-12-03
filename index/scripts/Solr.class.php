<?php 

class Solr {
  
  private $location;
  private $core;
  
  public function __construct($location, $core) {
    $this->location = $location;
    $this->core = $core;
  }
  
  public function deleteAll() {
    $xml = new SimpleXMLElement("<delete/>");
    $doc = $xml->addChild("query","*:*");
    return $this->postXML("update?commit=true",$xml->asXML());
  }
  
  public function post($json) {
    return $this->postJSON("update", $json);
  }
  
  public function commit() {
    $pause = 1;
    $xml = new SimpleXMLElement("<commit/>");
    if($result = $this->postXML("update",$xml->asXML())) {
      sleep($pause);
    } else {
      return false;
    }
  }
  
  public function checkStatus() {
    $url = $this->location;
    $url.= "admin/cores?action=status&wt=json&core=".urlencode($this->core);
    $status = false;
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_TIMEOUT,3600);
    $result = curl_exec($ch);
    if(curl_errno($ch)){
      echo("PROBLEM: ".curl_errno($ch).": ".curl_error($ch));
    } else {
      if($output = json_decode($result, true)) {
        if(is_array($output)) {
          if(isset($output["status"]) && is_array($output["status"])) {
            if(isset($output["status"][$this->core]) && is_array($output["status"][$this->core])) {
              if(isset($output["status"][$this->core]["index"]) && is_array($output["status"][$this->core]["index"])) {
                if(isset($output["status"][$this->core]["index"]["current"])) {
                  $status = $output["status"][$this->core]["index"]["current"];
                }
              }
            }
          }
        }
      }
    }
    curl_close($ch);
    return $status;
  }
  
  private function postXML($handler, $xml) {
    //force json response
    $defaultPause = 10; //10 seconds
    $errorPause = 900; //15 minutes
    if(preg_match("/^([^\?]*)\?(.*?)$/", $handler, $match)) {
      $handler = $match[1]."?wt=json";
      $arguments = explode("&",$match[2]);
      foreach($arguments AS $argument) {
        if(!preg_match("/^wt\=/",$argument)) {
          $handler.="&".$argument;
        }
      }
    } else {
      $handler = $handler."?wt=json";
    }
    //post
    do {
      $ch = curl_init();
      $url = $this->location;
      $url.= $this->core."/".$handler;
      curl_setopt($ch,CURLOPT_URL, $url);
      curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_HTTPHEADER, array("Content-Type: application/xml; charset=utf-8"));
      curl_setopt($ch,CURLOPT_POST, true);
      curl_setopt($ch,CURLOPT_POSTFIELDS, $xml);
      curl_setopt($ch,CURLOPT_TIMEOUT,3600);
      $result = curl_exec($ch);
      if(curl_errno($ch)){
        echo("PROBLEM: ".curl_errno($ch).": ".curl_error($ch));
      } else {
        if($output = json_decode($result, true)) {
          return $output;
        } else {
          echo("\n Timeout for ".$errorPause." seconds ");
          sleep($errorPause);
        }
      }
      curl_close($ch);
      echo("Retry after ".$defaultPause." seconds\n");
      sleep($defaultPause);
    } while (1==1);
  }
  
  private function postJSON($handler, $json) {
    //force json response
    $defaultPause = 10; //10 seconds
    $errorPause = 900; //15 minutes
    if(preg_match("/^([^\?]*)\?(.*?)$/", $handler, $match)) {
      $handler = $match[1]."?wt=json";
      $arguments = explode("&",$match[2]);
      foreach($arguments AS $argument) {
        if(!preg_match("/^wt\=/",$argument)) {
          $handler.="&".$argument;
        }
      }
    } else {
      $handler = $handler."?wt=json";
    }
    //post
    do {
      $ch = curl_init();
      $url = $this->location;
      $url.= $this->core."/".$handler;
      curl_setopt($ch,CURLOPT_URL, $url);
      curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_HTTPHEADER, array("Content-Type: application/json; charset=utf-8"));
      curl_setopt($ch,CURLOPT_POST, true);
      curl_setopt($ch,CURLOPT_POSTFIELDS, $json);
      curl_setopt($ch,CURLOPT_TIMEOUT,3600);
      $result = curl_exec($ch);
      if(curl_errno($ch)){
        echo("PROBLEM: ".curl_errno($ch).": ".curl_error($ch));
      } else {
        if($output = json_decode($result, true)) {
          return $output;
        } else {
          //trigger_error($result);
          //return false;
          echo("\n Timeout for ".$errorPause." seconds ");
          sleep($errorPause);
        }
      }
      curl_close($ch);
      echo("\nRetry after ".$defaultPause." seconds ");
      sleep($defaultPause);
    } while (1==1);
  }
  
  
}


?>