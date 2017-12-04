<?php

// ================
// AUTHENTICATION
// ================
$authentication = array (
    // =================================================
    // access based on ip as listed in example:
    // =================================================
    "ip" => array (
        // array(
        // "name" => "localhost",
        // "ip" => "127.0.0.1/24",
        // ),
        array(
          "name" => "everyone",
          "ip" => "0.0.0.0/0",
        )
    ),
    // =================================================
    // access based on login as listed in example:
    // =================================================
    "login" => array (
        array (
            "name" => "Administrator",
            "login" => "admin",
            "password" => "\$6\$rounds=5000\$775891cd901db4f9\$p9fzJx./1jbSrk8CK7oPqTgbsx7hFNfY0a8mdED5efLqlhebrUI95pcfCF.0O2TvC16mKWZC6kdbWhFLC2Hqd/",
            "admin" => true 
        ) 
    ),
    // =================================================
    // access based on key as listed in example:
    // =================================================
    "key" => array (
      // array(
      // "name" => "test key",
      // "key" => "1234567890",
      // ),
    ) 
);

// ================
// SOLR
// ================
$solr = array (
    // ==========================================
    // example configuration named 'demoConfig'
    // ==========================================
    "childes" => array (
        "url" => "http://localhost:8983/solr/childes/" ,      
      "exampleFieldText"=> "description", // optional: preferred field examples
      // "exampleFieldTextValues"=>array("koe","paard","schaap","geit","kip","ezel","konijn","cavia","muis","rat"),
      "exampleFieldInteger"=> "resource_mtas_numberOfPositions", // optional: preferred field examples
      // "exampleFieldTextValues"=>null, //autofill
      "exampleFieldString"=> "title", // optional: preferred field examples
      // "exampleFieldStringValues"=>null, //autofill
      "exampleFieldMtas" => "resource_mtas", // optional: preferred field examples
      "exampleMtasPrefixWord"=> "w", // optional: preferred prefix examples
      // "exampleMtasPrefixWordValues"=>array("koe","paard","schaap","geit","kip","ezel","konijn","cavia","muis","rat"),
      "exampleMtasPrefixLemma"=> "lemma", // optional: preferred prefix examples
      // "exampleMtasPrefixLemmaValues"=>array("boom","struik","gras","plant","bloem","aarde","wortel","blad","hout","mest"),
      "exampleMtasPrefixPos"=> "pos.c", // optional: preferred prefix examples
      // "exampleMtasPrefixPosValues"=>null,
    ) 
  // "config2" => array (
  // "url" => "http://localhost:8983/solr/core1/" // obligatory: url solr core
  // ),
);

?>