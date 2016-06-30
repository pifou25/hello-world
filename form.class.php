<?php 

/**
 * formulaire dynamique + protection antispam
 * ajout d'un champ caché 'salt' (hash) avec une valeur à vérifier
 * ajout d'un textarea caché qui doit rester vide
 * les noms des champs sont dynamiques
 */
class form {

	/**
	 * une constante pour crypter les noms
	 */
	const SALT = 'afg-94 (yF';

	protected $action = ''; //$_SERVER["REQUEST_URI"]; // URL
	
	/**
	* nom des champs et leurs attributs
	*/
	protected $fields = array(); 
	
	/**
	 * valeur des champs à récupérer dans $_POST
	 */
	protected $values = array();
	
	/**
	 * nom des champs cryptés
	 */
	protected $names = array();
	
	/**
	 * validation du formulaire
	 */
	protected $valide = false;
	/**
	 * si la validation a été faite ?
	 */
	protected $check = false;
	
	/**
	 * la clé de cryptage du formulaire
	 */
	protected $token;

	// debug 
	protected $debug = false;
	
	/**
	 * message d'erreur : en cas d'échec de validation
	 */
	protected $err;

	
	function __construct($cond = null)  {
	
		// champs supplémentaires si paramétré
		if(!empty($cond))
			foreach($cond as $key => $field)
				$this->fields[$key] = $field;

		// ajouter le message caché
		$this->fields['message'] = array('type'=>'textarea', 'name'=>'message', 'value'=>'',
			'label'=>array('style'=>'display:block;position:absolute;left:-9999px', 'txt'=>'Laisser cette zone vide.'));
	
		$this->action = $_SERVER["REQUEST_URI"];

	}
	
	/**
	 * générer le token, crypter les champs et rendre le formulaire
	 */
	public function getForm() {

		$date = get_date();
		$now = time();
		$token = sha1(self::SALT.$now.self::SALT);
		$signed = $now.'#'.$token;

		// crypter les noms des champs
		$fld = array();
		$return = "";
		foreach($this->fields as $key => $value) {
			$this->names[$key] = form::crypt($key, $token);
			$value['name'] = $this->names[$key];
			$fld[$key] = self::getField($value);
		}
		// ajouter le champ token
		$fld['token'] = "<input type=\"hidden\" name=\"token\" value=\"$signed\" />";
		
		return "<form method=\"post\" action=\"{$this->action}\">\n" . implode("<br />\n", $fld) . "\n</form>";

	}
	
	
	/**
	 * valider que le formulaire est fiable
	 */
	public function validateForm() {
	
		if ($this->check == false && !empty($_POST) && !empty($_POST['token'])) {

			// on récupere le token
			$token = $_POST['token'];
			list($when,$this->token) = explode('#',$token,2);
			if($when<(time()-30*60)) {
				$this->err = "Timeout ! 30 minutes maximum pour répondre.";
				return false;
			}
			else if ($this->token!==sha1(self::SALT.$when.self::SALT)) {
				$this->err = "SALT Error ! Formulaire invalide.";
				return false;
			}

			$fn = $this->fieldname('message');
			if (!empty($_POST[$fn])) {
				$this->err = "Message détecté (spam) ! Formulaire invalide.";
				return false;
			}

			$this->valide = true;
			
			// décrypter les noms des champs
			foreach($this->fields as $key => $value) {
				$this->names[$key] = $this->fieldname($key);
				$this->values[$key] = $_POST[$this->names[$key]];
			}

		}
		$this->check = true;
		return $this->valide;

	}
	
	
	public function fieldname($name) {
		return self::crypt($name, $this->token);
	}

	/**
	 * surcharge magique pour lire un résultat du formulaire. sans "protection"
	 */
	public function __get($name) {

		if(!$this->check)
			$this->validateForm();
		if(!$this->valide)
			return false;
			
		if($name == 'token')
			return $this->token;

		if(!isset($this->values[$name]))
			return false;
		
		return $this->values[$name];
	}
	
	
	/**
	 * surcharge magique pour tester l'existence d'un champ
	 */
	public function __isset($name) {
			
		if($name == 'token')
			return true;
	
		if(!$this->check)
			$this->validateForm();
		if(!$this->valide)
			return false;

		return isset($this->values[$name]);
	}
	
	
	/**
	 * surcharge magique pour supprimer une valeur d'un champ
	 */
	public function __unset($name) {
			
		if($name != 'token' && isset($this->values[$name]))
			unset($this->values[$name]);

	}
	
	
	/**
	 * surcharge magique pour écrire le formulaire
	 */
	public function __toString() {
		return $this->getForm();
	}
	
	
	/**
	 * rendre le tableau de tous les champs
	 */
	public function getValues() {
	
		if(!$this->check)
			$this->validateForm();
		if(!$this->valide)
			return false;
	
		return $this->values;
	}
	
	/**
	 * initialiser des valeurs par défaut du formulaire
	 */
	public function setValues($values) {
	
		foreach($values as $key => $val) {
			//$this->values[$key] = $val;
			$this->fields[$key]['value'] = $val;
		}
	}

	public function getError() { // msg de la dernière erreur
		return $this->err;
	}
	
	public function setDebug($debug) {
		$this->debug = $debug;
	}

	
	
	/**
	 * un array contient tous les attributs du champs
	 * getField permet de générer le tag <input ... />
	 * @param $field est un élément de $this->fields
	 * @return la balise HTML <input ... />
	 */
	static protected function getField($field) {
		// on gère le label
		if(isset($field['label'])) {
			$label = $field['label'];
			unset($field['label']);
		}
		$ret = '';
		$type = $field['type'];
		unset($field['type']);
		$default = isset($field['value']) ? $field['value'] : '';
		unset($field['value']);
		
		// préparer le champ
		foreach($field as $key => $value)
			$ret .= sprintf(' %s="%s"', $key, $value);
		
		// générer la balise input - ou textarea
		if($type == 'textarea') {
			$ret = "<$type $ret>$default</$type>";
		}else{
			$ret = sprintf('<input type="%s" %s value="%s" />', $type, $ret, $default);
		}
		
		// générer le label entourant la balise
		if(isset($label)) {
			$txt = isset($label['txt']) ? $label['txt'] : '';
			unset($label['txt']);
			$attr = '';
			foreach($label as $key => $value)
				$attr .= sprintf(' %s="%s"', $key, $value);
			$ret = "<label $attr>$txt\n$ret\n</label>";
		}
		
		return $ret;
	}

	public static function crypt($name,$salt) {
		return sha1($name.$salt.self::SALT);
	}

	
	
}