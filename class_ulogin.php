<?php

/** 
 * Auth via uLogin.ru
 * @package SMF
 * @subpackage uLogin Package
 * @author uLogin team@ulogin.ru https://ulogin.ru/
 * @license GPL3 
 */

require_once($sourcedir . '/class_JSON.php'); 

class uLogin
{
	private $db = NULL; // database
	private $token = NULL; // uLogin token
	private $user = NULL; // uLogin user data
	
	private $max_level = 5; // max nesting level (method: __fetch_random_name)
	private $image_ext = 'jpg'; // avatar extension

	public function __construct($db = NULL)
	{
		$this->db = $db;
		
		if ($_POST['token'])
		{
			$this->token = $_POST['token'];
		}
		
		$this->__get_user();
	}
	
	/**
	 * Get current user email or generate random
	 * 
	 * @access 	private
	 * @param 	bool 		$random		if true will generate random email
	 * @return 	string				return email
	 */
	private function __fetch_random_email($random = false)
	{
		if (!$random && $this->user['email'])
		{
			if ($user = $this->__get_first("SELECT * FROM {db_prefix}members WHERE email_address = '" . mysql_escape_string($this->user['email']) . "'"))
			{
				return $this->__fetch_random_email(true);
			}
			
			return $this->user['email'];
		}
		
		return preg_replace('![^\w\d]*!','',$this->user['identity']).'@'.$this->user['network'].'.com';
	}
	
	/**
	 * Get current user name or generate random
	 * 
	 * @access 	private
	 * @param 	string 		$name		if set will append random string
	 * @param	int		$level		the higher the value the more random string will be in result
	 * @return 	string				return user name
	 */
	private function __fetch_random_name($name = '', $level = 0)
	{
		if ($level == $this->max_level)
		{
			return '';
		}
		
		if ($name)
		{
			$name = $name . $this->__random(1);
		}
		else if ($this->user['first_name'] && $this->user['last_name'])
		{
			$name = $this->user['first_name'] . '_' . $this->user['last_name'];
		}
		else if ($this->user['first_name'])
		{
			$name = $this->user['first_name'];
		}
		else if ($this->user['last_name'])
		{
			$name = $this->user['last_name'];
		}
		else
		{
			$name = 'uLogin' . $this->__random(5);
		}
		
		if ($user = $this->__get_first("SELECT * FROM {db_prefix}members WHERE member_name = '" . mysql_escape_string($name) . "'"))
		{
			return $this->__fetch_random_name($name, ($level + 1));
		}
		
		return $name;
	}
	
	/**
	 * Get current user location (city/country)
	 * 
	 * @access 	private
	 * @return 	string				return user location
	 */
	private function __fetch_user_from()
	{
		if ($this->user['country'] && $this->user['city'])
		{
			return ucfirst(strtolower($this->user['country'])) . ', ' . ucfirst(strtolower($this->user['city']));
		}
		else if ($this->user['country'])
		{
			return ucfirst(strtolower($this->user['country']));
		}
		else if ($this->user['city'])
		{
			return ucfirst(strtolower($this->user['city']));
		}
		
		return '';
	}
	
	/**
	 * Get first row from db
	 * 
	 * @access 	private
	 * @param	string		$query		query to database
	 * @return 	array				return db row
	 */
	private function __get_first($query = '')
	{
		if (!$query)
		{
			return false;
		}
		
		$result = $this->db['db_query']('', $query, array());
		$row = $this->db['db_fetch_assoc']($result);
		
		if ($row)
		{
			return $row;
		}
		
		return false;
	}
	
	/**
	 * Get user from ulogin.ru by token
	 * 
	 * @access 	private
	 * @return 	mixed				if token expired or some errors occurred will return NULL else will return user data
	 */
	private function __get_user()
	{
		if ($this->user)
		{
			return $this->user;
		}
		
		if ($this->token)
		{
			$info = file_get_contents('https://ulogin.ru/token.php?token=' . $this->token . '&host=' . $_SERVER['HTTP_HOST']);
			
			if (function_exists('json_decode'))
			{
				$this->user = json_decode($info, true);
			}
			else
			{
				$json = new Services_JSON();
				
				$this->user = $json->decode($info, true);
			}
			
			return $this->user;
		}
		
		return NULL;
	}
	
	/**
	 * Generate random string
	 * 
	 * @access 	private
	 * @param	int		$length		length of generating string
	 * @return 	string				return generated string
	 */
	private function __random($length = 10)
	{
		$random = '';
		
		for ($i = 0; $i < $length; $i++)
		{
			$random += chr(rand(48, 57));
		}
		
		return $random;
	}
	
	/**
	 * Upload current user avatar to server
	 * 
	 * @access 	private
	 * @return 	bool				return TRUE if avatar set, else return FALSE
	 */
	private function __upload_avatar($user_id)
	{
		global $modSettings;
		
		if (!$this->user['photo'] || !$user_id)
		{
			return false;
		}
			
		$db_name = 'avatar_' . $user_id . '_' . time() . '.' . $this->image_ext;
		$hash = md5($this->user['photo'] . time() . $user_id);
		$path = $modSettings['attachmentUploadDir'];
		
		if (!is_dir($path) || !is_writable($path))
		{
			return false;
		}
		
		$this->db['db_query']('', "INSERT INTO {db_prefix}attachments (id_member, filename, file_hash, fileext, mime_type) VALUES (" . $user_id . ", '" . $db_name . "', '" . $hash . "', '" . $this->image_ext . "', 'image/" . $this->image_ext . "')", array());
		
		if (!$attach = $this->__get_first("SELECT id_attach as id FROM {db_prefix}attachments WHERE id_member = " . $user_id))
		{
			return false;
		}
		
		$name = $attach['id'] . '_' . $hash;
		$file = rtrim($path, '/') . '/' . $name;
		
		$avatar = file_get_contents($this->user['photo']);
		
		$fp = fopen($file, "w+");
		fwrite($fp, $avatar);
		fclose($fp);
		
		if (file_exists($file))
		{
			list($width, $height) = getimagesize($file);
			
			$this->db['db_query']('', "UPDATE {db_prefix}attachments SET `size` = " . filesize($file) . ", `width` = " . $width . ", `height` = " . $height . " WHERE id_member = " . $user_id, array());
			
			return true;
		}
		
		$this->db['db_query']('', "DELETE FROM {db_prefix}attachments WHERE id_member = " . $user_id, array());
		
		return false;
	}
	
	/**
	 * Auth user
	 * 
	 * @access 	public
	 * @return 	bool				if user authorized return true, else return false
	 */
	public function auth()
	{
		if (!$this->user)
		{
			return false;
		}
		
		if (!$user = $this->__get_first("SELECT * FROM {db_prefix}ulogin WHERE identity = '" . mysql_escape_string($this->user['identity']) . "'"))
		{
			return false;
		}

		if (!$member = $this->__get_first("SELECT * FROM {db_prefix}members WHERE id_member = " . $user['userid']))
		{
			$this->db['db_query']('', "DELETE FROM {db_prefix}ulogin WHERE identity = '" . mysql_escape_string($this->user['identity']) . "'", array());
		
			return false;
		}
		
		return $member;
	}
	
	/**
	 * Register user
	 * 
	 * @access 	public
	 */
	public function register()
	{
		global $modSettings, $sourcedir;
		
		if (isset($this->user['error']) || !$this->token || !$this->user)
		{
			return false;
		}
		
		require_once($sourcedir . '/Subs-Members.php');
		
		$username = substr($this->__fetch_random_name(), 0, 80);
		$password = $this->__random(15);
		$register = array(
			'username' => $username,
			'email' => $this->__fetch_random_email(),
			'password' => $password,
			'password_check' => $password,
			'interface' => 'guest',
			'extra_register_vars' => array(
				'real_name' => str_replace('_', ' ', $username)
			),
			'require' => 'nothing',
			'check_password_strength' => false,
		);
		
		if ($this->user['bdate'])
		{
			$register['extra_register_vars']['birthdate'] = date('Y-m-d', strtotime($this->user['bdate']));
		}
		
		$modSettings['disableRegisterCheck'] = true;

		if ($user_id = registerMember($register))
		{
			$this->__upload_avatar($user_id);
			$this->db['db_query']('', "INSERT INTO {db_prefix}ulogin VALUES (NULL, " . $user_id . ", '" . mysql_escape_string($this->user['identity']) . "')", array());
			return true;
		}
		
		return false;
	}
}

