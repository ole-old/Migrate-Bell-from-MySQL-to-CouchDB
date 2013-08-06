<?php


/*
 *
 * Setup dependencies and environment
 *
 */


// MySQL
$mysqli = new mysqli("localhost", "root", "raspberry", "schoolBell");

// CouchDB
require_once 'PHP-on-Couch-master/lib/couch.php';
require_once 'PHP-on-Couch-master/lib/couchClient.php';
require_once 'PHP-on-Couch-master/lib/couchDocument.php';
$couchClient = new couchClient('http://127.0.0.1:5984');
// database: resources
// document kinds: Resource
// from sync: only push
$Resources = new couchClient('http://127.0.0.1:5984', 'resources'); 
// database: log
// documents kinds: Action
// from sync: only pull
$Actions = new couchClient('http://127.0.0.1:5984', 'actions'); 
// database: members
// document kinds: Member
// from sync: push and pull
$Members = new couchClient('http://127.0.0.1:5984', 'members'); 
// database: syncs
// document kinds: Sync
// from sync device: pull
$Syncs = new couchClient('http://127.0.0.1:5984', 'syncs');

$Questions = new couchClient('http://127.0.0.1:5984', 'questions'); 
$Feedbacks = new couchClient('http://127.0.0.1:5984', 'feedbacks');
$Groups = new couchClient('http://127.0.0.1:5984', 'groups');
$Facilities = new couchClient('http://127.0.0.1:5984', 'facilities');









/*
 *
 *
 * Set up global variables and defaults
 *
 *
 */

date_default_timezone_set('UTC'); 



// We're going to consolidate the Lead Teacher accounts, of which they have two, into one account with two roles.
$leadTeacherAccountConsolidationMap = [
  ['Christian Adjabeng-Leadteacher', 'Adjabeng Christian -P5', 'Adjabeng Christian'], // Sacred Heart
  ['lydia sarfo - leadteacher', 'lydia sarfo p1', 'lydia sarfo'], // Akuiakrom
  ['Charlotte Akpaglo-Lead Teacher', 'Charlotte Akpaglo-P6', 'Charlotte Akpaglo'], // Ayikaidoblo
  ['Cephas-Lead Teacher', 'Cephas Agbai-Kude -P6', 'Cephas Agbai-Kude'], // Katapor
  ['ADWOA K ONADU - LEAD TEACHER', 'ADWOA K AGYEPONG-P3B', 'ADWOA K AGYEPONG'], // Mamobi
  ['Joshua Opata', 'Joshua Opata - P4', 'Joshua Opata'], // Ogua
  ['Benjamin Dodoo-Lead Teacher', 'Benjamin Dodoo-P2', 'Benjamin Dodoo'], // Pokwasi
   // nothing for Sam Sam
  ['Olivia Ahiayibor - lead teacher', 'Olivia Ahiayibor-P5', 'Olivia Ahiayibor'], // Sapeiman
  ['MISS SERWAH-LEAD TEACHER', 'MISS NKANSAH - P3', 'MISSxNSAH'] // Saint Anthony
];

// Person field was used in the action_log table to reference a user.  We'll want to capture a map of id to names when creating member records so we can migrate the action_log to action records with the correct memberId.
$idToPersonMap = [];
// @todo We need to fill this out in the mapping of teacherClass




/*
 *
 *
 * Phase One
 *
 *
 */

// Get record from schoolDetails so we can set Facility and get FacilityId for other documents that will reference the current facility
$result = $mysqli->query("SELECT * FROM schoolDetails");
$schoolDetails = $result->fetch_object();
$result->close();
$doc = new couchDocument($client);
$doc->set(array(
  "kind"     => "Facility",
  "type"     => $schoolDetails->schoolType,
  "GPS"      => array("", ""),
  "phone"    => "",
  "name"     => $schoolDetails->schoolName,
  "country"  => "Ghana",
  "region"   => $schoolDetails->location,
  "district" => "",
  "area"     => "",
  "street"   => ""
));
$facilityId = $doc->_id;


// Create the whoami/facility doc

$whoami = new couchClient('http://127.0.0.1:5984', 'whoami');
$whoamiFacility = new couchDocument($whoami);
$whoamiFacility->set(array(
  "id" => "facility",
  "kind" => "system",
  "facilityId" => $facilityId,
  "timezone" => "GMT",
  "language" => "EN",
  "version" => "2.0",
  "layout" => 1
));



// Create default groups in Couch

$levelToGroupIdMap = array(
  "KG" => "",
  "P1" => "",
  "P2" => "",
  "P3" => "",
  "P4" => "",
  "P5" => "",
  "P6" => ""
);

foreach($levelToGroupIdMap as $key => $id) {
  $n = new stdClass();
  // @todo Get an id from Couch
  $n->_id = $couchClient->getUuids(1)[0];
  $n->kind = "Group";
  $n->name = $key;
  $n->level = $key;
  $n->members = array();
  $n->owner = array();
  $n->facilityId = $facilityId;
  $levelToGroupIdMap[$key] = $n->_id;
}
saveCouchDocs($groups);






/*
 *
 *
 * Phase Two
 *
 *
 */

$tables = ["teacherClass", "student", "resources", "LessonPlan", "feedback", "VBQuestion", "action_log" ]

phaseTwo();


/*
 *
 *
 * -=-=-=-=-= Functions -=-=-=-=-
 *
 *
 */

function phaseTwo() {
  
  // Get the mysql entries
  foreach($tables as $table) {
    $mysqlEntries = getMysqlEntries($table);
    // Map their schema to what we'll use in CouchDB
    $couchEntries = mapBellSchema($mysqlEntries, $table);
    // Save the content to CouchDB
    saveCouchDocs($couchEntries, $table);
  }
}



function getMysqlEntries($table) {

  // Get our resources from MySQL
  $results = $mysqli->query("SELECT * FROM $table");

  // Get those resouces into an array
  while($mysqlEntries[] = $results->fetch_row()) { }

  return $mysqlEntries;
}



function saveCouchDocs($docs) {
  foreach($docs as $doc) {
    switch ($doc->kind) {

      // @todo BLOCKER Write the case for each kind:""

      case 'Resource':
        //
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
      break;
      case 'Sync':
      
      break;
    }
  }

 
  
  print("Files migrated: " . count($success));

}


function mapBeLLSchema($entries, $table) {
  $mapped = array();
  switch($table) {

    // LessonPlan table -> kind:LessonPlan documents
    case 'LessonPlan':
      foreach($entries as $entry) {
        $n = $entry 
        $n->kind = "LessonPlan";
        $n->Pre_Writing_or_Reading = $n->Pre_Writing;
        unset($n->Pre_Writing);
        $n->Writing_or_Reading = $n->Writing;
        unset($n->Writing);
        $n->Post_Writing_or_Reading = $n->Post_Writing;
        unset($n->Post_Writing);
        $mapped[] = $n
      }
    break;

    // resources table -> kind:Resource documents
    case 'resources':
      foreach($entries as $entry) {
      $n = new stdClass();
      // save legacy information for migration and in case we need it later
      $n->legacy = array(
        "id" => $entry[1],
        "type" => $entry[5]
      );
      $n->kind = "Resource";
      $n->title = $entry[3];
      $n->author = ""; 
      $n->subject = strtolower($entry[2]);
      $n->created = $entry[7];
      // @todo BLOCKER Clean up review
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
    
    case 'usedResources':

      // usedResources table -> kind:Feedback documents
      foreach($entries as $entry) {
        $n = new stdClass();
        $n->kind = "Feedback";
        $n->rating = $entry->rating;
        $n->comment = "";
        $n->facilityId = $facilityId;
        $n->memberId = $entry->usedby;
        $n->resourceId = $entry->resrcID;
        $n->timestamp = $entry->dateUsed;
        $n->context = array(
          subject => $entry->subject,
          use => "stories for the week",
          level => $entry->class
        ); 
        $mapped[] = $n
      }

      // usedResources table -> kind:Sync, useContext:"Stories for the week" documents
      foreach($entries as $entry) {
        $n = new stdClass();
        $n->kind = "Sync";
        $n->useContext = "stories for the week";
        // @todo Get group from $groups, 
        $n->group = $groups[$entry->class];
        $mapped[] = $n
      }

    break;

    // teacherClass table -> kind:Member documents
    case 'teacherClass' :
      foreach($entries as $entry) {
        if($entry->role=="Leadteacher"){
          // @todo use $leadTeacherMap to consolidate accounts
        }
        else {
          $n = new stdClass();
          // @todo get ID from Couch 
          $n->_id = $couchClient->getUuids(1)[0];
          // Transform into kind: Members, role: Teacher
          $n->login = $entry->loginId;
          $n->kind = "Member";
          $n->facilityId = $facilityId;
          $n->role = array(strtolower($entry->Role));
          $n->pass = $entry->pswd;
          $n->level = array($entry->classAssign);  // No good equivalent
          $n->dateRegistered = "";
          $n->dateOfBirth = "";
          $n->gender = "";
          // Break out the Name
          $nameArray = explode(" ", $entry->Name);
          $n->firstName = $nameArray[0];
          $n->lastName = $nameArray[count($nameArray)-1];
          $nameArray = array_shift($nameArray);
          $nameArray = array_pop($nameArray);
          $n->middleNames = implode(' ', $nameArray);
          // @todo BLOCKER Add id to owner array in documents of kind:Group 
        }
      }
    break;

    // students table -> kind:Member documents
    case 'students' :
      foreach($entries as $entry) {
        $n = new stdClass();
        $n->_id = $couchClient->getUuids(1)[0];
        $n->kind = "Member";
        $n->role = array("student");
        // no login for students
        $n->facilityId = $facilityId;
        $n->pass = $entry->stuCode;
        $n->level = array($entry->stuClass);  // No good equivalent
        $n->dateRegistered = strtotime($entry->DateRegistered); // There's timezone issues here
        $n->dateOfBirth = strtotime($entry->stuDOB);
        // Break out the Name
        $nameArray = explode(" ", $entry->Name);
        $n->gender = $entry->stuGender;
        $n->firstName = $nameArray[0];
        $n->lastName = $nameArray[count($nameArray)-1];
        $nameArray = array_shift($nameArray);
        $nameArray = array_pop($nameArray);
        if(count($nameArray) > 2) {
          $n->middleNames = implode(' ', $nameArray);
        }
        // @todo BLOCKER Add id to members array in documents of kind:Group 
        $mapped[] = $n;
      }
    break;
    // @todo feedback table -> kind:Action, context:pbell documents
    // @todo action_log table -> kind:Action, context:lms documents
    case 'action_log' : 
      // @todo When filling out $n->memberId, If $entry->person is in the $idToPersonMap, we'll want to consolidate

    break;
  }
  return $mapped;

}
?>
