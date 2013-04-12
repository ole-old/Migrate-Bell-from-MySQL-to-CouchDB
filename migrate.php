<?php
require_once 'PHP-on-Couch-master/lib/couch.php';
require_once 'PHP-on-Couch-master/lib/couchClient.php';
require_once 'PHP-on-Couch-master/lib/couchDocument.php';


// GO!
migrateMysqlToCouch();


function migrateMysqlToCouch() {
  
  // Get the mysql entries
  $mysqlEntries = getMysqlEntries();

  // Map their schema to what we'll use in CouchDB
  $couchEntries = mapBellSchema($mysqlEntries);
  
  // Save the content to CouchDB
  saveCouchDocs($couchEntries);

}



function getMysqlEntries() {

  $mysqli = new mysqli("localhost", "root", "", "schoolBell");

  // Get our resources from MySQL
  $results = $mysqli->query("SELECT * FROM resources");

  // Get those resouces into an array
  while($mysqlEntries[] = $results->fetch_row()) { }

  return $mysqlEntries;
}



function saveCouchDocs($docs) {

  $couch = new couchClient('http://127.0.0.1:5984', 'migration');

  foreach ($docs as $doc) {

    // Save the doc
    try {
      $response = $couch->storeDoc($doc);
    } catch (Exception $e) {
      echo "ERROR: ".$e->getMessage()." (".$e->getCode().")<br>\n";
    }

    // Add the attachment
    try { 
      $file_path = "/var/www/resources/" . $doc->legacy['id'] . "." . $doc->legacy['type'];
      $ok = $couch->storeAttachment($couch->getDoc($response->id), $file_path, mime_content_type($file_path));
    } catch (Exception $e) {
      echo "ERROR: ".$e->getMessage()." (".$e->getCode().")<br>\n";
    }    
    
    $success[] = $ok;

  }
  
  print("Files migrated: " . count($success));

}



/*
 *  Map the result to the schema we'll use in Couch

  mysql> describe resources;
  +-------------+-------------+------+-----+-------------------+----------------+
  | Field       | Type        | Null | Key | Default           | Extra          |
  +-------------+-------------+------+-----+-------------------+----------------+
  | colNum      | bigint(8)   | NO   | MUL | NULL              | auto_increment |
  | resrcID     | varchar(10) | NO   | PRI | NULL              |                |
  | subject     | varchar(15) | NO   |     | NULL              |                |
  | title       | text        | NO   |     | NULL              |                |
  | description | text        | NO   |     | NULL              |                |
  | type        | varchar(5)  | NO   |     | NULL              |                |
  | url         | text        | NO   |     | NULL              |                |
  | dateAdded   | timestamp   | NO   |     | CURRENT_TIMESTAMP |                |
  | KG          | varchar(3)  | NO   |     | NO                |                |
  | P1          | varchar(3)  | NO   |     | NO                |                |
  | P2          | varchar(3)  | NO   |     | NO                |                |
  | P3          | varchar(3)  | NO   |     | NO                |                |
  | P4          | varchar(3)  | NO   |     | NO                |                |
  | P5          | varchar(3)  | NO   |     | NO                |                |
  | P6          | varchar(3)  | NO   |     | NO                |                |
  | Community   | varchar(3)  | NO   |     | NO                |                |
  | TLR         | varchar(3)  | NO   |     | NO                |                |
  +-------------+-------------+------+-----+-------------------+----------------+ 

 */

function mapBellSchema($entries) {
  $mapped = array();
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
  }

  return $mapped;

}
?>
