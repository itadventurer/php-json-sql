<?php
class sqlException extends Exception {
	private $additional=null;
	public function __construct($message, $code,$additional='') {
		$this->additional=$additional;
		parent::__construct($message,$code);
		echo parent::getTraceAsString();
	}
	public function getAdditional() {
		return $this->additional;
	}
}