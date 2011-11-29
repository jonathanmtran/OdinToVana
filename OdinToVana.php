<?php
/*
Copyright (C) 2009 LazurBeemz

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
function OdinToVana($user_id, $username, $password) {
	$db_config = array(
		"host" => "",
		"user" => "",
		"pass" => "",
		"source" => "",
		"destination" => "",
		// Configure the following to prevent duplicates. Example: [Converted]
		"prefix" => ""
	);

	$source_db = $db_config['source'];
	$destination_db = $db_config['destination'];

	mysql_connect($db_config['host'], $db_config['user'], $db_config['pass']);
	
	// Get userid
	$user_data = mysql_fetch_assoc(mysql_query(
		"SELECT
			`id`,
			`name`,
			`password`,
			`salt`
		FROM
			`" . $source_db . "`.`accounts`
		WHERE
			`name` = '" . mysql_real_escape_string($username) . "'"
	));
	
	if($user_data['password'] != hash("sha512", $password . $user_data['salt'])) {
		return FALSE;
	}
	
	// Get characters
	$characters = mysql_query(
		"SELECT
			*
		FROM
			`" . $source_db . "`.`characters`
		WHERE
			`accountid` = " . $user_data['id']
	);
	
	while($row = mysql_fetch_assoc($characters)) {
		// Character
		$character = mysql_fetch_assoc(mysql_query(
			"SELECT
				*
			FROM
				`" . $source_db . "`.`characters`
			WHERE
				`id` = " . $row['id']
		));
		
		$new_character = mysql_query(
			"INSERT INTO `" . $destination_db . "`.`characters`
				(
					`name`,
					`userid`,
					`world_id`,
					`level`,
					`job`,
					`str`,
					`dex`,
					`int`,
					`luk`,
					`chp`,
					`mhp`,
					`cmp`,
					`mmp`,
					`hpmp_ap`,
					`ap`,
					`sp`,
					`exp`,
					`fame`,
					`map`,
					`pos`,
					`gender`,
					`skin`,
					`eyes`,
					`hair`,
					`mesos`,
					`buddylist_size`
				)
			VALUES
				(
					'" . $character['name'] . "',
					" . $user_id . ",
					" . $character['world'] . ",
					" . $character['level'] . ",
					" . $character['job'] . ",
					" . $character['str'] . ",
					" . $character['dex'] . ",
					" . $character['int'] . ",
					" . $character['luk'] . ",
					" . $character['hp'] . ",
					" . $character['maxhp'] . ",
					" . $character['mp'] . ",
					" . $character['maxmp'] . ",
					" . ($character['hpApUsed'] + $character['mpApUsed']) . ",
					" . $character['ap'] . ",
					" . $character['sp'] . ",
					" . $character['exp'] . ",
					" . $character['fame'] . ",
					100000000,
					0,
					" . $character['gender'] . ",
					" . $character['skincolor'] . ",
					" . $character['face'] . ",
					" . $character['hair'] . ",
					" . $character['meso'] . ",
					" . $character['buddyCapacity'] . "
				)"
		);

		$id = mysql_insert_id();
		
		// Keymap
		$keymap_query = mysql_query(
			"SELECT
				*
			FROM
				`" . $source_db . "`.`keymap`
			WHERE
				`characterid` = " . $row['id']
		);
		while($key = mysql_fetch_assoc($keymap_query)) {
			
			$new_keymap = mysql_query(
				"INSERT INTO `" . $destination_db . "`.`keymap`
					(
						`charid`,
						`pos`,
						`type`,
						`action`
					)
				VALUES
					(
						" . $id . ",
						" . $key['key'] . ",
						" . $key['type'] . ",
						" . $key['action'] . "
					)"
			);
		}
	
		// Items and pets
		$items_query = mysql_query(
			"SELECT
				`inventorytype`,
				`position`,
				`itemid`,
				`quantity`,
				`upgradeslots`,
				`str`,
				`dex`,
				`int`,
				`luk`,
				`hp`,
				`mp`,
				`watk`,
				`matk`,
				`wdef`,
				`mdef`,
				`acc`,
				`avoid`,
				`hands`,
				`speed`,
				`jump`,
				`petid`
			FROM 
				`" . $source_db . "`.`inventoryitems`
			LEFT JOIN
				`" . $source_db . "`.`inventoryequipment`
			ON
				`inventoryitems`.`inventoryitemid` = `inventoryequipment`.`inventoryitemid`
			WHERE
				FLOOR(`itemid`/1000) != 1112 AND
				`storageid` IS NULL AND
				`inventorytype` != 0 AND
				`characterid` = " . $row['id']
		);

		while($item = mysql_fetch_assoc($items_query)) {
			$pet_id = 0;
			$pet_name = "''";

			if($item['petid'] != -1) {
				$pet = mysql_fetch_assoc(mysql_query(
					"SELECT
						*
					FROM
						`" . $source_db . "`.`pets`
					WHERE
						`petid` = " . $item['petid']
				));
				$new_pet = mysql_query(
					"INSERT INTO `" . $destination_db . "`.`pets`
						(
							`name`,
							`level`,
							`closeness`,
							`fullness`
						)
					VALUES
						(
							'" . $pet['name'] . "',
							" . $pet['level'] . ",
							" . $pet['closeness'] . ",
							" . $pet['fullness'] . "
						)"
				);
				$pet_id = mysql_insert_id();
				
				$new_item = mysql_query(
					"INSERT INTO `" . $destination_db . "`.`items`
						(
							`charid`,
							`inv`,
							`slot`,
							`itemid`,
							`amount`,
							`petid`,
							`name`
						)
					VALUES
						(
							" . $id . ",
							" . $item['inventorytype'] . ",
							" . $item['position'] . ",
							" . $item['itemid'] . ",
							" . $item['quantity'] . ",
							" . $pet_id . ",
							'" . $pet['name'] . "'
						)"
				);
			}
			else {
				$new_item = mysql_query(
					"INSERT INTO `" . $destination_db . "`.`items`
						(
							`charid`,
							`inv`,
							`slot`,
							`itemid`,
							`amount`,
							`slots`,
							`istr`,
							`idex`,
							`iint`,
							`iluk`,
							`ihp`,
							`imp`,
							`iwatk`,
							`imatk`,
							`iwdef`,
							`imdef`,
							`iacc`,
							`iavo`,
							`ihand`,
							`ispeed`,
							`ijump`
						)
					VALUES
						(
							" . $id . ",
							" . ($item['inventorytype'] < 0 ? -$item['inventorytype'] : $item['inventorytype']) . ",
							" . $item['position'] . ",
							" . $item['itemid'] . ",
							" . $item['quantity'] . ",
							" . (strlen($item['upgradeslots'] == 0) ? 0 : "'" . $item['upgradeslots'] . "'") . ",
							" . (strlen($item['str']) == 0 ? 0 : $item['str']) . ",
							" . (strlen($item['dex']) == 0 ? 0 : $item['dex']) . ",
							" . (strlen($item['int']) == 0 ? 0 : $item['int']) . ",
							" . (strlen($item['luk']) == 0 ? 0 : $item['luk']) . ",
							" . (strlen($item['hp']) == 0 ? 0 : $item['hp']) . ",
							" . (strlen($item['mp']) == 0 ? 0 : $item['mp']) . ",
							" . (strlen($item['watk']) == 0 ? 0 : $item['watk']) . ",
							" . (strlen($item['matk']) == 0 ? 0 : $item['matk']) . ",
							" . (strlen($item['wdef']) == 0 ? 0 : $item['wdef']) . ",
							" . (strlen($item['mdef']) == 0 ? 0 : $item['mdef']) . ",
							" . (strlen($item['acc']) == 0 ? 0 : $item['acc']) . ",
							" . (strlen($item['avoid']) == 0 ? 0 : $item['avoid']) . ",
							" . (strlen($item['hands']) == 0 ? 0 : $item['hands']) . ",
							" . (strlen($item['speed']) == 0 ? 0 : $item['speed']) . ",
							" . (strlen($item['jump']) == 0 ? 0 : $item['jump']) . "
						)"
				);
			}
		}

		// Quest status
		$quest_query = mysql_query(
			"SELECT
				`quest`,
				`mob`,
				`count`,
				`status`
			FROM
				`" . $source_db . "`.`queststatus`,
				`" . $source_db . "`.`queststatusmobs`
			WHERE
				`queststatus`.`queststatusid` = `queststatusmobs`.`queststatusid` AND
				`forfeited` = 0 AND
				`characterid` = " . $row['id']
		);

		while($quest = mysql_fetch_assoc($quest_query)) {
			if($quest['status'] == 1) {
				// Active
				$new_quest = mysql_query(
					"INSERT INTO `" . $destination_db . "`.`active_quests`
						(
							`charid`,
							`questid`,
							`mobid`,
							`mobskilled`
						)
					VALUES
						(
							" . $id . ",
							" . $quest['quest'] . ",
							" . $quest['mob'] .",
							" . $quest['count'] . "
						)"
				);
			}
			elseif($quest['status'] == 2) {
				// Completed
				$new_quest = mysql_query(
					"INSERT INTO `" . $destination_db . "`.`completed_quests`
						(
							`charid`,
							`questid`,
							`endtime`
						)
					VALUES
						(
							" . $id . ",
							" . $quest['quest'] . ",
							" . $quest['time'] ."
						)"
				);
			}
		}

		// Skills
		$skills_query = mysql_query(
			"SELECT
				`skillid`,
				`skilllevel`,
				`masterlevel`
			FROM
				`" . $source_db . "`.`skills`
			WHERE
				`characterid` = " . $row['id']
		);

		while($skill = mysql_fetch_assoc($skills_query)) {
			$new_skill = mysql_query(
				"INSERT INTO `" . $destination_db . "`.`skills`
				VALUES
					(
						" . $id . ",
						" . $skill['skillid'] . ",
						" . $skill['skilllevel'] . ",
						" . $skill['masterlevel'] . "
					)"
			);
			
		}
	}

	// Storage
	$storage_query = mysql_query(
		"SELECT
			*
		FROM
			`" . $source_db . "`.`storages`
		WHERE
			`accountid` = " . $user_data['id']
	);

	while($storage = mysql_fetch_array($storage_query)) {
		$storage_id = $storage['storageid'];
	
		$new_storage = mysql_query(
			"INSERT INTO `" . $destination_db . "`.`storage`
				(
					`userid`,
					`world_id`,
					`slots`,
					`mesos`
				)
			VALUES
				(
					" . $user_id . ",
					0,
					" . $storage['slots'] . ",
					" . $storage['meso'] . "
				)"
		);
		
		$new_storage_id = mysql_insert_id();
	
		$storage_items_query = mysql_query(
			"SELECT
				`inventorytype`,
				`position`,
				`itemid`,
				`quantity`,
				`upgradeslots`,
				`str`,
				`dex`,
				`int`,
				`luk`,
				`hp`,
				`mp`,
				`watk`,
				`matk`,
				`wdef`,
				`mdef`,
				`acc`,
				`avoid`,
				`hands`,
				`speed`,
				`jump`,
				`petid`
			FROM 
				`" . $source_db . "`.`inventoryitems`
			LEFT JOIN
				`" . $source_db . "`.`inventoryequipment`
			ON
				`inventoryitems`.`inventoryitemid` = `inventoryequipment`.`inventoryitemid`
			WHERE
				FLOOR(`itemid`/1000) != 1112 AND
				`inventorytype` != 0 AND
				`storageid` = " . $storage_id
		);

		while($storage_item = mysql_fetch_assoc($storage_items_query)) {
			$new_storage_item = mysql_query(
				"INSERT INTO `" . $destination_db . "`.`storageitems`
					(
						`userid`,
						`world_id`,
						`slot`,
						`itemid`,
						`amount`,
						`slots`,
						`istr`,
						`idex`,
						`iint`,
						`iluk`,
						`ihp`,
						`imp`,
						`iwatk`,
						`imatk`,
						`iwdef`,
						`imdef`,
						`iacc`,
						`iavo`,
						`ihand`,
						`ispeed`,
						`ijump`
					)
				VALUES
					(
						" . $id . ",
						0,
						" . $storage_item['position'] . ",
						" . $storage_item['itemid'] . ",
						" . $storage_item['quantity'] . ",
						" . (strlen($storage_item['upgradeslots'] == 0) ? 0 : "'" . $storage_item['upgradeslots'] . "'") . ",
						" . (strlen($storage_item['str']) == 0 ? 0 : $storage_item['str']) . ",
						" . (strlen($storage_item['dex']) == 0 ? 0 : $storage_item['dex']) . ",
						" . (strlen($storage_item['int']) == 0 ? 0 : $storage_item['int']) . ",
						" . (strlen($storage_item['luk']) == 0 ? 0 : $storage_item['luk']) . ",
						" . (strlen($storage_item['hp']) == 0 ? 0 : $storage_item['hp']) . ",
						" . (strlen($storage_item['mp']) == 0 ? 0 : $storage_item['mp']) . ",
						" . (strlen($storage_item['watk']) == 0 ? 0 : $storage_item['watk']) . ",
						" . (strlen($storage_item['matk']) == 0 ? 0 : $storage_item['matk']) . ",
						" . (strlen($storage_item['wdef']) == 0 ? 0 : $storage_item['wdef']) . ",
						" . (strlen($storage_item['mdef']) == 0 ? 0 : $storage_item['mdef']) . ",
						" . (strlen($storage_item['acc']) == 0 ? 0 : $storage_item['acc']) . ",
						" . (strlen($storage_item['avoid']) == 0 ? 0 : $storage_item['avoid']) . ",
						" . (strlen($storage_item['hands']) == 0 ? 0 : $storage_item['hands']) . ",
						" . (strlen($storage_item['speed']) == 0 ? 0 : $storage_item['speed']) . ",
						" . (strlen($storage_item['jump']) == 0 ? 0 : $storage_item['jump']) . "
					)"
			);
		}
	}

	// Prefix user account with KB so that they don't get converted again
	$prefix_user = mysql_query(
		"UPDATE
			`" . $source_db . "`.`accounts`
		SET
			`name` = '" . $db_config['prefix'] . $user_data['name'] . "'
		WHERE
			`id` = " . $user_data['id']
	);

	return TRUE;
}
