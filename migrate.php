<?php















/*
 *
 * Setup dependencies and environment
 *
 */


// MySQL
global $mysqli;
$mysqli = new mysqli("localhost", "root", "raspberry", "Test-SchoolDB");

// CouchDB
require_once 'PHP-on-Couch-master/lib/couch.php';
require_once 'PHP-on-Couch-master/lib/couchClient.php';
require_once 'PHP-on-Couch-master/lib/couchDocument.php';
global $couchUrl;
$couchUrl = 'http://pi:raspberry@127.0.0.1:5984';

global $couchClient;

$couchClient = new couchClient($couchUrl, "dummy");


exec("curl -XPUT $couchUrl/facilities");
exec("curl -XPUT $couchUrl/whoami");
exec("curl -XPUT $couchUrl/resources");
exec("curl -XPUT $couchUrl/members");
exec("curl -XPUT $couchUrl/assignments");
exec("curl -XPUT $couchUrl/actions");
exec("curl -XPUT $couchUrl/questions");
exec("curl -XPUT $couchUrl/feedback");
exec("curl -XPUT $couchUrl/groups");










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
  // [ Lead Teacher Account, Other Account, Name for Consolidated Account, ID for Consolidated Account ]
  ['Christian Adjabeng-Leadteacher', 'Adjabeng Christian -P5', 'Adjabeng Christian', null], // Sacred Heart
  ['lydia sarfo - leadteacher', 'lydia sarfo p1', 'lydia sarfo', null], // Akuiakrom
  ['Charlotte Akpaglo-Lead Teacher', 'Charlotte Akpaglo-P6', 'Charlotte Akpaglo', null], // Ayikaidoblo
  ['Cephas-Lead Teacher', 'Cephas Agbai-Kude -P6', 'Cephas Agbai-Kude', null], // Katapor
  ['ADWOA K ONADU - LEAD TEACHER', 'ADWOA K AGYEPONG-P3B', 'ADWOA K AGYEPONG', null], // Mamobi
  ['Joshua Opata', 'Joshua Opata - P4', 'Joshua Opata', null], // Ogua
  ['Benjamin Dodoo-Lead Teacher', 'Benjamin Dodoo-P2', 'Benjamin Dodoo', null], // Pokwasi
   // nothing for Sam Sam
  ['Olivia Ahiayibor - lead teacher', 'Olivia Ahiayibor-P5', 'Olivia Ahiayibor', null], // Sapeiman
  ['MISS SERWAH-LEAD TEACHER', 'MISS NKANSAH - P3', 'MISS NKANSAH', null] // Saint Anthony
];

// Person field was used in the action_log table to reference a user.  We'll want to capture a map of id to names when creating member records so we can migrate the action_log to action records with the correct memberId.
global $personToIdMap;
$personToIdMap = [];











/*
 *
 * The save function
 *
 */

function saveCouchDocs($docs) {
  $couchUrl = "http://pi:raspberry@127.0.0.1:5984";
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
        $Resources->storeAttachment($Resources->getDoc($response->id), $file_path, mime_content_type($file_path));
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
$facility = (object) array(
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
);
$Facilities = new couchClient($couchUrl, 'facilities');
$facility = $Facilities->storeDoc($facility);

global $facilityId;
$facilityId = $facility->id;


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
global $levelToGroupIdMap;
$levelToGroupIdMap = array(
  "KG" => "",
  "P1" => "",
  "P2" => "",
  "P3" => "",
  "P4" => "",
  "P5" => "",
  "P6" => ""
);

$groups = array();
foreach($levelToGroupIdMap as $key => $id) {
  $n = new stdClass();
  $n->_id = $couchClient->getUuids(1)[0];
  $n->kind = "Group";
  $n->name = $key;
  $n->level = $key;
  $n->members = array();
  $n->owners = array();
  $n->facilityId = $facilityId;
  $levelToGroupIdMap[$key] = $n->_id;
  $groups[] = $n;
}

saveCouchDocs($groups);















/*
 *
 *
 * Phase Two
 *
 *
 */
print ("Starting Phase Two");

$phaseTwoTables = ["teacherClass", "students", "resources", "LessonPlan", "feedback", "action_log" ];
//$phaseTwoTables = ["teacherClass", "students", "LessonPlan", "feedback", "action_log" ];
//$phaseTwoTables = ["resources", "LessonPlan", "feedback", "action_log" ];

phaseTwo($phaseTwoTables);


function phaseTwo($tables) {
  foreach($tables as $table) {
    $mysqlRecords = getMysqlRecords($table);
    print "Received mysqlRecords for " . $table . "\n";
    // Map their schema to what we'll use in CouchDB
    $transformedRecords = mapBellSchema($mysqlRecords, $table);
    print "Transformed records for $table \n";
    // Save the content to CouchDB
    saveCouchDocs($transformedRecords);
  }
}


function getMysqlRecords($table) {
  global $mysqli;
  // Get our resources from MySQL
  $results = $mysqli->query("SELECT * FROM $table");
  // Get those resouces into an array
  $records = array();
  while($record = $results->fetch_object()) { 
    $records[] = $record;
  }
  return $records;
}


function mapBeLLSchema($records, $table) {
  print "mapBeLLSchema CALLED for " . $table . "\n";
  global $couchClient;
  global $facilityId;
  global $levelToGroupIdMap;
  $mapped = array();

  switch($table) {

    // LessonPlan table -> kind:LessonPlan documents
    case 'LessonPlan':
      foreach($records as $record) {
        $n = $record; 
        $n->kind = "LessonPlan";
        $n->Pre_Writing_or_Reading = $n->Pre_Writing;
        unset($n->Pre_Writing);
        $n->Writing_or_Reading = $n->Writing;
        unset($n->Writing);
        $n->Post_Writing_or_Reading = $n->Post_Writing;
        unset($n->Post_Writing);
        $mapped[] = $n;
      }
    break;

    // resources table -> kind:Resource documents
    case 'resources':
      foreach($records as $record) {
        $n = new stdClass();
        // save legacy information for migration and in case we need it later
        $n->legacy = array(
          "id" => $record->resrcID,
          "type" => $record->type
        );
        switch($record->type)
          case "pdf" :
          case "doc" :
          case "docx" : 
          case "ppt" :
          case "pptx" :
            $n->type = "readable";
          break;
          case "mp3" :
            $n->type = "audio story";
          break;
        }
        $n->kind = "Resource";
        $n->language = "EN";
        $n->title = $record->title;
        $n->author = ""; 
        $n->subject = strtolower($record->subject);
        $n->created = strtotime($record->dateAdded);
        if($record->Community == "YES") {
          $n->audience[] = "community education";
        }
        if($record->TLR == "YES") {
          $n->audience[] = "teacher training";
        }
        if ($record->KG || $record->P1 || $record->P2 || $record->P3 || $record->P4 || $record->P5 || $record->P6) {
          $n->audience[] = "formal education";
        }
        if ($record->KG) $n->levels[] = "KG";
        if ($record->P1) $n->levels[] = "P1";
        if ($record->P2) $n->levels[] = "P2";
        if ($record->P3) $n->levels[] = "P3";
        if ($record->P4) $n->levels[] = "P4";
        if ($record->P5) $n->levels[] = "P5";
        if ($record->P6) $n->levels[] = "P6";
        $mapped[] = $n;
      }
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
          "subject" => $record->subject,
          "use" => "stories for the week",
          "level" => $record->class
        ); 
        $mapped[] = $n;
      }

      // usedResources table -> kind:Sync, useContext:"Stories for the week" documents
      foreach($records as $record) {
        $n = new stdClass();
        $n->kind = "Assignment";
        $n->resourceId = $record->resrcID;
        $n->startDate = strtotime($record->dateUsed);
        $n->endDate = strtotime($record->dateUsed);
        $n->context = array(
          "subject" => $record->subject,
          "use" => "stories for the week",
          "group" => $levelToGroupIdMap[$record->class]
        );
        $mapped[] = $n;
      }

    break;

    // teacherClass table -> kind:Member documents
    case 'teacherClass' :
      global $personToIdMap;


      foreach($records as $record) {

        // Check to see if this is a lead teacher account, it may cause $skip to be true.
        foreach($leadTeacherAccountConsolidationMap as $key => $map) {
          if($record->Name == $map[0] || $record->Name == $map[1]) { 
            // If this has a real _id already, we need to skip consolidation but save this a potential name to id match
            if($map[3]) { 
              $skip = TRUE;
              $personToIdMap[$record->Name] = $map[3];
            }
            // We have our first case of this Lead teacher in which case we want to this account the desired name and make a new Member document
            else { 
              $skip = FALSE;
              $originalName = $record->Name; // save for later when we have an ID
              $record->Name = $map[2]
            }
          }
        }

        if(!$skip) {
          $n = new stdClass();
          $n->_id = $couchClient->getUuids(1)[0];
          // Save this so we can change the member reference in the action_log table to docs with kind:Action migration
          $personToIdMap[$record->Name] = $n->_id
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
          if(count($nameArray) > 2) {
            // Modify array for only middle name entries
            array_pop($nameArray);
            array_shift($nameArray);
            $n->middleNames = implode(' ', $nameArray);
          }
          else {
            $n->middleNames = "";
          }

          // We need this later for migrating action_log table to documents with kind:Action
          $personToIdMap[$originalName] = $n->_id;

          // Add Member _id to owners array in documents of kind:Group 
          if ($record->classAssign != "Gen") { 
            global $couchUrl;
            $Groups = new couchClient($couchUrl, "groups");
            $groupId = $levelToGroupIdMap[$record->classAssign];
            $group = $Groups->getDoc($groupId);
            $group->owners[] = $n->_id;
            $Groups->storeDoc($group);
          }
          $mapped[] = $n;
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
        $nameArray = explode(" ", $record->stuName);
        $n->gender = $record->stuGender;
        $n->firstName = $nameArray[0];
        $n->lastName = $nameArray[count($nameArray)-1];
        if(count($nameArray) > 2) {
          // Modify array for only middle name entries
          array_pop($nameArray);
          array_shift($nameArray);
          $n->middleNames = implode(' ', $nameArray);
        }
        else {
          $n->middleNames = "";
        }
        // Add Member _id to members array in documents of kind:Group 
        global $couchUrl;
        $Groups = new couchClient($couchUrl, "groups");
        $group = $Groups->getDoc($levelToGroupIdMap[$record->stuClass]);
        $group->members[] = $n->_id;
        $Groups->storeDoc($group);
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
