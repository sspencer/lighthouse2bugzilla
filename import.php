<?php

// pw for bugs + root: buggy
// set @id = LAST_INSERT_ID();


// defaults
define('PRODUCT_ID', 3);
define('COMPONENT_ID', 16);
define('BUG_SEVERITY', 'normal');
define('OP_SYS', 'All');
define('REP_PLATFORM', 'All');


//  7 | contributor@bugs.browserplus.org 
function m_name($name) {
	switch($name) {
		case "lloyd": return 1;
		case "steve": return 2;
		case "Gary MacDonald": return 3;
		case "durand": return 4;
		case "dgrigsby": return 5;
		default: return 7;
	}
}

function m_priority($priority) {
	return "---";
}

function m_milestone($milestone) {
	switch($milestone) {
		case "2.5":
		case "2.6":
		case "2.7":
		case "2.8":
		case "2.9":
			return $milestone;
		default:
			return "unspecified";
	}
}

function m_state($state)
{
	switch($state) {
		case "new":
		case "hold":
			return "NEW";
		case "open": 
			return "ACCEPTED";
		case "resolved":
		case "invalid": 
			return "RESOLVED";
		default: 
			return "NEW";
	}
}

// in:  2010-03-23T15:16:13-07:00
// out: 2010-03-22 22:37:00 -0700
function m_time($t) {
	return date("Y-m-d H:i:s O", strtotime($t));
}


function insert($table, $data) {
	$fields = join(", ", array_keys($data));
	$values = array();
	foreach($data as $v) {
		if ($v[0] == "@") {
			$values[] = $v;
		} else {
			$values[] = "'" . str_replace("'", "\\'", $v) . "'";
		}
	}
	$values = join(", ", $values);
	
	return "INSERT INTO $table ($fields) VALUES ($values);\n";
}

function insert_bug($data, $fulltext, $comments) {
	$sql = array();
	$sql[] = insert("bugs", $data);
	$sql[] = "set @bug_id = LAST_INSERT_ID();\n";
	
	$ft = array();
	$ft["bug_id"] = "@bug_id";
	$ft["short_desc"] = $data["short_desc"];
	$ft["comments"] = $fulltext;
	$ft["comments_noprivate"] = $fulltext;
	
	$sql[] = insert("bugs_fulltext", $ft);
	
	foreach($comments as $c) {
		$sql[] = insert("longdescs", $c);
	}

	return $sql;
}


if (count($argv) < 2) {
	echo "Usage: php ${argv[0]} file\n";
	exit;
}

$bugs = json_decode(file_get_contents($argv[1]), true);

function create_comment($name, $time, $body) {
	return array(
		"comment_id" => 0, 
		"bug_id"     => "@bug_id",
		"who"        => $name,
		"bug_when"   => $time,
		"work_time"  => 0,
		"thetext"    => $body,
		"isprivate"  => 0,
		"already_wrapped" => 0,
		"type"       => 0
	);
}


// lloyd@hilaiel.com, durand@yahoo-inc.com, dgrigsby@yahoo-inc.com, garymd@yahoo-inc.com, steve@bigfrog.net,
// else contributor@bugs.browserplus.org
// new, open, resolved, invalid and hold
// NEW, ACCEPTED, RESOLVED, RESOLVED, NEW
foreach($bugs as $bug) {
	$creation_ts = m_time($bug["created_at"]);
	$delta_ts    = m_time($bug["created_at"]);
	$version     = m_milestone($bug["milestone_title"]);
	$priority    = m_priority($bug["priority"]);
	$assigned_to = m_name($bug["assigned_user_name"]);
	$reporter    = m_name($bug["creator_name"]);
	$state       = m_state($bug["state"]);
	$short_desc	 = $bug["title"];
	$latest_body = $bug["latest_body"];
	
	$data = array(
		'bug_id'              => 0,
		'assigned_to'         => $assigned_to,
		'bug_severity'        => BUG_SEVERITY,
		'bug_status'          => $state,
		'creation_ts'         => $creation_ts, 
		'delta_ts'            => $delta_ts, 
		'short_desc'          => $short_desc, 
		'op_sys'              => OP_SYS,
		'priority'            => $priority,
		'product_id'          => PRODUCT_ID, 
		'rep_platform'        => REP_PLATFORM,
		'reporter'            => $reporter, 
		'version'             => $version,
		'component_id'        => COMPONENT_ID, 
		'resolution'          => "", 
		'target_milestone'    => "---", 
		'status_whiteboard'   => "",
		'keywords'            => "",
		'everconfirmed'       => 1, 
		'reporter_accessible' => 1, 
		'cclist_accessible'   => 1,
		'estimated_time'      => 0,
		'remaining_time'      => 0);

	$comments = array();

	// the bug description is comment #1
	$comments[] = create_comment($reporter, $creation_ts, $latest_body);

	foreach($bug["comments"] as $c) {
		$comments[] = create_comment(m_name($c["user_name"]), m_time($c["updated_at"]), $c["body"]);
	}
	
	$sql = insert_bug($data, $latest_body, $comments);
	echo join("\n", $sql);
}
