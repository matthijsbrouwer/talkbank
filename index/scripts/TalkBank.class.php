<?php
class TalkBank {
  private $baseCMDIDirectory;
  private $dbCMDI;
  private $solr;
  private $counter = 0;
  private $counterMaximum = 100;
  private $starttime;
  private $timeout = 10;
  private $loadExternal;
  public function __construct($solr, $baseCMDIDirectory = null, $loadExternal = true) {
    $this->solr = $solr;
    $this->loadExternal = $loadExternal;
    $this->starttime = microtime ( true );
    //check cmdi
    if($baseCMDIDirectory) {
      if (is_dir ( $baseCMDIDirectory )) {
        $this->baseCMDIDirectory = $baseCMDIDirectory;
      } else {
        echo ( "couldn't find base CMDI directory " . $baseCMDIDirectory ."\n");
      }
    } else {
      $this->baseCMDIDirectory = null;
    }
    //create database in memory
    $this->createDatabaseCMDI();
  }
  public function process($ref, $type, $ancestors = array()) {
    $this->counter = 0;
    $this->_process($ref, $type, $ancestors);
    $this->checkCommit(true);
  }
  private function _process($ref, $type, $ancestors = array()) {    
    if($cmdi = $this->getCMDI($ref)) {
      if ($xml = simplexml_load_string ( $cmdi )) {
        list($list, $ancestors) = $this->processCMDI ( $xml, $ref, $type, $ancestors);
        unset($xml);
        if($list && is_array($list)) {
          foreach($list AS $listItem) {
            $this->_process($listItem, $type, $ancestors);
          }
        }
      } else {
        echo ("couldn't process CMDI for ".$ref."\n");
      }
    } else {
      //echo("could not find ".$ref."\n");
    } 
  }
  private function getCMDI($ref) {
    if(isset($this->dbCMDI[$ref])) {
      $cmdiFile = $this->baseCMDIDirectory . DIRECTORY_SEPARATOR . $this->dbCMDI[$ref];
      if(file_exists($cmdiFile)) {
        $fp = fopen($cmdiFile, "r");
        return fread($fp, filesize($cmdiFile));
      } 
    } 
    if(count($this->dbCMDI)>0) {
      echo("Couldn't find ".$ref." on local filesystem\n");
    }
    if($this->loadExternal && preg_match("/^http\:\/\//",$ref)) {
      return $this->getURL($ref);
    }
    if($this->loadExternal && preg_match("/^hdl\:(.*?)$/",$ref,$match)) {
      $handleUrl = "http://hdl.handle.net/api/handles/".$match[1]."?type=URL";
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_URL, $handleUrl);
      $result = curl_exec($ch);
      curl_close($ch);
      if($object = json_decode($result)) {
        if(isset($object->values)&&isset($object->values[0]->data) && isset($object->values[0]->data->value)) {
          return $this->getURL($object->values[0]->data->value);
        }
      }
    }
    return null;
  }
  private function processCMDI($xml, $ref, $type, $ancestors = array()) {
    if (isset ( $xml->Components ) && is_object ( $xml->Components )) {
      $xmlObject = $xml->Components;
      if (isset ( $xmlObject->collection ) && is_object ( $xmlObject->collection )) {
        $xmlObject = $xmlObject->collection;
        if (isset ( $xmlObject->CollectionInfo ) && is_object ( $xmlObject->CollectionInfo )) {
          $xmlObject = $xmlObject->CollectionInfo;
          if (isset ( $xmlObject->Name ) && is_object ( $xmlObject->Name )) {
            $name = ( string ) $xmlObject->Name;
            return $this->processCollection ( $xml, $ref, $name , $ancestors);
          }
        }
      } else if (isset ( $xmlObject->{"talkbank-license-session"} ) && is_object ( $xmlObject->{"talkbank-license-session"} )) {
        $xmlObject = $xmlObject->{"talkbank-license-session"};
        if (isset ( $xmlObject->Session ) && is_object ( $xmlObject->Session )) {
          $xmlObject = $xmlObject->Session;
          if (isset ( $xmlObject->Resources ) && is_object ( $xmlObject->Resources )) {
            $xmlObject = $xmlObject->Resources;
            if (isset ( $xmlObject->WrittenResource ) && is_object ( $xmlObject->WrittenResource )) {
              $xmlObject = $xmlObject->WrittenResource;
              $attributes = $xmlObject->attributes ();
              if ($attributes->{"ref"} == "Transcript_file") {
                if (isset ( $xmlObject->ResourceLink )) {
                  $link = ( string ) $xmlObject->ResourceLink;
                  if (preg_match ( "/^http:\/\/childes.talkbank.org\/data-orig\/(.*?)\.cha$/", $link, $match )) {
                    return $this->processResource ( $xml, $ref, $type, $match [1] . ".xml" , $ancestors);
                  }
                }
              }
            }
          }
        }
      }
    }
    return array(false, $ancestors);
  }
  private function processCollection($xml, $ref, $name, $ancestors = array()) {
    //create data
    $data = array();
    $data["id"] = $ref;
    $data["type"] = "collection";
    $data["title"] = $name;
    if(count($ancestors)>0) {
      $data["collectionId"] = array();
      $data["collectionName"] = array();
      foreach($ancestors AS $ancestor) {
        $data["collectionId"][] = $ancestor["ref"];
        $data["collectionName"][] = $ancestor["name"];
      }
    }
    //add to index
    $result = $this->solr->post(json_encode(array($data)));
    if(!$result || isset($result["error"])) {
      echo "\n" . ($this->getTime () . " - === SOLR-problem ===\n");
      echo var_export ( $result, true );
      echo $this->getTime () . " - ===\n" . var_export($data, true) . "\n===\n";
    }
    $this->counter++;
    $this->checkCommit();
    //collect resources
    $ancestors[] = array("name" => $name, "ref" => $ref);
    $list = array ();
    if (isset ( $xml->Resources ) && is_object ( $xml->Resources )) {
      $xmlObject = $xml->Resources;
      if (isset ( $xmlObject->ResourceProxyList ) && is_object ( $xmlObject->ResourceProxyList )) {
        $xmlObject = $xmlObject->ResourceProxyList;
        foreach ( $xmlObject->ResourceProxy as $item ) {
          if (isset ( $item->ResourceType ) && isset ( $item->ResourceRef ) && $item->ResourceType == "Metadata") {
            $list [] = ( string ) $item->ResourceRef;
          }
        }
      }
    }
    return array($list, $ancestors);
  }
  private function processResource($xml, $ref, $type, $name, $ancestors = array()) {
    //create data
    $data = array();
    $data["id"] = $ref;
    $data["type"] = "resource";
    $data["resource_mtas"] = $name;
    $data["resource_mtas_type"] = $type;
    if(count($ancestors)>0) {
      $data["collectionId"] = array();
      $data["collectionName"] = array();
      foreach($ancestors AS $ancestor) {
        $data["collectionId"][] = $ancestor["ref"];   
        $data["collectionName"][] = $ancestor["name"];   
      }
    }
    $mappingResourceActor = array(
        array(array("Role"), "actorRole", "string"),
        array(array("Name"), "actorName", "string"),
        array(array("FullName"), "actorFullName", "string"),
        array(array("Code"), "actorCode", "string"),
        array(array("FamilySocialRole"), "actorFamilySocialRole", "string"),
        array(array("EthnicGroup"), "actorEthnicGroup", "string"),
        array(array("Age"), "actorAge", "string"),
        array(array("BirthDate"), "actorBirthDate", "string"),
        array(array("Sex"), "actorSex", "string"),
        array(array("Education"), "actorEducation", "string"),
        array(array("Anonymized"), "actorAnonymized", "string"),
        array(array("Contact", "Name"), "actorContactName", "string"),
        array(array("Contact","Address"), "actorContactAddress", "string"),
        array(array("Contact","Email"), "actorContactEmail", "string"),
        array(array("Contact","Organisation"), "actorContactOrganisation", "string"),
        array(array("Actor_Languages","Actor_Language","Id"), "actorLanguageId", "string"),
        array(array("Actor_Languages","Actor_Language","Name"), "actorLanguageName", "string"),
        array(array("Actor_Languages","Actor_Language","MotherTongue"), "actorLanguageMotherTongue", "string"),
        array(array("Actor_Languages","Actor_Language","PrimaryLanguage"), "actorLanguagePrimaryLanguage", "string"),
    );
    $mappingResourceMain = array(
        array(array("License","DistributionType"), "distributionType", "string"),
        array(array("License","LicenseName"), "licenseName", "string"),
        array(array("License","LicenseURL"), "licenseURL", "string"),
        array(array("Session","Name"), "name", "string"),
        array(array("Session","Title"), "title", "string"),
        array(array("Session","Date"), "date", "date"),
        array(array("Session","MDGroup","Location","Continent"), "locationContinent", "string"),
        array(array("Session","MDGroup","Location","Country"), "locationCountry", "string"),
        array(array("Session","MDGroup","Location","Region"), "locationRegion", "string"),
        array(array("Session","MDGroup","Location","Address"), "locationAddress", "string"),
        array(array("Session","MDGroup","Project","Name"), "projectName", "string"),
        array(array("Session","MDGroup","Project","Title"), "projectTitle", "string"),
        array(array("Session","MDGroup","Project","Id"), "projectId", "string"),
        array(array("Session","MDGroup","Project","Contact","Name"), "projectContactName", "string"),
        array(array("Session","MDGroup","Project","Contact","Address"), "projectContactAddress", "string"),
        array(array("Session","MDGroup","Project","Contact","Email"), "projectContactEmail", "string"),
        array(array("Session","MDGroup","Project","Contact","Organisation"), "projectContactOrganisation", "string"),
        array(array("Session","MDGroup","Project","descriptions","Description"), "projectDescription", "string"),
        array(array("Session","MDGroup","Content","Genre"), "contentGenre", "string"),
        array(array("Session","MDGroup","Content","SubGenre"), "contentSubGenre", "string"),
        array(array("Session","MDGroup","Content","Task"), "contentTask", "string"),
        array(array("Session","MDGroup","Content","Modalities"), "contentModalities", "string"),
        array(array("Session","MDGroup","Content","Subject"), "contentSubject", "string"),
        array(array("Session","MDGroup","Content","CommunicationContext","Interactivity"), "contentCommunicationContextInteractivity", "string"),
        array(array("Session","MDGroup","Content","CommunicationContext","PlanningType"), "contentCommunicationContextPlanningType", "string"),
        array(array("Session","MDGroup","Content","CommunicationContext","Involvement"), "contentCommunicationContextInvolvement", "string"),
        array(array("Session","MDGroup","Content","CommunicationContext","SocialContext"), "contentCommunicationContextSocialContext", "string"),
        array(array("Session","MDGroup","Content","CommunicationContext","EventStructure"), "contentCommunicationContextEventStructure", "string"),
        array(array("Session","MDGroup","Content","Content_Languages","Content_Language", "Id"), "contentLanguageId", "string"),
        array(array("Session","MDGroup","Content","Content_Languages","Content_Language", "Name"), "contentLanguageName", "string"),
        array(array("Session","MDGroup","Content","Content_Languages","Content_Language", "Dominant"), "contentLanguageDominant", "string"),
        array(array("Session","MDGroup","Content","Content_Languages","Content_Language", "SourceLanguage"), "contentLanguageSource", "string"),
        array(array("Session","MDGroup","Content","Content_Languages","Content_Language", "TargetLanguage"), "contentLanguageTarget", "string"),
        array(array("Session","MDGroup","Actors","Actor"), array("Code"), "object", $mappingResourceActor),
        array(array("Session","Resources","WrittenResource","Access","Availability"), "accessAvailability", "string"),
        array(array("Session","Resources","WrittenResource","Access","Owner"), "accessOwner", "string"),
        array(array("Session","Resources","WrittenResource","Access","Publisher"), "accessPublisher", "string"),
        array(array("Session","References","descriptions","Description"), "description", "string"),
        
    );
    $data = $this->addMappings($mappingResourceMain, $xml->Components->{"talkbank-license-session"}, $data);
    //add to index
    $result = $this->solr->post(json_encode(array($data)));
    if(!$result || isset($result["error"])) {
      echo "\n" . ($this->getTime () . " - === SOLR-problem ===\n");
      echo var_export ( $result, true );
      echo $this->getTime () . " - ===\n" . var_export($data, true) . "\n===\n";
    }
    $this->counter++;
    $this->checkCommit();
    return array(false, $ancestors);
  }
  
  private function checkCommit($force=false) {
    if($this->counter>$this->counterMaximum || ($force && $this->counter>0)) {
      sleep(8);
      $this->solr->commit();
      sleep(2);
      if (! $this->solr->checkStatus ()) {
        $checkCounter = 0;
        echo ("Re-commit and timeout for " . $this->timeout . " seconds\n");
        while ( ! $this->solr->checkStatus ( ) ) {
          if ($checkCounter >= 5) {
            $this->solr->commit ();
            $checkCounter = 0;
            echo ("Re-commit and timeout for " . $this->timeout. " seconds\n");
          } else {
            echo ("Timeout for " . $this->timeout. " seconds\n");
          }
          sleep ( $this->timeout);
          $checkCounter ++;
        }
      }
      $this->counter = 0;
    }
  }
  
  private function addMappings($mapping, $xml, $data = array(), $postfix="") {
    foreach($mapping AS $mappingItem) {
      $data = $this->addMappingItem($mappingItem, $xml, 0, $data, $postfix);
    }
    return $data;
  }
  private function addMappingItem($mappingItem, $xmlObject, $level=0, $data = array(), $postfix="") {
    if($level<count($mappingItem[0])) {
      if(!is_object($xmlObject) || !isset($xmlObject->{$mappingItem[0][$level]})) {
        return $data;
      } else {
        foreach($xmlObject->{$mappingItem[0][$level]} AS $xmlSubObject) {
          $data = $this->addMappingItem($mappingItem, $xmlSubObject, $level+1, $data, $postfix);
        }
        return $data;
      }
    } else if($xmlObject) {
      if($mappingItem[2]=="object") {
        $data = $this->addMappings($mappingItem[3], $xmlObject, $data); 
        $subData = $this->addMappingItem(array($mappingItem[1], "postfix", "string"), $xmlObject);
        if(isset($subData["postfix"])) {
          $data = $this->addMappings($mappingItem[3], $xmlObject, $data, $postfix."_".$subData["postfix"]);
        }
      } else if(($value = $this->mappingValue($xmlObject, $mappingItem[2])) !=null) {
        $key = $mappingItem[1].$postfix;
        if(isset($data[$key])) {
          if(!is_array($data[$key])) {
            if($data[$key]!=$value) {
              $data[$key] = array($data[$key]);
              $data[$key][] = $value;
            }  
          } else if(!in_array($value, $data[$key])) {
            $data[$key][] = $value;
          }
        } else {
          $data[$key] = $value;
        }
      }  
    }
    return $data;
  }
  private function mappingValue($xml, $type) {
    if($type=="string" ) {
      $value = (String) $xml;
      if($value!=null && $value!="") {
        return $value;
      } else {
        return null;
      }
    } else if($type=="date") {
      $value = (String) $xml;
      if($value!=null && preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/", $value)) {
        return $value."T00:00:00Z";
      } else {
        return null;
      }
    } else {
      return null;
    }
  }
  
  private function createDatabaseCMDI() {
    $this->dbCMDI = array();
    $this->processCMDIDirectory();
    echo("CMDI-database: ".count($this->dbCMDI)." items\n");
  }
  
  private function processCMDIDirectory($directory = "") {
    if($this->baseCMDIDirectory) {
      $fullDirectory = $this->baseCMDIDirectory;
      if($directory!="") {
        $fullDirectory.=DIRECTORY_SEPARATOR.$directory;
      }
      if ($dh = opendir ( $fullDirectory )) {
        while ( ($file = readdir ( $dh )) !== false ) {
          if (is_file ( $fullDirectory . DIRECTORY_SEPARATOR. $file )) {
            if(preg_match ( "/\.cmdi$/i", $file, $match )) {
              //get PID from first characters
              if (!($fp = fopen($fullDirectory . DIRECTORY_SEPARATOR. $file, "r"))) {
                die("could not open CMDI input ".$directory. DIRECTORY_SEPARATOR.$file);
              } else if($data = fread($fp, 1024)) {
                if(preg_match("/<MdSelfLink>([^<]*)<\/MdSelfLink>/msi", $data, $match)) {
                  $this->dbCMDI[$match[1]] = $directory. DIRECTORY_SEPARATOR . $file;
                } else {
                  die("no MdSelfLink found in ".$directory. DIRECTORY_SEPARATOR . $file."\n");
                }
              } else {
                die("problem while reading ".$directory. DIRECTORY_SEPARATOR.$file);
              }
            }
          } else if (is_dir ( $fullDirectory. DIRECTORY_SEPARATOR.$file )) {
            if ($file != "." && $file != "..") {
              $this->processCMDIDirectory ( $directory. DIRECTORY_SEPARATOR.$file );
            }
          }
        }
      } else {
        die("couldn't process ".$directory);
      }      
    } 
  }
  
  private function getTime() {
    $time = microtime ( true ) - $this->starttime;
    return sprintf ( "%09.4f", $time ) . " " . memory_get_usage () . " " . memory_get_peak_usage ();
  }
  
  private function getURL($url) {    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
  }
  
  
}

?>