<?php $v = "";

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
// Version of this code will place in seperate databases for testing
$dbNames = array( 
  "facilities" => "facilities$v",
  "whoami" => "whoami$v",
  "resources" => "resources$v",
  "members" => "members$v",
  "assignments" => "assignments$v",
  "actions" => "actions$v",
  "questions" => "questions$v",
  "feedback" => "feedback$v",
  "groups" => "groups$v"
  );

global $couchClient;

$couchClient = new couchClient($couchUrl, "dummy");


exec("curl -XPUT $couchUrl/" . $dbNames['facilities']);
exec("curl -XPUT $couchUrl/" . $dbNames['whoami']);
exec("curl -XPUT $couchUrl/" . $dbNames['resources']);
exec("curl -XPUT $couchUrl/" . $dbNames['members']);
exec("curl -XPUT $couchUrl/" . $dbNames['assignments']);
exec("curl -XPUT $couchUrl/" . $dbNames['actions']);
exec("curl -XPUT $couchUrl/" . $dbNames['questions']);
exec("curl -XPUT $couchUrl/" . $dbNames['feedback']);
exec("curl -XPUT $couchUrl/" . $dbNames['groups']);










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
global $teacherClassNameToMemberIdMap;
$teacherClassNameToMemberIdMap = [];

global $resrcIDtoResourceIdMap;
$resrcIDtoResourceIdMap = array();











/*
 *
 * The save function
 *
 */

function saveCouchDocs($docs) {
  global $dbNames;
  $couchUrl = "http://pi:raspberry@127.0.0.1:5984";
  $Resources = new couchClient($couchUrl, $dbNames['resources']); 
  $Assignments = new couchClient($couchUrl, $dbNames['assignments']);
  $Members = new couchClient($couchUrl, $dbNames['members']); 
  $Actions = new couchClient($couchUrl, $dbNames['actions']); 
  $Questions = new couchClient($couchUrl, $dbNames['questions']); 
  $Feedback = new couchClient($couchUrl, $dbNames['feedback']);
  $Groups = new couchClient($couchUrl, $dbNames['groups']);
  $Facilities = new couchClient($couchUrl, $dbNames['facilities']);
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
      case 'Feedback':
        $Feedback->storeDoc($doc); 
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
$Facilities = new couchClient($couchUrl, $dbNames['facilities']);
$facility = $Facilities->storeDoc($facility);

global $facilityId;
$facilityId = $facility->id;


// Create the whoami/facility doc
$whoami = new couchClient('http://127.0.0.1:5984', $dbNames['whoami']);
$whoamiFacility = new couchDocument($whoami);
$whoamiFacility->set(array(
  "_id" => "facility",
  "kind" => "system",
  "facilityId" => $facilityId,
));

// Create the whoami/config doc
$whoamiConfig = new couchDocument($whoami);
$whoamiConfig->set(array(
  "_id" => "config",
  "kind" => "system",
  "timezone" => "GMT",
  "language" => "EN",
  "version" => "2.0",
  "layout" => 1,
  "subjects" => array("english", "math", "science"),
  "levels" => array('KG1', 'KG2', 'P1', 'P2', 'P3', 'P4', 'P5', 'P6') 
));


// Create default groups in Couch
global $levelToGroupIdMap;
$levelToGroupIdMap = array(
  "KG1" => "",
  "KG2" => "",
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
  $n->level = array($key);
  $n->members = array();
  $n->owners = array();
  $n->facilityId = $facilityId;
  $levelToGroupIdMap[$key] = $n->_id;
  $groups[] = $n;
}
// Things with level:KG are going to reference the KG1 group
$levelToGroupIdMap["KG"] = $levelToGroupIdMap["KG1"];
saveCouchDocs($groups);















/*
 *
 *
 * Phase Two
 *
 *
 */
print ("Starting Phase Two");

$phaseTwoTables = ["teacherClass", "students", "resources", "usedResources",  "LessonPlan", "feedback", "action_log" ];
//$phaseTwoTables = ["teacherClass",  "resources", "usedResources","LessonPlan", "feedback", "action_log" ];
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
  global $dbNames;
  global $levelToGroupIdMap;
  global $resrcIDtoResourceIdMap;
  global $teacherClassNameToMemberIdMap;
  $mapped = array();

  switch($table) {

    // LessonPlan table -> kind:LessonPlan documents
    case 'LessonPlan':
      foreach($records as $record) {
        // @todo This needs to also create docs of kind:Assignment 
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
        $n->_id = $couchClient->getUuids(1)[0];
        $resrcIDtoResourceIdMap[$record->resrcID] = $n->_id;
        // save legacy information for migration and in case we need it later
        $n->legacy = array(
          "id" => $record->resrcID,
          "type" => $record->type
        );
        switch($record->type) {
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
        $n->language = "en";
        $n->description = $record->description;
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
        if ($record->KG) $n->levels[] = "KG1";
        if ($record->KG) $n->levels[] = "KG2";
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
      
      // Because this table's usedby column had a limit of 15 characters, references to teachers by name were truncated.
      // Create a version of $teacherClassNameToMemberIdMap with truncated keys.
      $teacherClassNameToMemberIdMapTruncated = array();
      foreach($teacherClassNameToMemberIdMap as $key => $value) {
        $teacherClassNameToMemberIdMapTruncated[substr($key, 0, 15)] = $value;
      }

      
      // usedResources table -> kind:Feedback documents
      foreach($records as $record) {
        $n = new stdClass();
        $n->kind = "Feedback";
        $n->rating = $record->rating;
        $n->comment = "";
        $n->facilityId = $facilityId;
        $n->memberId = $teacherClassNameToMemberIdMapTruncated[$record->usedby];
        $n->resourceId = $resrcIDtoResourceIdMap[$record->resrcID];
        $n->timestamp = strtotime($record->dateUsed);
        $level = ($record->class == "KG") ? "KG1": $record->class;
        $n->context = array(
          "subject" => strtolower($record->subject),
          "use" => "stories for the week",
          "level" => $level
        ); 
        $mapped[] = $n;
      }

      // usedResources table -> kind:Assignment, useContext:"Stories for the week" documents
      foreach($records as $record) {
        $n = new stdClass();
        $n->kind = "Assignment";
        $n->resourceId = $resrcIDtoResourceIdMap[$record->resrcID];
        $n->createdBy = $teacherClassNameToMemberIdMapTruncated[$record->usedby];
        $n->startDate = strtotime($record->dateUsed);
        $n->endDate = strtotime($record->dateUsed);
        $n->context = array(
          "subject" => strtolower($record->subject),
          "use" => "stories for the week",
          "groupId" => $levelToGroupIdMap[$record->class],
          "facilityId" => $facilityId 
        );
        $mapped[] = $n;
      }

    break;

    // teacherClass table -> kind:Member documents
    case 'teacherClass' :
      global $teacherClassNameToMemberIdMap;
      global $leadTeacherAccountConsolidationMap;
      foreach($records as $record) {

        // check to see if this is a lead teacher account, it may cause $skip to be true.
        foreach($leadTeacherAccountConsolidationMap as $key => $map) {
          if ($record->Name == $map[1]) { 
            $skip = TRUE;
          }
          else if($record->Name == $map[0]) {
            $skip = FALSE;
            $originalLeadName = $map[1]; // save for later when we have an ID
            $originalTeacherName = $map[0]; // save for later when we have an ID
            $record->Name = $map[2];
          }
          else {
            $skip = FALSE;
          }
        }

        if(!$skip) {
          $n = new stdClass();
          $n->_id = "gh" . $couchClient->getUuids(1)[0];
          // Save this so we can change the member reference in the action_log table to docs with kind:Action migration
          // If we are mapping this name from old accounts we'll need to save the map to this new ID for reference from other tables
          if ($originalLeadName && $originalTeacherName) {
            $teacherClassNameToMemberIdMap[$originalTeacherName] = $n->_id;
            $teacherClassNameToMemberIdMap[$originalLeadName] = $n->_id;
            // Reset variables for next iteration
            $originalLeadName = FALSE;
            $originalTeacherName = FALSE;
            $skip = FALSE;
          }
          else {
            $teacherClassNameToMemberIdMap[$record->Name] = $n->_id;
          }
          // Transform into kind: Members, role: Teacher
          $n->login = $record->loginId;
          $n->kind = "Member";
          $n->facilityId = $facilityId;
          $n->roles = array(strtolower($record->Role));
          $n->pass = $record->pswd;
          $n->status= "active";
          $n->levels = ($record->classAssign == "KG") ? array("KG1") : array($record->classAssign);  // No good equivalent
          $n->dateRegistered = "";
          $n->dateOfBirth = "";
          $n->nationality = "gh";
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

          // Add Member _id to owners array in documents of kind:Group 
          if ($record->classAssign != "Gen") { 
            global $couchUrl;
            global $dbNames;
            $Groups = new couchClient($couchUrl, $dbNames["groups"]);
            $groupId = $levelToGroupIdMap[$record->classAssign];
            $group = $Groups->getDoc($groupId);
            $group->owners[] = $n->_id;
            $Groups->storeDoc($group);
          }
          $mapped[] = $n;
        }
      }
      print_r($teacherClassNameToMemberIdMap);
    break;

    // students table -> kind:Member documents
    case 'students' :
      foreach($records as $record) {
        $n = new stdClass();
        $n->_id = "gh" . $couchClient->getUuids(1)[0];
        $n->kind = "Member";
        $n->roles = array("student");
        // no login for students
        $n->facilityId = $facilityId;
        $n->pass = $record->stuCode;
        $n->levels = ($record->stuClass == "KG") ? array("KG1") : array($record->stuClass); 
        $n->dateRegistered = strtotime($record->DateRegistered); // There's timezone issues here
        $n->dateOfBirth = strtotime($record->stuDOB);
        $n->nationality = "gh";
        // Break out the Name
        $nameArray = explode(" ", $record->stuName);
        $n->status= "active";
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
        $Groups = new couchClient($couchUrl, $dbNames["groups"]);
        $group = $Groups->getDoc($levelToGroupIdMap[$record->stuClass]);
        $group->members[] = $n->_id;
        $Groups->storeDoc($group);
        $mapped[] = $n;
      }
    break;
    // @todo feedback table -> kind:Action, context:pbell documents
    // @todo action_log table -> kind:Action, context:lms documents
    case 'feedback' : 
    case 'action_log' : 
      // @todo When filling out $n->memberId, If $record->person is in the $idToPersonMap, we'll want to consolidate

    break;
  }
  return $mapped;

}



?>
