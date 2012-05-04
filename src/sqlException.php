<?php
/**
 * Exception-Handler-Klasse
 * @package php-json-sql
 */
class sqlException extends Exception {
	/**
	 * Weitere Informationen zur Exception
	 * @var mixed
	 */
	private $additional=null;
	/**
	 * Erstellt die Exception
	 * @param string $message Die Error-Nachricht
	 * @param int $code Der Error-Code (Unix timestamp, Hardcoded)
	 * @param mixed $additional Weitere Informationen
	 */
	public function __construct($message, $code,$additional='') {
		$this->additional=$additional;
		parent::__construct($message,$code);
		echo parent::getTraceAsString();
	}
	/**
	 * Gibt die weiteren Informationen zurück
	 * @return mixed
	 */
	public function getAdditional() {
		return $this->additional;
	}
}