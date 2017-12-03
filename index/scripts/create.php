<?php
//create "http://alpha.talkbank.org/data-cmdi/childes-data.cmdi" "childes" "http://localhost:8983/solr/" 
if (php_sapi_name() == "cli") {
  if($argc==4 || $argc==5) {
    $cmdiRef = $argv[1];
    $dataType = $argv[2];
    $solrUrl = $argv[3];
    if($argc==5) {
      $baseCMDIDirectory= $argv[4];
    } else {
      $baseCMDIDirectory= null;
    }
  } else {
    die("USAGE: ".basename(__FILE__)." <REF CMDI> <TYPE> <URL SOLR-CORE> (<DIRECTORY CMDI>)\n");
  }
  require_once("Solr.class.php");
  require_once("TalkBank.class.php");  
  $solr = new Solr($solrUrl, $dataType);  
  $talkBank = new TalkBank($solr, $baseCMDIDirectory, false);  
  $solr->deleteAll();
  $talkBank->process($cmdiRef, $dataType);
} else {
  die("only use CLI");
}

?>