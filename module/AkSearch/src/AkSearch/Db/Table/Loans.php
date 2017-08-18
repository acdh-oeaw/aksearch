<?php

namespace AkSearch\Db\Table;
use \VuFind\Db\Table\Gateway as Gateway;
use AkSearch\Db\Row\Loan;

class Loans extends Gateway {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('loans', 'AkSearch\Db\Row\Loan');
	}
	
	
	/**
	 * Retrieve a loan object from the database based on ils_user_id (this is normally
	 * the primary, not modifiable ID of the user in the ILS).
	 *
	 * @param string $ilsUserId Primary ILS user id to use for retrieval.
	 *
	 * @return array or null if nothing was found
	 */
	public function getByIlsUserId($ilsUserId) {
		$row = $this->select(['ils_user_id' => $ilsUserId])->toArray();
		return (empty($row)) ? null : $row;
	}
	
}

?>