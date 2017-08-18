<?php

namespace AkSearch\Db\Row;
use \Zend\Db\Sql\Expression;
use \Zend\Db\Sql\Predicate\Predicate;
use \Zend\Db\Sql\Sql;
use \Zend\Crypt\Symmetric\Mcrypt;
use \Zend\Crypt\Password\Bcrypt;
use \Zend\Crypt\BlockCipher as BlockCipher;
use \VuFind\Db\Row\RowGateway as RowGateway;

class Loan extends RowGateway implements \VuFind\Db\Table\DbTableAwareInterface {
	
	use \VuFind\Db\Table\DbTableAwareTrait;
	
	/**
	 * Constructor
	 *
	 * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
	 */
	public function __construct($adapter) {
		parent::__construct('id', 'loans', $adapter);
	}
}
?>