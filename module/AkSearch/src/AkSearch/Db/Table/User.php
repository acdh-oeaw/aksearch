<?php

namespace AkSearch\Db\Table;
use \VuFind\Db\Table\User as DefaultUserTable;

class User extends DefaultUserTable {
	
	
	/**
	 * Retrieve a user object from the database based on catalog ID.
	 *
	 * @param string	$catId 		Catalog ID.
	 * @param bool		$create		Should we create users that don't already exist?
	 *
	 * @return UserRow
	 */
	public function getByCatalogId($catId, $username = null, $create = true) {
		$row = $this->select(['cat_id' => $catId])->current();
		return ($create && empty($row)) ? $this->createRowForCatId($catId, $username) : $row;	
	}
	
	
	/**
	 * Create a row for the specified catalog ID.
	 *
	 * @param string $catId Catalog ID to use for retrieval.
	 *
	 * @return UserRow
	 */
	public function createRowForCatId($catId, $username) {
		$row = $this->createRow();
		$row->cat_id = $catId;
		$row->username = $username;
		$row->created = date('Y-m-d H:i:s');
		return $row;
	}
	
    
}
