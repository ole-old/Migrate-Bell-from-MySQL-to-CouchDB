<?php















/*
 *
 * Setup dependencies and environment
 *
 */


// MySQL
global $mysqli;
$mysqli = new mysqli("localhost", "root", "raspberry", "schoolBell");

// CouchDB
require_once 'PHP-on-Couch-master/lib/couch.php';
require_once 'PHP-on-Couch-master/lib/couchClient.php';
require_once 'PHP-on-Couch-master/lib/couchDocument.php';
global $couchUrl;
$couchUrl = 'http://127.0.0.1:5984';
global $couchClient;
$couchClient = new couchClient($couchUrl);














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
  // [ Lead Teacher Account, Other Account, Name for Consolidated Account ]
  ['Christian Adjabeng-Leadteacher', 'Adjabeng Christian -P5', 'Adjabeng Christian'], // Sacred Heart
  ['lydia sarfo - leadteacher', 'lydia sarfo p1', 'lydia sarfo'], // Akuiakrom
  ['Charlotte Akpaglo-Lead Teacher', 'Charlotte Akpaglo-P6', 'Charlotte Akpaglo'], // Ayikaidoblo
  ['Cephas-Lead Teacher', 'Cephas Agbai-Kude -P6', 'Cephas Agbai-Kude'], // Katapor
  ['ADWOA K ONADU - LEAD TEACHER', 'ADWOA K AGYEPONG-P3B', 'ADWOA K AGYEPONG'], // Mamobi
  ['Joshua Opata', 'Joshua Opata - P4', 'Joshua Opata'], // Ogua
  ['Benjamin Dodoo-Lead Teacher', 'Benjamin Dodoo-P2', 'Benjamin Dodoo'], // Pokwasi
   // nothing for Sam Sam
  ['Olivia Ahiayibor - lead teacher', 'Olivia Ahiayibor-P5', 'Olivia Ahiayibor'], // Sapeiman
  ['MISS SERWAH-LEAD TEACHER', 'MISS NKANSAH - P3', 'MISS NKANSAH'] // Saint Anthony
];

// Person field was used in the action_log table to reference a user.  We'll want to capture a map of id to names when creating member records so we can migrate the action_log to action records with the correct memberId.
global $idToPersonMap;
$idToPersonMap = [];
// @todo We need to fill this out in the mapping of teacherClass
















/*
 *
 * The save function
 *
 */

function saveCouchDocs($docs) {
  $Resources = new couchClient($couchUrl, 'resources'); 
  $Assignments = new couchClient($couchUrl, 'assignments');
  $Members = new couchClient($couchUrl, 'members'); 
  $Actions = new couchClient($couchUrl, 'actions'); 
  $Questions = new couchClient($couchUrl, 'questions'); 
  $Feedbacks = new couchClient($couchUrl, 'feedbacks');
  $Groups = new couchClient($couchUrl, 'groups');
  $Facilities = new couchClient($couchUrl, 'facilities');
  foreach($docs as $doc) {
    switch ($doc->kind) {
      case 'Resource':
        // Save the doc
        $response = $Resources->storeDoc($doc);
        // Send the attachment
        $file_path = "/var/www/resources/" . $doc->legacy['id'] . "." . $doc->legacy['type'];
        $Resources->storeAttachment($Resources->getDoc($response->id), $file_path, mime_content_type($pfile_path));
      break;
      case 'Assignment':
        $Assignments->storeDoc($doc); 
      break;
      case "Member":
        $Members->storeDoc($doc);
      break;
      case 'Action':
        $Actions->storeDoc($doc); 
      break;
      case 'Group':
        $Groups->storeDoc($doc);
    }
  }
}















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
$facility = new couchDocument($client);
$facility->set(array(
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

global $facilityId;
$facilityId = $facility->_id;


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


$phaseTwoTables = ["teacherClass", "student", "resources", "LessonPlan", "feedback", "action_log" ];

phaseTwo($phaseTwoTables);


function phaseTwo($tables) {
  foreach($tables as $table) {
    $mysqlRecords = getMysqlrecords($table);
    // Map their schema to what we'll use in CouchDB
    $transformedRecords = mapBellSchema($mysqlRecords, $table);
    // Save the content to CouchDB
    saveCouchDocs($transformedRecords);
  }
}


function getMysqlrecords($table) {
  global $mysqli;
  // Get our resources from MySQL
  $results = $mysqli->query("SELECT * FROM $table");
  // Get those resouces into an array
  while($records[] = $results->fetch_row()) { }
  return $records;
}


function mapBeLLSchema($records, $table) {

  global $couchClient;
  global $facilityId;
  $mapped = array();

  switch($table) {

    // LessonPlan table -> kind:LessonPlan documents
    case 'LessonPlan':
      foreach($records as $record) {
        $n = $record 
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
      foreach($records as $record) {
      $n = new stdClass();
      // save legacy information for migration and in case we need it later
      $n->legacy = array(
        "id" => $record[1],
        "type" => $record[5]
      );
      $n->kind = "Resource";
      $n->title = $record[3];
      $n->author = ""; 
      $n->subject = strtolower($record[2]);
      $n->created = $record[7];
      $n->community = $record[15];
      $n->TLR = $record[16];
      if ($record[8]) $n->levels[] = "KG";
      if ($record[9]) $n->levels[] = "P1";
      if ($record[10]) $n->levels[] = "P2";
      if ($record[11]) $n->levels[] = "P3";
      if ($record[12]) $n->levels[] = "P4";
      if ($record[13]) $n->levels[] = "P5";
      if ($record[14]) $n->levels[] = "P6";
      $mapped[] = $n;
    break;
    
    case 'usedResources':

      // usedResources table -> kind:Feedback documents
      foreach($records as $record) {
        $n = new stdClass();
        $n->kind = "Feedback";
        $n->rating = $record->rating;
        $n->comment = "";
        $n->facilityId = $facilityId;
        $n->memberId = $record->usedby;
        $n->resourceId = $record->resrcID;
        $n->timestamp = $record->dateUsed;
        $n->context = array(
          subject => $record->subject,
          use => "stories for the week",
          level => $record->class
        ); 
        $mapped[] = $n
      }

      // usedResources table -> kind:Sync, useContext:"Stories for the week" documents
      foreach($records as $record) {
        $n = new stdClass();
        $n->kind = "Sync";
        $n->useContext = "stories for the week";
        // @todo Get group from $groups, 
        $n->group = $groups[$record->class];
        $mapped[] = $n
      }

    break;

    // teacherClass table -> kind:Member documents
    case 'teacherClass' :
      foreach($records as $record) {
        if($record->role=="Leadteacher"){
          // @todo use $leadTeacherMap to consolidate accounts
        }
        else {
          $n = new stdClass();
          // @todo get ID from Couch 
          $n->_id = $couchClient->getUuids(1)[0];
          // Transform into kind: Members, role: Teacher
          $n->login = $record->loginId;
          $n->kind = "Member";
          $n->facilityId = $facilityId;
          $n->role = array(strtolower($record->Role));
          $n->pass = $record->pswd;
          $n->level = array($record->classAssign);  // No good equivalent
          $n->dateRegistered = "";
          $n->dateOfBirth = "";
          $n->gender = "";
          // Break out the Name
          $nameArray = explode(" ", $record->Name);
          $n->firstName = $nameArray[0];
          $n->lastName = $nameArray[count($nameArray)-1];
          $nameArray = array_shift($nameArray);
          $nameArray = array_pop($nameArray);
          $n->middleNames = implode(' ', $nameArray);
          // Add Member _id to owners array in documents of kind:Group 
          $group = $Groups->getDoc($levelToGroupIdMap[$record->classAssign]);
          $group->owners[] = $n->_id;
          $couchClient->storeDoc($group);
        }
      }
    break;

    // students table -> kind:Member documents
    case 'students' :
      foreach($records as $record) {
        $n = new stdClass();
        $n->_id = $couchClient->getUuids(1)[0];
        $n->kind = "Member";
        $n->role = array("student");
        // no login for students
        $n->facilityId = $facilityId;
        $n->pass = $record->stuCode;
        $n->level = array($record->stuClass);  // No good equivalent
        $n->dateRegistered = strtotime($record->DateRegistered); // There's timezone issues here
        $n->dateOfBirth = strtotime($record->stuDOB);
        // Break out the Name
        $nameArray = explode(" ", $record->Name);
        $n->gender = $record->stuGender;
        $n->firstName = $nameArray[0];
        $n->lastName = $nameArray[count($nameArray)-1];
        $nameArray = array_shift($nameArray);
        $nameArray = array_pop($nameArray);
        if(count($nameArray) > 2) {
          $n->middleNames = implode(' ', $nameArray);
        }
        // Add Member _id to members array in documents of kind:Group 
        $group = $Groups->getDoc($levelToGroupIdMap[$record->stuClass]);
        $group->members[] = $n->_id;
        $couchClient->storeDoc($group);
        $mapped[] = $n;
      }
    break;
    // @todo feedback table -> kind:Action, context:pbell documents
    // @todo action_log table -> kind:Action, context:lms documents
    case 'action_log' : 
      // @todo When filling out $n->memberId, If $record->person is in the $idToPersonMap, we'll want to consolidate

    break;
  }
  return $mapped;

}



?>
