<?php

/*
 * formlaire pour poster des commentaires
 * contient : nom mail url et message
 *  étend formclass.php 
 * ajoute un captcha simpliste :
 * "Ecrivez le chiffre 'cinq' ... en chiffre :"
 * ajouter une fonction antiflood dans la validation
 */
class comment extends form {

	private $captcha = array('zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf');

	// protection anti flood : ne pas poster plus de X messages par minute
	protected $last_post;
	protected $first_post;
	protected $nb_posts;

	/**
	 * le constructeur crée automatiquement  7 champs pour ce formulair:
	 * nom email url question (texte)
	 * texto (textarea)
	 * submit (le bouton submit)
	 * answer (hidden)
	 * /
	function __construct($cond = null) {
	
	
		// liste des champs nécessaires - sauf token non crypté
		//$this->fields = array('nom', 'url', 'email', 'texto');
		$this->fields['nom'] = array('type'=>'text', 'name'=>'nom', 'size'=>18, 'maxlength'=>20, 'value'=>'', 'label'=>array('txt'=>'Nom : '));
		$this->fields['email'] = array('type'=>'text', 'name'=>'email', 'size'=>22, 'maxlength'=>40, 'value'=>'', 'label'=>array('txt'=>'Email : '));
		$this->fields['url'] = array('type'=>'text', 'name'=>'url', 'size'=>25, 'maxlength'=>50, 'value'=>'http://', 'label'=>array('txt'=>'URL : '));
		$this->fields['texto'] = array('type'=>'textarea', 'name'=>'texto', 'cols'=>60, 'rows'=>4, 'wrap'=>'virtual', 'value'=>'');
		$this->fields['submit'] = array('type'=>'submit', 'name'=>'Submit', 'value'=>'Envoyer',
			'label'=>array('txt'=>'Nom et message obligatoires.'));
		$this->fields['question'] = array('type'=>'text', 'size'=>2, 'name'=>'question');
		$this->fields['answer'] = array('type'=>'hidden', 'name'=>'answer');
			
		parent::__construct($cond);
	}
	

	function getForm() {
		
		$date = get_date();
		$now = time();
		$token = sha1(self::SALT.$now.self::SALT);
		$signed = $now.'#'.$token;

		// demander la question piège et ajouter la réponse
		$answer = rand(0,9);
		$this->fields['question']['label']['txt'] = 'Ecrivez le chiffre '. $this->captcha[$answer] . ' en chiffre :';
		$this->fields['answer']['value'] = $this->captcha[$answer];

		// crypter les noms des champs
		$fld = array();
		foreach($this->fields as $key => $value) {
			$this->names[$key] = form::crypt($key, $token);
			$value['name'] = $this->names[$key];
			$fld[$key] = parent::getField($value);
		}
		
		
		return <<<MYFORM
<form method="post" action="{$this->action}" name="critique">
  <table align="center">
	<tr><td>$date</td>
	<td>{$fld['nom']}</td>
	<td>{$fld['email']}</td>
	<td>{$fld['url']}</td>
	</tr><tr>
	<td colspan="4">{$fld['texto']}
	<br />
	{$fld['question']}
	{$fld['submit']}
	<input type="hidden" name="token" value="$signed" />	
	{$fld['answer']}

	{$fld['message']}
	</td></tr></table>
</form>
MYFORM;

	}
	
	public function validateForm() {
	
		if (parent::validateForm()) {
				
			// tester la bonne réponse au captcha
			if(!is_numeric($this->values['question']))
			{
				$this->valide = false;
				$this->err = 'Question = "'.$this->values['question'] . '" pas numérique';
			}
			else if (!isset($this->captcha[$this->values['question']]))
			{
				$this->valide = false;
				$this->err = 'Question = "'.$this->values['question'] . '" hors champs';
			}
			else if ($this->values['answer'] != $this->captcha[$this->values['question']])
			{
				$this->valide = false;
				$this->err = 'Question = "'.$this->values['answer'] . '" Reponse="' . $this->values['question'] .'"';
			}
			else
			{
				// formulaire valide
				$this->last_post = request('last_post', 'int', 'session');
				$this->first_post = request('first_post', 'int', 'session',0);
				$this->nb_posts = request('nb_posts', 'uint', 'session',0);
				
				// MAJ des infos antiflood en session
				$_SESSION['last_post'] = time();
				if(isset($_SESSION['nb_posts']))
					$_SESSION['nb_posts'] ++;
				else
				{
					$_SESSION['nb_posts'] = 1;
					$_SESSION['first_post'] = time(); // heure du 1er post
				}
			}
		}
		return $this->valide;

	}
	
	
	
	// fonction anti-flood : enregistrer en session les temps et nb de $_POST à chaque validation de formulaire
	function antiflood() {
	
		// protection anti flood : ne pas poster plus de X messages par minute
		$duree = time() - $this->last_post;
		$dureeh = time_elapsed($duree);

		if($this->last_post && $duree < 30) {
			$this->err = "Anti-spam : moins d'un message par 30 secondes svp ($duree = $dureeh).";
			return true;
		}
		if ($this->nb_posts > 20) {
			$duree = time() - $this->first_post;
			if ($this->first_post && $duree < 3600) {
				$this->err = "Anti-spam : moins de 20 messages par heure svp !! ($nb_posts, $duree, $dureeh)";
				return true;
			}
		}
		
		return false;

	}
	
}
?>
