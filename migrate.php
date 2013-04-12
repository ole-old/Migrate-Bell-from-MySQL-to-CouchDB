<?php
require_once 'PHP-on-Couch-master/lib/couch.php';
require_once 'PHP-on-Couch-master/lib/couchClient.php';
require_once 'PHP-on-Couch-master/lib/couchDocument.php';


// GO!
migrateMysqlToCouch();


function migrateMysqlToCouch() {

  $couch = new couchClient('http://127.0.0.1:5984', 'schoolbell');
  $mysqli = new mysqli("localhost", "root", "", "schoolBell");

  // Get our resources from MySQL
  $results = $mysqli->query("SELECT * FROM resources");

  // Get those resouces into an array
  while($mysqlEntries[] = $results->fetch_row()) { }

  //print_r($mysqlEntries);

  // Map their schema to what we'll use in CouchDB
  $couchEntries = mapBellSchema($mysqlEntries);
  
  print(count($couchEntries));
  $i=0;
  while($i < 20) {
    print_r($couchEntries[$i]);
    $i++;
  }



  // Save the content to CouchDB
  // saveCouchDocs($couchEntries);

}


function saveCouchDocs($docs) {
  foreach ($docs as $doc) {
    // Save the doc
    $doc = new couchDocument($client);
    // Add the attachment
    $ok = $client->storeAttachment($doc,'/etc/resolv.conf','text/plain', 'my-resolv.conf');
    print_r($ok);
  }
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

  => 

  {
     "_id": "3b847bc22ba3a28994187d60b2000c7b",
     "_rev": "4-c67a223e57f97782c6907e5cf19aecf2",
     "kind": "resource",
     "title": "Another book",
     "author": "Ken",
     "subject": "english",
     "created": "168846...",
     "community": "not sure what this is",
     "TLR": "not sure what this is",
     "levels": [
         "p2",
         "p3"
     ],
     "_attachments": {
         "2011-10-21-rjsteinert-PDX-to-OAK.pdf": {
       "content_type": "application/pdf",
       "revpos": 3,
       "digest": "md5-zfT1fKU1I7o/3on3voJYwQ==",
       "length": 275053,
       "stub": true
         }
     }
  }

 */

function mapBellSchema($entries) {
  $mapped = array();
  foreach($entries as $entry) {
    $n = new stdClass();
    $n->id = $entry[1];
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
