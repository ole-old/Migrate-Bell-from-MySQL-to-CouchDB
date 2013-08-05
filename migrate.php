<?php
require_once 'PHP-on-Couch-master/lib/couch.php';
require_once 'PHP-on-Couch-master/lib/couchClient.php';
require_once 'PHP-on-Couch-master/lib/couchDocument.php';


$tables = ["resources","student", "lessonPlan"]

// GO!
migrateMysqlToCouch();


function migrateMysqlToCouch() {
  
  // Get the mysql entries
  foreach($tables as $table) {
    $mysqlEntries = getMysqlEntries($table);
    // Map their schema to what we'll use in CouchDB
    $couchEntries = mapBellSchema($mysqlEntries, $table);
    // Save the content to CouchDB
    saveCouchDocs($couchEntries);
  }
}



function getMysqlEntries($table) {

  $mysqli = new mysqli("localhost", "root", "", "schoolBell");

  // Get our resources from MySQL
  $results = $mysqli->query("SELECT * FROM $table");

  // Get those resouces into an array
  while($mysqlEntries[] = $results->fetch_row()) { }

  return $mysqlEntries;
}



function saveCouchDocs($docs, $table) {

  $couch = new couchClient('http://127.0.0.1:5984', 'migration');

  foreach ($docs as $doc) {

    // Save the doc
    try {
      $response = $couch->storeDoc($doc);
    } catch (Exception $e) {
      echo "ERROR: ".$e->getMessage()." (".$e->getCode().")<br>\n";
    }

    if($table == "resources") {
      // Add the attachment
      try { 
        $file_path = "/var/www/resources/" . $doc->legacy['id'] . "." . $doc->legacy['type'];
        $ok = $couch->storeAttachment($couch->getDoc($response->id), $file_path, mime_content_type($file_path));
      } catch (Exception $e) {
        echo "ERROR: ".$e->getMessage()." (".$e->getCode().")<br>\n";
      }    
    }

    $success[] = $ok;

  }
  
  print("Files migrated: " . count($success));

}


function mapBeLLSchema($entries, $table) {
  $mapped = array();
  switch($table) {
    case 'resources':
      foreach($entries as $entry) {
      $n = new stdClass();
      // save legacy information for migration and in case we need it later
      $n->legacy = array(
        "id" => $entry[1],
        "type" => $entry[5]
      );
      $n->kind = "resource";
      $n->title = $entry[3];
      $n->author = ""; 
      $n->subject = strtolower($entry[2]);
      $n->created = $entry[7];
      $n->community = $entry[15];
      $n->TLR = $entry[16];
      if ($entry[8]) $n->levels[] = "KG";
      if ($entry[9]) $n->levels[] = "P1";
      if ($entry[10]) $n->levels[] = "P2";
      if ($entry[11]) $n->levels[] = "P3";
      if ($entry[12]) $n->levels[] = "P4";
      if ($entry[13]) $n->levels[] = "P5";
      if ($entry[14]) $n->levels[] = "P6";
      $mapped[] = $n;
    break;
    case 'students':

    break;
  }


  return $mapped;

}
?>
