<?php

class Scraping_Code {
	
	const SPECIFIED_MATCH_MODE_ALL = 1;
	private $_subject = '';
	private $_specified_subject = '';
	private $_specified_match_result = false;
	private $_current_except_properties = array();
	
	/*  Main  */
	
	public function __construct($subject='') {
		
		if($subject != '') {
			
			$this->setSubject($subject);
			
		}
		
	}

	public function setSubject($subject) {
	
		$this->_subject = $subject;
	
	}

	public function getSubject() {
	
		return $this->_subject;
	
	}
	
	public function getSpecifiedSubject() {
	
		return $this->_specified_subject;
	
	}
	
	/*  Subject  */
	
	public function stripLineBreak($replacement='') {

		$targets = array("\n", "\t", "\r", "\0");
		$this->_subject = str_replace($targets, $replacement, $this->_subject);

	}
	
	public function stripSpace($replacement='') {

		$subject = mb_convert_kana($this->_subject, 's');
		$this->_subject = str_replace(' ', $replacement, $this->_subject);

	}
	
	public function stripSpaceInTag() {

		$pattern = '|>[^<]+<|';
		$callback = array($this, 'stripAllSpaceInTagCallback');
		$this->_subject = preg_replace_callback($pattern, $callback, $this->_subject);

	}
	
	private function stripAllSpaceInTagCallback($matches) {
		
		$value = $matches[0];
		$value = preg_replace('|^>[\s]+|', '>', $value);
		return preg_replace('|[\s]+<$|', '<', $value);
		
	}

	public function stripTag($tags) {

		$tags = implode('|', $tags);
		$pattern = '!</?('. $tags .')\s[^>]*>!i';
		$this->_subject = preg_replace($pattern, '', $this->_subject);

	}

	public function stripTagPropertiy($tags) {

		$tags = implode('|', $tags);
		$pattern = '!<('. $tags .')\s[^>]*>!i';
		$this->_subject = preg_replace($pattern, '<$1>', $this->_subject);

	}
	
	public function stripTagPropertiyExcept($tag_data) {
		
		$callback = array($this, 'stripTagPropertiyExceptCallback');
		
		foreach ($tag_data as $tag_name => $properties) {

			$this->_current_except_properties = $properties;
			$pattern = '|<('. $tag_name .')\s([^>]+)>|i';
			$this->_subject = preg_replace_callback($pattern, $callback, $this->_subject);
			
		}

	}
	
	public function stripTagPropertiyExceptCallback($matches) {

		$except_properties = array();
		
		if(preg_match_all('|(([^\s=]+)=[\"\']?.*?[\"\']?)[\s>]+|', $matches[0], $matches_2)) {
			
			$matches_2_count = count($matches_2[0]);
			
			for ($i = 0; $i < $matches_2_count; $i++) {
				
				$property_name = $matches_2[2][$i];
				
				if(in_array($property_name, $this->_current_except_properties)) {
				
					$except_properties[] = $matches_2[1][$i];
					
				}
				
			}
			
		}
		
		$property = (count($except_properties) > 0) ? ' '. implode(' ', $except_properties) : '';
		return '<'. $matches[1] . $property .'>';
		
	}
	
	/*  Specified Match  */
	
	public function specifiedMatch($matches_pattern, $specified_pattern, &$matches) {
	
		$matches = $this->_specified_match($matches_pattern, $specified_pattern);
		return $this->_specified_match_result;
	
	}
	
	public function specifiedMatchAll($matches_pattern, $specified_pattern, &$matches) {
	
		$matches = $this->_specified_match($matches_pattern, $specified_pattern, self::SPECIFIED_MATCH_MODE_ALL);
		return $this->_specified_match_result;
	
	}
	
	private function _specified_match($matches_pattern, $specified_pattern, $mode=0) {
	
		$matches = array();
		$this->_specified_subject = $this->extractSpecifiedSubject($specified_pattern);
	
		if($mode == self::SPECIFIED_MATCH_MODE_ALL) {
		
			$this->_specified_match_result = preg_match_all($matches_pattern, $this->_specified_subject, $matches);
				
		} else {
			
			$this->_specified_match_result = preg_match($matches_pattern, $this->_specified_subject, $matches);
				
		}
	
		return $matches;
	
	}
	
	private function extractSpecifiedSubject($specified_pattern) {
	
		if(preg_match($specified_pattern, $this->_subject, $matches)) {
		
			return $matches[1];
				
		}
	
		return '';
	
	}
	
	/*  Combo Matches  */
	
	public function comboMatch($comboMatchParams, $full_flag, &$matches) {
		
		$combo_matches = array();
		
		foreach ($comboMatchParams as $comboMatchParam) {
		
			$pattern = $comboMatchParam->_pattern;
			
			if($comboMatchParam->_match_all_flag) {
			
				$result = preg_match_all($pattern, $this->_subject, $matches);
			
			} else {
			
				$result = preg_match($pattern, $this->_subject, $matches);
			
			}
			
			if(!$result && $full_flag) {
				
				return false;
				
			} else {
				
				foreach($comboMatchParam->_key_data as $key_name => $index_value) {
					
					if(is_array($index_value)) {

						$match_index = $index_value[0];
						$template = $index_value[1];
						$match_value = $this->getTemplateValue($matches[$match_index], $template);
						
					} else {

						$match_value = $matches[$index_value];
						
					}
					
					if($match_value != '') {
							
						$combo_matches[$key_name] = $match_value;
							
					}
						
				}
				
			}
			
		}
		
		$matches = $combo_matches;
		return true;
		
	}
	
	public function comboMatchParam($pattern, $var_data) {
		
		return new Scraping_Code_Combo_Match_Param($pattern, $var_data, false);
		
	}
	
	public function comboMatchAllParam($pattern, $var_data) {
		
		return new Scraping_Code_Combo_Match_Param($pattern, $var_data, true);
		
	}
	
	/*  Others  */
	
	public function getTemplateValue($value, $template) {
		
		if(is_array($value)) {
			
			foreach ($value as $key => $value_part) {
			
				$value[$key] = $this->getTemplateValue($value_part, $template);
				
			}
			
			return $value;
			
		}
		
		return str_replace('[{value}]', $value, $template);
		
	}
	
}

class Scraping_Code_Combo_Match_Param {
	
	public $_pattern;
	public $_key_data;
	public $_match_all_flag;
	
	public function __construct($pattern, $key_data, $match_all_flag) {
		
		$this->_pattern = $pattern;
		$this->_key_data = $key_data;
		$this->_match_all_flag = $match_all_flag;
		
	}
	
}

/*** Sample

	$scraping = new Scraping_Code();					// or new Scraping_Code($str);
	$scraping->setSubject($str);
	$scraping->stripLineBreak();
	$scraping->stripSpace();
	$scraping->stripSpaceIntag();						// e.g.) <p>	String1 String2	   </p> => <p>String1 String2</p>
	$scraping->stripTag(array('br', 'div', 'p'));
	$scraping->stripTagPropertiy(array('dd', 'div'));	// e.g.) <div class="class" id="id"> => <div>
	$scraping->stripTagPropertiyExcept(array(			// e.g.) <img src="***" style="***" class="***" id="***"> => <img src="***"> ...
			'img' => array('src'), 
			'div' => array('id', 'class')
	));
	
	echo $scraping->getSubject();


	// Matches in a Specified block

	$matches_pattern = '|<dl><dt>([^<]*)</dt><dd>([^<]*)</dd></dl>|';
	$specified_pattern = '|<ul id="girl_sukkin">(.*?)</ul>|';
	
	if($scraping->specifiedMatch($matches_pattern, $specified_pattern, $matches)) {
		
		print_r($matches);
		
	}
	
	if($scraping->specifiedMatchAll($matches_pattern, $specified_pattern, $matches)) {
		
		print_r($matches);
		
	}
	
	
	// Combo Matches
	
	$combo_match_params = array(
			
		$scraping->comboMatchParam('|<title>([^<]+)</title>|', array(
				
			'name' => 1
				
		)),
		$scraping->comboMatchAllParam('|<div class="([^"]+)">([^<]+)</div>|', array(
				
			'profile' => 2
				
		)),
		$scraping->comboMatchAllParam('|<img src="([^<]+)">|', array(
				
			'images' => array(1, 'http://example.com/images[{value}]')		// 1st arg => index, 2nd => template
				
		))
			
	);
	
	if($scraping->comboMatch($combo_match_params, true, $matches)) {
		
		print_r($matches);
		
	}

***/
