<?php

require_once(__DIR__.'/../lib/loader.php');


if(LDAP_SERVER == null) {
	die("LDAP Sync Not Configured!\n");
}

$ldapconn = ldap_connect(LDAP_SERVER);
if(!$ldapconn) {
	die("ldap_connect FAILED.\n");
}

echo "ldap_connect OK\n";
ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldapconn, LDAP_OPT_NETWORK_TIMEOUT, 5);
ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0 );
$ldapbind = ldap_bind($ldapconn, LDAP_USER.'@'.LDAP_DOMAIN, LDAP_PASS);
if(!$ldapbind) {
	die("ldap_bind FAILED.".ldap_error($ldapconn)."\n");
}

echo "ldap_bind OK\n";
$result = ldap_search($ldapconn, LDAP_QUERY_ROOT, "(objectClass=user)");
if(!$result) {
	die("ldap_search FAILED: ".ldap_error($ldapconn)."\n");
}

$data = ldap_get_entries($ldapconn, $result);

echo "ldap_search OK - Found ".$data["count"]." Entries\n";
echo "Begin Processing Entries...\n";
#var_dump($data); // debug

// iterate over result array
$ldapUsers = [];
$counter = 1;
for($i=0; $i<$data["count"]; $i++) {
	#var_dump($data[$i]); /*die();*/ // debug

	$color = '#'.dechex(rand(20,210)).dechex(rand(20,210)).dechex(rand(20,210));
	$login = $data[$i]["samaccountname"][0];
	$firstname = "?";
	$lastname = "?";
	$fullname = "?";
	$mail = null;
	$phone = null;
	$mobile = null;
	$description = null;
	if(isset($data[$i]["givenname"][0]))
		$firstname = $data[$i]["givenname"][0];
	if(isset($data[$i]["sn"][0]))
		$lastname = $data[$i]["sn"][0];
	if(isset($data[$i]["displayname"][0]))
		$fullname = $data[$i]["displayname"][0];
	if(isset($data[$i]["mail"][0]))
		$mail = $data[$i]["mail"][0];
	if(isset($data[$i]["telephonenumber"][0]))
		$phone = $data[$i]["telephonenumber"][0];
	if(isset($data[$i]["mobile"][0]))
		$mobile = $data[$i]["mobile"][0];
	if(isset($data[$i]["description"][0]))
		$description = $data[$i]["description"][0];

	// check group
	$groupCheck = false;
	if(LDAP_SYNC_GROUP == null) {
		$groupCheck = true;
	} else if(isset($data[$i]["memberof"])) {
		for($n=0; $n<$data[$i]["memberof"]["count"]; $n++) {
			if($data[$i]["memberof"][$n] == LDAP_SYNC_GROUP) {
				$groupCheck = true;
				break;
			}
		}
	}

	if($groupCheck) {
		echo "<===== ".$counter." =====>\n";
		echo "login       : ". $login ."\n";
		echo "name        : ". $firstname . " | " . $lastname . " | " . $fullname ."\n";
		echo "mail        : ". $mail ."\n";
		echo "phone       : ". $phone ."\n";
		echo "mobile      : ". $mobile ."\n";
		echo "description : ". $description ."\n";

		// add to found array
		$ldapUsers[] = $login;

		// check if user already exists
		$id = null;
		$checkResult = $db->getUserByLogin($login);
		if($checkResult != null) {
			$id = $checkResult->id;
			echo "--> found user in MASTERPLAN db with id: ".$id."\n";

			// update into db
			if($db->updateUserLdap($id, $checkResult->superadmin, $login, $firstname, $lastname, $fullname, $mail, $phone, $mobile, $description, 1))
				echo "--> updated successfully\n";
			else echo "--> ERROR updating: ".$db->getLastStatement()->error."\n";
		} else {
			echo "--> user not found in MASTERPLAN db - create new\n";

			// insert into db
			$currentDate = date('Y-m-d');
			if($db->insertUserLdap(0, $login, $firstname, $lastname, $fullname, $mail, $phone, $mobile, $currentDate, $description, 1, $color))
				echo "--> inserted successfully\n";
			else echo "--> ERROR inserting: ".$db->getLastStatement()->error."\n";
		}
		$counter ++;
	} else {
		echo "--> skip user ".htmlspecialchars($login)." : not in required group\n";
	}
}
ldap_close($ldapconn);

echo "<===== Check For Deleted Users... =====>\n";
foreach($db->getUsers() as $dbUser) {
	if($dbUser->ldap != 1) continue;
	$found = false;
	foreach($ldapUsers as $ldapUser) {
		if($dbUser->login == $ldapUser) {
			$found = true;
		}
	}
	if(!$found) {
		if($db->removeUser($dbUser->id)) echo "--> '".$dbUser->login."' deleted successfully\n";
		else echo "--> ERROR deleting '".$dbUser->login."': ".$db->getLastStatement()->error."\n";
	}
}
