<?php
require_once 'PHP-on-Couch-master/lib/couch.php';
require_once 'PHP-on-Couch-master/lib/couchClient.php';
require_once 'PHP-on-Couch-master/lib/couchDocument.php';

$couch = new couchClient('http://127.0.0.1:5984', 'schoolbell');
$mysqli = new mysqli("localhost", "root", "", "schoolBell");


// GO!
migrateMysqlToCouch();


function migrateMysqlToCouch() {

	// Get our resources from MySQL
	$results = $mysqli->query("SELECT * FROM resources");

	// Get those resouces into an array
	while($mysqlEntries[] = $results->fetch_row()) { }

	// Map their schema to what we'll use in CouchDB
	$couchEntries = mapBellSchema($mysqlEntries);
	
	// Save the content to CouchDB
	saveCouchDocs($couchEntries);

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
	foreach($entries as $o) {
		$n = (object);
		
		

	}

}
?>
