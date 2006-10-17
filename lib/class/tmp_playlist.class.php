<?php
/*

 Copyright (c) 2001 - 2006 Ampache.org
 All rights reserved.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

*/
/**
 * TempPlaylist Class
 * This class handles the temporary playlists in ampache, it handles the
 * tmp_playlist and tmp_playlist_data tables, and sneaks out at night to 
 * visit user_vote from time to time
 */
class tmpPlaylist { 

	/* Variables from the Datbase */
	var $id;
	var $session;
	var $type;
	var $object_type;
	var $base_playlist;

	/* Generated Elements */
	var $items = array(); 


	/**
	 * Constructor 
	 * This takes a playlist_id as an optional argument and gathers the information
	 * if not playlist_id is passed returns false (or if it isn't found 
	 */
	function tmpPlaylist($playlist_id = 0) { 

		if (!$playlist_id) { return false; }
		
		$this->id 	= intval($playlist_id);
		$info 		= $this->_get_info();

		/* If we get something back */
		if (count($info)) { 
			$this->session		= $info['session'];
			$this->type		= $info['type'];
			$this->object_type 	= $info['object_type'];
			$this->base_playlist	= $info['base_playlist'];
		} 

		return true;

	} // tmpPlaylist

	/** 
	 * _get_info
	 * This is an internal (private) function that gathers the information for this object from the 
	 * playlist_id that was passed in. 
	 */
	function _get_info() { 

		$sql = "SELECT * FROM tmp_playlist WHERE id='" . sql_escape($this->id) . "'";	
		$db_results = mysql_query($sql, dbh());

		$results = mysql_fetch_assoc($db_results);

		return $results;

	} // _get_info

	/**
	 * get_items
	 * This returns an array of all object_ids currently in this tmpPlaylist
	 */
	function get_items() { 

		$sql = "SELECT object_id FROM tmp_playlist_data " . 
			"WHERE tmp_playlist_data.tmp_playlist='" . sql_escape($this->id) . "'";
		$db_results = mysql_query($sql, dbh());

		while ($results = mysql_fetch_assoc($db_results)) { 
			$items[] = $results['id'];
		}

		return $items;

	} // get_items

	/** 
	 * ceate
	 * This function initializes a new tmpPlaylist it is assoicated with the current
	 * session rather then a user, as you could have same user multiple locations
	 */
	function create($sessid,$type,$object_type,$base_playlist) { 

		$sessid 	= sql_escape($sessid);
		$type		= sql_escape($type);
		$object_type	= sql_escape($object_type);
		$base_playlist	= sql_escape($base_playlist);

		$sql = "INSERT INTO tmp_playlist (`session`,`type`,`object_type`,`base_playlist`) " . 
			" VALUES ('$sessid','$type','$object_type','$base_playlist')";
		$db_results = mysql_query($sql, dbh());

		$id = mysql_insert_id(dbh());

		return $id;

	} // create 

	/**
	 * vote
	 * This function is called by users to vote on a system wide playlist
	 * This adds the specified objects to the tmp_playlist and adds a 'vote' 
	 * by this user, naturally it checks to make sure that the user hasn't
	 * already voted on any of these objects
	 */
	function vote($items) { 

		/* Itterate through the objects if no vote, add to playlist and vote */
		foreach ($items as $object_id) { 
			if (!$this->has_vote($object_id)) { 
				$this->add_vote($object_id);
			}
		} // end foreach


	} // vote

	/**
	 * add_vote
	 * This takes a object id and user and actually inserts the row
	 */
	function add_vote($object_id) { 

		$object_id = sql_escape($object_id);

		/* If it's on the playlist just vote */
		$sql = "SELECT id FROM tmp_playlist_data " . 
			"WHERE tmp_playlist_data.object_id='$object_id'";
		$db_results = mysql_query($sql, dbh());

		/* If it's not there, add it and pull ID */
		if (!$results = mysql_fetch_assoc($db_results)) { 
			$sql = "INSERT INTO tmp_playlist_data (`tmp_playlist`,`object_id`) " . 
				"VALUES ('-1','$object_id')";
			$db_results = mysql_query($sql, dbh());
			$results['id'] = mysql_insert_id(dbh());
		} 

		/* Vote! */
		$sql = "INSERT INTO user_vote (`user`,`object_id`) " . 
			"VALUES ('" . sql_escape($GLOBALS['user']->id) . "','" . $results['id'] . "')";
		$db_results = mysql_query($sql, dbh());

		return true;

	} // add_vote
	
	/**
	 * has_vote
	 * This checks to see if the current user has already voted on this object
	 */
	function has_vote($object_id) { 

		/* Query vote table */
		$sql = "SELECT tmp_playlist_data.id FROM user_vote " . 
			"INNER JOIN tmp_playlist_data ON tmp_playlist_data.id=user_vote.object_id " . 
			"WHERE user_vote.user='" . sql_escape($GLOBALS['user']->id) . "' " . 
			"      AND tmp_playlist_data.object_id='" . sql_escape($object_id) . "' " . 
			"      AND tmp_playlist_data.tmp_playlist='-1'";
		$db_results = mysql_query($sql, dbh());
		
		/* If we find  row, they've voted!! */
		if (mysql_num_rows($db_results)) { 
			return false; 
		}

		return true;		

	} // has_vote

	/**
	 * delete_track
	 * This deletes a track and any assoicated votes, we only check for
	 * votes if it's a -1 session
	 */
	function delete_track($id) { 

		$id = sql_escape($id);

		/* If this is a -1 session then kill votes as well */
		if ($this->session = '-1') { 
			$sql = "DELETE FROM user_vote WHERE object_id='$id'";
			$db_results = mysql_query($sql, dbh());
		}

		/* delete the track its self */
		$sql = "DELETE FROM tmp_playlist_data WHERE id='$id'";
		$db_results = mysql_query($sql,dbh());

	} // delete_track

} // class tmpPlaylist