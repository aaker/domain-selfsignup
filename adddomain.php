<?php

define("SERVER", "");
define("SUPERUSER", ""); //label is super user but really should can be reseller too
define("PASSWORD", "");
define("CLIENTID", "");
define("CLIENTSECRET", "");

if (!isset($_REQUEST["reseller"]) && !isset($_REQUEST["domain"]))
  {
    header('Location: signupdomain.php?error=incomplete');
    exit;
  } //!isset($_REQUEST["reseller"]) && !isset($_REQUEST["domain"])
elseif ($_REQUEST["reseller"] == "" || $_REQUEST["domain"] == "" || $_REQUEST["email"] == "")
  {
    header('Location: signupdomain.php?error=incomplete');
    exit;
  } //$_REQUEST["reseller"] == "" || $_REQUEST["domain"] == "" || $_REQUEST["email"] == ""

$reseller = $_REQUEST["reseller"];
$domain   = $_REQUEST["domain"];
$email    = $_REQUEST["email"];

mt_srand(make_seed());
$PASSWORD = mt_rand() % 1000000;

/* First Step is to get a new Access token to given server.*/
$query = array(
    'grant_type' => 'password',
    'username' => SUPERUSER,
    'password' => PASSWORD,
    'client_id' => CLIENTID,
    'client_secret' => CLIENTSECRET
);

$postFields    = http_build_query($query);
$http_response = "";

$curl_result = __doCurl("https://" . SERVER . "/ns-api/oauth2/token", CURLOPT_POST, NULL, NULL, $postFields, $http_response);


if (!$curl_result)
  {
    header('Location: signupdomain.php?error=server');
    exit;
    
  } //!$curl_result

$token = json_decode($curl_result, /*assoc*/ true);

if (!isset($token['access_token']))
  {
    header('Location: signupdomain.php?error=server');
    exit;
    
  } //!isset($token['access_token'])

$token = $token['access_token'];


//Check Reseller
$query         = array(
    'object' => 'reseller',
    'action' => "count",
    'territory' => $reseller,
    'format' => "json"
    
);
$resellerCheck = __doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$reselChk      = json_decode($resellerCheck, true);
if (isset($reselChk['total']) && $reselChk['total'] . "" != "1")
  {
    header('Location: signupdomain.php?error=reseller');
    exit;
  } //isset($reselChk['total']) && $reselChk['total'] . "" != "1"
//Check Domain
$query       = array(
    'object' => 'domain',
    'action' => "count",
    'domain' => $domain,
    'format' => "json"
    
);
$domainCheck = __doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$domainChk   = json_decode($domainCheck, true);

if (isset($domainChk['total']) && $domainChk['total'] . "" == "1")
  {
    header('Location: signupdomain.php?error=domain');
    exit;
  } //isset($domainChk['total']) && $domainChk['total'] . "" == "1"

//ADD Domain
$query = array(
    'object' => 'domain',
    'action' => "create",
    'domain' => $domain,
    'territory' => $reseller
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


//ADD Domain Dial Plan
$query = array(
    'object' => 'dialplan',
    'action' => "create",
    'domain' => $domain,
    'dialplan' => $domain,
    'description' => "Dial Plan for " . $domain
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

//ADD default  dial Plan rule going to Default table
$query = array(
    'object' => 'dialrule',
    'action' => "create",
    'domain' => $domain,
    'dialplan' => $domain,
    'matchrule' => "*",
    'responder' => "<Cloud PBX Features>",
    'matchrule' => "*",
    'to_scheme' => "[*]",
    'to_user' => "[*]",
    'to_host' => "[*]",
    'plan_description' => "Chain to Default Table"
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


//Find DID
$query = array(
    'object' => 'phonenumber',
    'action' => "read",
    'responder' => "AvailableDID",
    'dialplan' => "DID Table",
    'plan_description' => "Available",
    'territory' => "AakerCo",
    'format' => "json"
);
$DIDs  = __doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$DIDsJson = json_decode($DIDs, true);

//Assign DID
if (isset($DIDsJson[0]['matchrule']))
  {
    $newDID = $DIDsJson[0]['matchrule'];
    $query  = array(
        'object' => 'phonenumber',
        'matchrule' => $newDID,
        'action' => "update",
        'responder' => "AvailableDID",
        'dialplan' => "DID Table",
        'dest_domain' => $domain,
        'to_user' => "[*]",
        'to_host' => $domain,
        'plan_description' => "Assigned to " . $domain
        
    );
    $DIDs   = __doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
    
    $newDID = str_replace("sip:1", "", str_replace("@*", "", $newDID));
  } //isset($DIDsJson[0]['matchrule'])
else
  {
    header('Location: signup.php?error=noResources');
  }


$areaCode = substr($newDID, 0, 3);
//ADD Domain user
$query    = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'first_name' => "Domain",
    'last_name' => "User",
    'dial_plan' => $domain,
    'dial_policy' => "US and Canada",
    'user' => 'domain',
    'dir_list' => "no",
    'dir_anc' => "no",
    'srv_code' => 'system-user',
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'vmail_transcribe' => "mutare",
    'subscriber_pin' => $PASSWORD
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'first_name' => "Guest",
    'last_name' => "Video",
    'dial_plan' => "Video Conference",
    'dial_policy' => "US and Canada",
    'user' => 'guest',
    'dir_list' => "no",
    'dir_anc' => "no",
    'srv_code' => 'system-user',
    'scope' => 'No Portal',
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'subscriber_pin' => $PASSWORD
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);



//ADD Department
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'dial_plan' => $domain,
    'scope' => "Basic User",
    'name' => "Tech Department",
    'user' => 'Tech',
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'directory_match' => 'departments',
    'dir' => 'departments',
    'dir_list' => "no",
    'dir_anc' => "no",
    'srv_code' => 'system-department',
    'vmail_transcribe' => "mutare",
    'subscriber_pin' => $PASSWORD
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
//ADD Department
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'dial_plan' => $domain,
    'scope' => "Basic User",
    'name' => "Sales Department",
    'user' => 'Sales',
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'directory_match' => 'departments',
    'dir' => 'departments',
    'dir_list' => "no",
    'dir_anc' => "no",
    'srv_code' => 'system-department',
    'vmail_transcribe' => "mutare",
    'subscriber_pin' => $PASSWORD
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);



//ADD Reseller user
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'dial_plan' => $domain,
    'scope' => "Reseller",
    'name' => $name,
    'user' => '1000',
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'department' => 'Tech',
    'vmail_transcribe' => "mutare",
    'subscriber_pin' => $PASSWORD
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$query = array(
    'object' => 'device',
    'action' => "create",
    'domain' => $domain,
    'user' => "1000",
    'device' => "sip:1000@" . $domain,
    'passwordLength' => 8
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'answerrule',
    'action' => "create",
    'domain' => $domain,
    'user' => "1000",
    'time_frame' => "*",
    'priority' => "0",
    'sim_parameters' => "<OwnDevices>",
    'sim_control' => "e",
    'dnd_enable' => "0",
    'enable' => "1",
    'order' => "99"
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

//ADD Office Manager user
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'dial_plan' => $domain,
    'email' => $email,
    'scope' => "Office Manager",
    'first_name' => "Office",
    'last_name' => "Manager",
    'user' => '1001',
    'dir_list' => "yes",
    'dir_anc' => "yes",
    'department' => 'Tech',
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'vmail_transcribe' => "mutare",
    'subscriber_pin' => $PASSWORD
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$query = array(
    'object' => 'device',
    'action' => "create",
    'domain' => $domain,
    'user' => "1001",
    'device' => "sip:1001@" . $domain,
    'passwordLength' => 8
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$query = array(
    'object' => 'answerrule',
    'action' => "create",
    'domain' => $domain,
    'user' => "1001",
    'time_frame' => "*",
    'priority' => "0",
    'sim_parameters' => "<OwnDevices>",
    'sim_control' => "e",
    'dnd_enable' => "0",
    'enable' => "1",
    'order' => "99"
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


//ADD Supervisor user
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'dial_plan' => $domain,
    'scope' => "Call Center Supervisor",
    'first_name' => "Call Center",
    'last_name' => "Supervisor",
    'user' => '1002',
    'dir_list' => "yes",
    'dir_anc' => "yes",
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'vmail_transcribe' => "mutare",
    'subscriber_pin' => $PASSWORD
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$query = array(
    'object' => 'device',
    'action' => "create",
    'domain' => $domain,
    'user' => "1002",
    'device' => "sip:1002@" . $domain,
    'passwordLength' => 8
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$query = array(
    'object' => 'answerrule',
    'action' => "create",
    'domain' => $domain,
    'user' => "1002",
    'time_frame' => "*",
    'priority' => "0",
    'sim_parameters' => "<OwnDevices>",
    'sim_control' => "e",
    'dnd_enable' => "0",
    'enable' => "1",
    'order' => "99"
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


//ADD basic user
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'dial_plan' => $domain,
    'scope' => "Basic User",
    'first_name' => "Basic",
    'last_name' => "User",
    'user' => '1003',
    'dir_list' => "yes",
    'dir_anc' => "yes",
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'department' => 'Sales',
    'vmail_transcribe' => "mutare",
    'subscriber_pin' => $PASSWORD
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$query = array(
    'object' => 'device',
    'action' => "create",
    'domain' => $domain,
    'user' => "1003",
    'device' => "sip:1003@" . $domain,
    'passwordLength' => 8
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$query = array(
    'object' => 'answerrule',
    'action' => "create",
    'domain' => $domain,
    'user' => "1003",
    'time_frame' => "*",
    'priority' => "0",
    'sim_parameters' => "<OwnDevices>",
    'sim_control' => "e",
    'dnd_enable' => "0",
    'enable' => "1",
    'order' => "99"
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


//ADD Route Manager
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'dial_plan' => $domain,
    'scope' => "Route Manager",
    'first_name' => "Route",
    'last_name' => "Manager",
    'user' => '1004',
    'dir_list' => "yes",
    'dir_anc' => "yes",
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'vmail_transcribe' => "mutare",
    'subscriber_pin' => $PASSWORD
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$query = array(
    'object' => 'device',
    'action' => "create",
    'domain' => $domain,
    'user' => "1004",
    'device' => "sip:1004@" . $domain,
    'passwordLength' => 8
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'answerrule',
    'action' => "create",
    'domain' => $domain,
    'user' => "1004",
    'time_frame' => "*",
    'priority' => "0",
    'sim_parameters' => "<OwnDevices>",
    'sim_control' => "e",
    'dnd_enable' => "0",
    'enable' => "1",
    'order' => "99"
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


//ADD Call Queue
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'scope' => "Basic User",
    'first_name' => "Sales",
    'last_name' => "Call Queue",
    'dir_list' => "no",
    'dir_anc' => "no",
    'user' => '2000',
    'srv_code' => 'system-queue',
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'subscriber_pin' => $PASSWORD
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
$query = array(
    'object' => 'callqueue',
    'action' => "create",
    'domain' => $domain,
    'queue' => "2000",
    'run_stats' => "yes",
    'huntgroup_option' => "Ring All",
    'description' => "Sales"
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'answerrule',
    'action' => "create",
    'domain' => $domain,
    'user' => "2000",
    'time_frame' => "*",
    'priority' => "0",
    'for_parameters' => "queue_2000",
    'for_control' => "e",
    'dnd_enable' => "0",
    'enable' => "1",
    'order' => "99"
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


$query = array(
    'object' => 'dialrule',
    'action' => "create",
    'domain' => $domain,
    'dialplan' => $domain,
    'matchrule' => "*",
    'responder' => "sip:start@call-queuing",
    'matchrule' => "queue_2000",
    'to_scheme' => "[*]",
    'to_user' => "2000",
    'to_host' => $domain,
    'plan_description' => "To Queue"
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);




//ADD Call Queue
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'scope' => "Basic User",
    'first_name' => "Support",
    'last_name' => "Call Queue",
    'dir_list' => "no",
    'dir_anc' => "no",
    'user' => '2001',
    'srv_code' => 'system-queue',
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'subscriber_pin' => $PASSWORD
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'callqueue',
    'action' => "create",
    'domain' => $domain,
    'queue' => "2001",
    'huntgroup_option' => "1stAvail",
    'run_stats' => "yes",
    'description' => "Support"
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'answerrule',
    'action' => "create",
    'domain' => $domain,
    'user' => "2001",
    'time_frame' => "*",
    'priority' => "0",
    'for_parameters' => "queue_2001",
    'for_control' => "e",
    'dnd_enable' => "0",
    'enable' => "1",
    'order' => "99"
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


$query = array(
    'object' => 'dialrule',
    'action' => "create",
    'domain' => $domain,
    'dialplan' => $domain,
    'matchrule' => "*",
    'responder' => "sip:start@call-queuing",
    'matchrule' => "queue_2001",
    'to_scheme' => "[*]",
    'to_user' => "2001",
    'to_host' => $domain,
    'plan_description' => "To Queue"
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


//ADD Park 1
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'scope' => "Basic User",
    'first_name' => "Call Park",
    'last_name' => "One",
    'dir_list' => "no",
    'dir_anc' => "no",
    'user' => '701',
    'srv_code' => 'system-queue',
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'subscriber_pin' => $PASSWORD
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'callqueue',
    'action' => "create",
    'domain' => $domain,
    'queue' => "701",
    'huntgroup_option' => "Call Park",
    'description' => "Call Park One"
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'answerrule',
    'action' => "create",
    'domain' => $domain,
    'user' => "701",
    'time_frame' => "*",
    'priority' => "0",
    'for_parameters' => "queue_701",
    'for_control' => "e",
    'dnd_enable' => "0",
    'enable' => "1",
    'order' => "99"
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


$query = array(
    'object' => 'dialrule',
    'action' => "create",
    'domain' => $domain,
    'dialplan' => $domain,
    'matchrule' => "*",
    'responder' => "sip:start@call-queuing",
    'matchrule' => "queue_701",
    'to_scheme' => "[*]",
    'to_user' => "701",
    'to_host' => $domain,
    'plan_description' => "To Queue"
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


//ADD Park 2
$query = array(
    'object' => 'subscriber',
    'action' => "create",
    'domain' => $domain,
    'scope' => "Basic User",
    'first_name' => "Call Park",
    'last_name' => "Two",
    'dir_list' => "no",
    'dir_anc' => "no",
    'user' => '702',
    'srv_code' => 'system-queue',
    'area_code' => $areaCode,
    'callid_name' => $reseller,
    'callid_nmbr' => $newDID,
    'callid_emgr' => $newDID,
    'subscriber_pin' => $PASSWORD
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'callqueue',
    'action' => "create",
    'domain' => $domain,
    'queue' => "702",
    'huntgroup_option' => "Call Park",
    'description' => "Call Park Two"
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

$query = array(
    'object' => 'answerrule',
    'action' => "create",
    'domain' => $domain,
    'user' => "702",
    'time_frame' => "*",
    'priority' => "0",
    'for_parameters' => "queue_702",
    'for_control' => "e",
    'dnd_enable' => "0",
    'enable' => "1",
    'order' => "99"
    
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);


$query = array(
    'object' => 'dialrule',
    'action' => "create",
    'domain' => $domain,
    'dialplan' => $domain,
    'matchrule' => "*",
    'responder' => "sip:start@call-queuing",
    'matchrule' => "queue_702",
    'to_scheme' => "[*]",
    'to_user' => "702",
    'to_host' => $domain,
    'plan_description' => "To Queue"
    
);
__doCurl("https://" . SERVER . "/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);




__sendEmail($email, $reseller, $domain, $newDID, $PASSWORD, $name, $client_id, $client_secret);

function __sendEmail($to, $reseller, $domain, $phonenumber, $password, $name, $client_id, $client_secret)
  {
    // subject
    $subject = 'Access to NetSapiens Alpha Server';
    
    // message
    $message = "<html>
	<body>

	<p>Thank you for requesting access to demo Platform.</p>

	<h3>Domain Access</h3>
	<p>We have setup a domain named '$domain' </p>
	<p>We have allocated a single DID for you use, its number is $phonenumber and should be available in the inventory page of the portal to assign as you wish. </p>

	<h3>Accounts</h3>
	<p>We have created a few users for easy access to the system with the portal at https://" . SERVER . "/portal. </p>

	<table border='1'>
	<tr>
	<th>User</th>
	<th>Login</th>
	<th>Scope</th>
	<th>Password</th>
	</tr>
	<tr>
	<td>1000</td>
	<td>1000@$domain</td>
	<td>Reseller</td>
	<td>$password</td>
	</tr>
	<tr>
	<td>1001</td>
	<td>1001@$domain</td>
	<td>Office Manager</td>
	<td>$password</td>
	</tr>
	<tr>
	<td>1002</td>
	<td>1002@$domain</td>
	<td>Call Center Supervisor</td>
	<td>$password</td>
	</tr>
	<tr>
	<td>1003</td>
	<td>1003@$domain</td>
	<td>Basic User</td>
	<td>$password</td>
	</tr>
	<tr>
	<td>1004</td>
	<td>1004@$domain</td>
	<td>Route Manager</td>
	<td>$password</td>
	</tr>
	</table>

	<p>We have created a few Call queues, 2000 (Sales) and 2001 (Support) as well. Feel free to use or remove as part of you feature testing. </p>


	

	</table>




	</body>
	</html>
	";
    
    // To send HTML mail, the Content-type header must be set
    $headers = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    
    // Additional headers
    //$headers .= "To: $to" . "\r\n";
    $headers .= 'From: demo@netsapiens.com' . "\r\n";
    
    // Mail it
    mail($to, $subject, $message, $headers);
  }



function make_seed()
  {
    list($usec, $sec) = explode(' ', microtime());
    return (float) $sec + ((float) $usec * 100000);
  }



function __doCurl($url, $method, $authorization, $query, $postFields, &$http_response)
  {
    $start        = microtime(true);
    $curl_options = array(
        CURLOPT_URL => $url . ($query ? '?' . http_build_query($query) : ''),
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FORBID_REUSE => true,
        CURLOPT_TIMEOUT => 60
    );
    
    $headers = array();
    if ($authorization != NULL)
      {
        $headers[$authorization] = $authorization;
      } //$authorization != NULL
    
    
    
    $curl_options[$method] = true;
    if ($postFields != NULL)
      {
        $curl_options[CURLOPT_POSTFIELDS] = $postFields;
      } //$postFields != NULL
    
    if (sizeof($headers) > 0)
        $curl_options[CURLOPT_HTTPHEADER] = $headers;
    
    $curl_handle = curl_init();
    curl_setopt_array($curl_handle, $curl_options);
    $curl_result   = curl_exec($curl_handle);
    $http_response = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
    //print_r($http_response);
    curl_close($curl_handle);
    $end = microtime(true);
    if (!$curl_result)
        return NULL;
    else if ($http_response >= 400)
        return NULL;
    else
        return $curl_result;
  }

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Thank You</title>
<link rel="shortcut icon" type="image/x-icon"
	href="https://alpha1.netsapiens.com/SiPbx/getimage.php?filename=favicon.gif&server=corp.netsapiens.com" />
<link rel="icon" type="image/x-icon"
	href="https://alpha1.netsapiens.com/SiPbx/getimage.php?filename=favicon.gif&server=corp.netsapiens.com" />

<link rel="stylesheet"
	href="https://alpha1.netsapiens.com/portal/css/bootstrap.min.css"
	type="text/css">
<link rel="stylesheet"
	href="https://alpha1.netsapiens.com/portal/css/jquery-ui-1.10.bootstrap.css"
	type="text/css">

<link rel="stylesheet"
	href="https://alpha1.netsapiens.com/portal/css/portal.php?background=%23eeefe9&primary1=%237f223c&primary2=%23c0919e&bar1=%238c8c8c&bar2=%23cccccc"
	type="text/css">


</head>
<body>
	<div class="fixed-container"></div>
	<div id="login-container">

		<div id="login-group">
			<div id="login-box" style="width: 350px;">

				<div id="login-logo">



					<img
						src="https://alpha1.netsapiens.com/SiPbx/getimage.php?server=corp.netsapiens.com&filename=portal_landing.png&server=corp.netsapiens.com" />
				</div>

				<div id="login-text"
					style="width: 330px; font-size: 120%; text-align: center; padding-top: 10px; padding-left: 10px; padding-right: 10px;">


					Thank you! You should be recieving a email shortly. Please check
					your Spam filter if you do not see it withen 5 minutes.</div>

				<form action="signup.php" class="form-stacked" id="LoginLoginForm"
					method="get" accept-charset="utf-8">
					<div style="display: none;">
						<input type="hidden" name="_method" value="POST" />
					</div>
					<div id="login-fields"></div>
					<BR>
					<div id="login-submit">
						<div class="submit">
							<input class="btn color-primary" update="#login" type="submit"
								value="Go Back" />
						</div>
					</div>
				</form>
			</div>
			<div id="footer">
				<p>Example by NetSapiens, Inc.</p>
				<p>Domain Creation Script</p>

			</div>
			<!-- /login-box -->
		</div>
		<!-- /login-group -->

	</div>
	<!-- majority of javascript at bottom so the page displays faster -->

</body>

</html>
