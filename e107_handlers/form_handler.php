<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2012 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * Form Handler
 *
*/

if (!defined('e107_INIT')) { exit; }

/**
 * 
 * @package e107
 * @subpackage e107_handlers
 * @version $Id$
 *
 * 
 * Automate Form fields creation. Produced markup is following e107 CSS/XHTML standards
 * If options argument is omitted, default values will be used (which OK most of the time)
 * Options are intended to handle some very special cases.
 *
 * Overall field options format (array or GET string like this one: var1=val1&var2=val2...):
 *
 *  - id => (mixed) custom id attribute value
 *  if numeric value is passed it'll be just appended to the name e.g. {filed-name}-{value}
 *  if false is passed id will be not created
 *  if empty string is passed (or no 'id' option is found)
 *  in all other cases the value will be used as field id
 * 	default: empty string
 *
 *  - class => (string) field class(es)
 * 	Example: 'tbox select class1 class2 class3'
 * 	NOTE: this will override core classes, so you have to explicit include them!
 * 	default: empty string
 *
 *  - size => (int) size attribute value (used when needed)
 *	default: 40
 *
 *  - title (string) title attribute
 *  default: empty string (omitted)
 *
 *  - readonly => (bool) readonly attribute
 * 	default: false
 *
 *  - selected => (bool) selected attribute (used when needed)
 * 	default: false
 *
 *  checked => (bool) checked attribute (used when needed)
 *  default: false
 *  - disabled => (bool) disabled attribute
 *  default: false
 *
 *  - tabindex => (int) tabindex attribute value
 *	default: inner tabindex counter
 *
 *  - other => (string) additional data
 *  Example: 'attribute1="value1" attribute2="value2"'
 *  default: empty string
 */
class e_form
{
	protected   $_tabindex_counter = 0;
	protected   $_tabindex_enabled = true;
	protected   $_cached_attributes = array();
	protected   $_field_warnings = array();
	private     $_inline_token;
	public      $_snippets = false; // use snippets or not. - experimental, and may be removed -  use at own risk.
	private     $_fontawesome = false;
	private     $_bootstrap;
	private     $_helptip = 1;
	/**
	 * @var user_class
	 */
	protected $_uc;

	protected $_required_string;

	/**
	 * @var e_parse
	 */
	protected $tp;

	/**
	 * @param $enable_tabindex
	 */
	public function __construct($enable_tabindex = false)
	{
		e107::loadAdminIcons(); // required below.
		e107::includeLan(e_LANGUAGEDIR.e_LANGUAGE. '/lan_form_handler.php');
		$this->_tabindex_enabled = $enable_tabindex;
		$this->_uc = e107::getUserClass();
		$this->setRequiredString('<span class="required text-warning">&nbsp;*</span>');

		if(defset('THEME_VERSION') === 2.3)
		{
			$this->_snippets = true;
		}

		if(deftrue('FONTAWESOME'))
		{
			$this->_fontawesome = true;
		}

		if(deftrue('BOOTSTRAP'))
		{
			$this->_bootstrap = (int) BOOTSTRAP;
		}

		$this->_helptip = (int) e107::getPref('admin_helptip', 1);

		$this->tp = e107::getParser();
	}


	/**
	 * @param $tmp
	 * @return array
	 * @see https://github.com/e107inc/e107/issues/3533
	 */
	private static function sort_get_files_output($tmp)
	{
		usort($tmp, static function ($left, $right) {
			$left_full_path = $left['path'] . $left['fname'];
			$right_full_path = $right['path'] . $right['fname'];
			return strcmp($left_full_path, $right_full_path);
		});
		return $tmp;
	}


	/**
	 * @param $field
	 * @return void
	 */
	public function addWarning($field)
	{
		$this->_field_warnings[] = $field;

	}

	/**
	 * Open a new form
	 * @param string name
	 * @param $method - post|get  default is post
	 * @param string target - e_REQUEST_URI by default
	 * @param array|string $options
	 * @return string
	 */
	public function open($name, $method=null, $target=null, $options=null)
	{
		if($target == null)
		{
			$target = e_REQUEST_URI;	
		}
		
		if($method == null)
		{
			$method = 'post';
		}

		$autoComplete 	= '';

		if(is_string($options))
		{
			parse_str($options, $options);
		}
	
		if(!empty($options['class']))
		{
			$class = $options['class'];
		}
		else  // default 
		{
			$class = "form-horizontal";
		}
		
		if(isset($options['autocomplete'])) // leave as isset()
		{
			$autoComplete = $options['autocomplete'] ? 'on' : 'off';
		}

		
		if($method === 'get' && strpos($target,'='))
		{
			list($url, $qry) = explode('?', $target);
			$text = "\n<form" . $this->attributes([
					'class'        => $class,
					'action'       => $url,
					'id'           => $this->name2id($name),
					'method'       => $method,
					'autocomplete' => $autoComplete,
				]) . ">\n";

			parse_str($qry, $m);
			foreach ($m as $k => $v)
			{
				$text .= $this->hidden($k, $v);
			}

		}
		else
		{
			$text = "\n<form" . $this->attributes([
					'class'        => $class,
					'action'       => $target,
					'id'           => $this->name2id($name),
					'method'       => $method,
					'autocomplete' => $autoComplete,
				]) . ">\n";
		}
		return $text;	
	}
	
	/**
	 * Close a Form
	 */
	public function close()
	{
		return '</form>';
		
	}


	/**
	 * Render a country drop-down list.
	 * @param string $name
	 * @param string $value
	 * @param array $options
	 * @return string
	 */
	public function country($name, $value, $options=array())
	{

		$arr = $this->getCountry();

		$placeholder = isset($options['placeholder']) ? $options['placeholder'] : ' ';

		return $this->select($name, $arr, $value, $options, $placeholder);
	}


	/**
	 * Get a list of countries.
	 * @param null|string $iso ISO code.
	 * @return array|mixed|string
	 */
	public function getCountry($iso=null)  // move to parser?
	{

		$c = array();

		 $c['af'] = 'Afghanistan';
		 $c['al'] = 'Albania';
		 $c['dz'] = 'Algeria';
		 $c['as'] = 'American Samoa';
		 $c['ad'] = 'Andorra';
		 $c['ao'] = 'Angola';
		 $c['ai'] = 'Anguilla';
		 $c['aq'] = 'Antarctica';
		 $c['ag'] = 'Antigua and Barbuda';
		 $c['ar'] = 'Argentina';
		 $c['am'] = 'Armenia';
		 $c['aw'] = 'Aruba';
		 $c['au'] = 'Australia';
		 $c['at'] = 'Austria';
		 $c['az'] = 'Azerbaijan';
		 $c['bs'] = 'Bahamas';
		 $c['bh'] = 'Bahrain';
		 $c['bd'] = 'Bangladesh';
		 $c['bb'] = 'Barbados';
		 $c['by'] = 'Belarus';
		 $c['be'] = 'Belgium';
		 $c['bz'] = 'Belize';
		 $c['bj'] = 'Benin';
		 $c['bm'] = 'Bermuda';
		 $c['bt'] = 'Bhutan';
		 $c['bo'] = 'Bolivia';
		 $c['ba'] = 'Bosnia-Herzegovina';
		 $c['bw'] = 'Botswana';
		 $c['bv'] = 'Bouvet Island';
		 $c['br'] = 'Brazil';
		 $c['io'] = 'British Indian Ocean Territory';
		 $c['bn'] = 'Brunei Darussalam';
		 $c['bg'] = 'Bulgaria';
		 $c['bf'] = 'Burkina Faso';
		 $c['bi'] = 'Burundi';
		 $c['kh'] = 'Cambodia';
		 $c['cm'] = 'Cameroon';
		 $c['ca'] = 'Canada';

		 $c['cv'] = 'Cape Verde';
		 $c['ky'] = 'Cayman Islands';
		 $c['cf'] = 'Central African Republic';
		 $c['td'] = 'Chad';
		 $c['cl'] = 'Chile';
		 $c['cn'] = 'China';
		 $c['cx'] = 'Christmas Island';
		 $c['cc'] = 'Cocos (Keeling) Islands';
		 $c['co'] = 'Colombia';
		 $c['km'] = 'Comoros';
		 $c['cg'] = 'Congo';
		 $c['cd'] = 'Congo (Dem.Rep)';
		 $c['ck'] = 'Cook Islands';
		 $c['cr'] = 'Costa Rica';
		 $c['hr'] = 'Croatia';
		 $c['cu'] = 'Cuba';
		 $c['cy'] = 'Cyprus';
		 $c['cz'] = 'Czech Republic';
		 $c['dk'] = 'Denmark';
		 $c['dj'] = 'Djibouti';
		 $c['dm'] = 'Dominica';
		 $c['do'] = 'Dominican Republic';
		 $c['tp'] = 'East Timor';
		 $c['ec'] = 'Ecuador';
		 $c['eg'] = 'Egypt';
		 $c['sv'] = 'El Salvador';
		 $c['gq'] = 'Equatorial Guinea';
		 $c['er'] = 'Eritrea';
		 $c['ee'] = 'Estonia';
		 $c['et'] = 'Ethiopia';
		 $c['fk'] = 'Falkland Islands';
		 $c['fo'] = 'Faroe Islands';
		 $c['fj'] = 'Fiji';
		 $c['fi'] = 'Finland';
		// $c['cs'] = "Former Czechoslovakia";
		// $c['su'] = "Former USSR";
		 $c['fr'] = 'France';
		// $c['fx'] = "France (European Territory)";
		 $c['gf'] = 'French Guyana';
		 $c['tf'] = 'French Southern Territories';
		 $c['ga'] = 'Gabon';
		 $c['gm'] = 'Gambia';
		 $c['ge'] = 'Georgia';
		 $c['de'] = 'Germany';
		 $c['gh'] = 'Ghana';
		 $c['gi'] = 'Gibraltar';
		 $c['gr'] = 'Greece';
		 $c['gl'] = 'Greenland';
		 $c['gd'] = 'Grenada';
		 $c['gp'] = 'Guadeloupe (French)';
		 $c['gu'] = 'Guam (USA)';
		 $c['gt'] = 'Guatemala';
		 $c['gn'] = 'Guinea';
		 $c['gw'] = 'Guinea Bissau';
		 $c['gy'] = 'Guyana';
		 $c['ht'] = 'Haiti';
		 $c['hm'] = 'Heard and McDonald Islands';
		 $c['hn'] = 'Honduras';
		 $c['hk'] = 'Hong Kong';
		 $c['hu'] = 'Hungary';
		 $c['is'] = 'Iceland';
		 $c['in'] = 'India';
		 $c['id'] = 'Indonesia';
		 $c['ir'] = 'Iran';
		 $c['iq'] = 'Iraq';
		 $c['ie'] = 'Ireland';
		 $c['il'] = 'Israel';
		 $c['it'] = 'Italy';
		 $c['ci'] = "Ivory Coast (Cote D'Ivoire)";
		 $c['jm'] = 'Jamaica';
		 $c['jp'] = 'Japan';
		 $c['jo'] = 'Jordan';
		 $c['kz'] = 'Kazakhstan';
		 $c['ke'] = 'Kenya';
		 $c['ki'] = 'Kiribati';
		 $c['kp'] = 'Korea (North)';
		 $c['kr'] = 'Korea (South)';
		 $c['kw'] = 'Kuwait';
		 $c['kg'] = 'Kyrgyzstan';
		 $c['la'] = 'Laos';
		 $c['lv'] = 'Latvia';
		 $c['lb'] = 'Lebanon';
		 $c['ls'] = 'Lesotho';
		 $c['lr'] = 'Liberia';
		 $c['ly'] = 'Libya';
		 $c['li'] = 'Liechtenstein';
		 $c['lt'] = 'Lithuania';
		 $c['lu'] = 'Luxembourg';
		 $c['mo'] = 'Macau';
		 $c['mk'] = 'Macedonia';
		 $c['mg'] = 'Madagascar';
		 $c['mw'] = 'Malawi';
		 $c['my'] = 'Malaysia';
		 $c['mv'] = 'Maldives';
		 $c['ml'] = 'Mali';
		 $c['mt'] = 'Malta';
		 $c['mh'] = 'Marshall Islands';
		 $c['mq'] = 'Martinique (French)';
		 $c['mr'] = 'Mauritania';
		 $c['mu'] = 'Mauritius';
		 $c['yt'] = 'Mayotte';
		 $c['mx'] = 'Mexico';
		 $c['fm'] = 'Micronesia';
		 $c['md'] = 'Moldavia';
		 $c['mc'] = 'Monaco';
		 $c['mn'] = 'Mongolia';
		 $c['me'] = 'Montenegro';
		 $c['ms'] = 'Montserrat';
		 $c['ma'] = 'Morocco';
		 $c['mz'] = 'Mozambique';
		 $c['mm'] = 'Myanmar';
		 $c['na'] = 'Namibia';
		 $c['nr'] = 'Nauru';
		 $c['np'] = 'Nepal';
		 $c['nl'] = 'Netherlands';
		 $c['an'] = 'Netherlands Antilles';
		 // $c['net'] = "Network";

		 $c['nc'] = 'New Caledonia (French)';
		 $c['nz'] = 'New Zealand';
		 $c['ni'] = 'Nicaragua';
		 $c['ne'] = 'Niger';
		 $c['ng'] = 'Nigeria';
		 $c['nu'] = 'Niue';
		 $c['nf'] = 'Norfolk Island';

		 $c['mp'] = 'Northern Mariana Islands';
		 $c['no'] = 'Norway';
		//  $c['arpa'] = "Old style Arpanet";
		 $c['om'] = 'Oman';
		 $c['pk'] = 'Pakistan';
		 $c['pw'] = 'Palau';
		 $c['pa'] = 'Panama';
		 $c['pg'] = 'Papua New Guinea';
		 $c['py'] = 'Paraguay';
		 $c['pe'] = 'Peru';
		 $c['ph'] = 'Philippines';
		 $c['pn'] = 'Pitcairn Island';
		 $c['pl'] = 'Poland';
		 $c['pf'] = 'Polynesia (French)';
		 $c['pt'] = 'Portugal';
		 $c['pr'] = 'Puerto Rico';
		 $c['ps'] = 'Palestine';
		 $c['qa'] = 'Qatar';
		 $c['re'] = 'Reunion (French)';
		 $c['ro'] = 'Romania';
		 $c['ru'] = 'Russia';
		 $c['rw'] = 'Rwanda';
		 $c['gs'] = 'S. Georgia &amp; S. Sandwich Isls.';
		 $c['sh'] = 'Saint Helena';
		 $c['kn'] = 'Saint Kitts &amp; Nevis';
		 $c['lc'] = 'Saint Lucia';
		 $c['pm'] = 'Saint Pierre and Miquelon';
		 $c['st'] = 'Saint Tome (Sao Tome) and Principe';
		 $c['vc'] = 'Saint Vincent &amp; Grenadines';
		 $c['ws'] = 'Samoa';
		 $c['sm'] = 'San Marino';
		 $c['sa'] = 'Saudi Arabia';
		 $c['sn'] = 'Senegal';
		 $c['rs'] = 'Serbia';
		 $c['sc'] = 'Seychelles';
		 $c['sl'] = 'Sierra Leone';
		 $c['sg'] = 'Singapore';
		 $c['sk'] = 'Slovak Republic';
		 $c['si'] = 'Slovenia';
		 $c['sb'] = 'Solomon Islands';
		 $c['so'] = 'Somalia';
		 $c['za'] = 'South Africa';

		 $c['es'] = 'Spain';
		 $c['lk'] = 'Sri Lanka';
		 $c['sd'] = 'Sudan';
		 $c['sr'] = 'Suriname';
		 $c['sj'] = 'Svalbard and Jan Mayen Islands';
		 $c['sz'] = 'Swaziland';
		 $c['se'] = 'Sweden';
		 $c['ch'] = 'Switzerland';
		 $c['sy'] = 'Syria';
		 $c['tj'] = 'Tadjikistan';
		 $c['tw'] = 'Taiwan';
		 $c['tz'] = 'Tanzania';
		 $c['th'] = 'Thailand';
		 $c['ti'] = 'Tibet';
		 $c['tg'] = 'Togo';
		 $c['tk'] = 'Tokelau';
		 $c['to'] = 'Tonga';
		 $c['tt'] = 'Trinidad and Tobago';
		 $c['tn'] = 'Tunisia';
		 $c['tr'] = 'Turkey';
		 $c['tm'] = 'Turkmenistan';
		 $c['tc'] = 'Turks and Caicos Islands';
		 $c['tv'] = 'Tuvalu';
		 $c['ug'] = 'Uganda';
		 $c['ua'] = 'Ukraine';
		 $c['ae'] = 'United Arab Emirates';
		 $c['gb'] = 'United Kingdom';
		 $c['us'] = 'United States';
		 $c['uy'] = 'Uruguay';
		 $c['um'] = 'US Minor Outlying Islands';
		 $c['uz'] = 'Uzbekistan';
		 $c['vu'] = 'Vanuatu';
		 $c['va'] = 'Vatican City State';
		 $c['ve'] = 'Venezuela';
		 $c['vn'] = 'Vietnam';
		 $c['vg'] = 'Virgin Islands (British)';
		 $c['vi'] = 'Virgin Islands (USA)';
		 $c['wf'] = 'Wallis and Futuna Islands';
		 $c['eh'] = 'Western Sahara';
		 $c['ye'] = 'Yemen';

		// $c['zr'] = "(deprecated) Zaire";
		 $c['zm'] = 'Zambia';
		 $c['zw'] = 'Zimbabwe';


        if(!empty($iso) && !empty($c[$iso]))
        {
            return $c[$iso];
        }


		return ($iso === null) ? $c : '';

	}


	/**
	 * Get required field markup string
	 * @return string
	 */
	public function getRequiredString()
	{
		return $this->_required_string;
	}

	/**
	 * Set required field markup string
	 * @param string $string
	 * @return e_form
	 */
	public function setRequiredString($string)
	{
		$this->_required_string = $string;
		return $this;
	}
	
	// For Comma separated keyword tags.

	/**
	 * @param $name
	 * @param $value
	 * @param int $maxlength
	 * @param string|array $options
	 * @return string
	 */
	public function tags($name, $value, $maxlength = 200, $options = null)
	{
	  if(is_string($options))
	  {
		  parse_str($options, $options);
	  }

	  $defaults['selectize'] = array(
		'create'   => true,
		'maxItems' => vartrue($options['maxItems'], 7),
		'mode'     => 'multi',
		'plugins'  => array('remove_button'),
	  );

	  $options = array_replace_recursive($defaults, $options);

	  return $this->text($name, $value, $maxlength, $options);
	}


	/**
	 * Render Bootstrap Tabs
	 *
	 * @param array $array
	 * @param array $options = [
	 *      'active'    => (string|int) - array key of the active tab.
	 *      'fade'      => (bool) - use fade effect or not.
	 *      'class'     => (string) - custom css class of the tab content container
	 * ]
	 * @return string html
	 * @example
	 *        $array = array(
	 *        'home' => array('caption' => 'Home', 'text' => 'some tab content' ),
	 *        'other' => array('caption' => 'Other', 'text' => 'second tab content' )
	 *        );
	 */
	public function tabs($array, $options = array())
	{
		$initTab = varset($options['active'], false);

		if(is_numeric($initTab))
		{
			$initTab = 'tab-'.$initTab;
		}

		$id = !empty($options['id']) ? 'id="'.$options['id'].'" ' : '';
		$toggle = ($this->_bootstrap > 3) ? 'data-bs-toggle="tab"' : 'data-toggle="tab"';

		$text  ='
		<!-- Nav tabs -->
			<ul '.$id.'class="nav nav-tabs">';

		$c = 0;

		$act = $initTab;
		foreach($array as $key=>$tab)
		{

			if(is_numeric($key))
			{
				$key = 'tab-'.$key;
			}

			if($c === 0 && ($act === false))
			{
				$act = $key;
			}

			$active = ($key == $act) ? ' active' : '';
			$text .= '<li class="nav-item'.$active.'"><a class="nav-link'.$active.'" href="#'.$key.'" '.$toggle.'>'.$tab['caption'].'</a></li>';
			$c++;
		}
		
		$text .= '</ul>';

		$tabClass = varset($options['class'],null);
		$fade = !empty($options['fade']) ? ' fade' : '';
		$show = !empty($options['fade']) ? ($this->_bootstrap > 3 ?  ' show' : ' in') : '';

		$text .= '
		<!-- Tab panes -->
		<div class="tab-content '.$tabClass.'">';
		
		$c=0;
		$act = $initTab;
		foreach($array as $key=>$tab)
		{

			if(is_numeric($key))
			{
				$key = 'tab-'.$key;
			}

			if($c == 0 && ($act === false))
			{
				$act = $key;
			}

			$active = ($key == $act) ? $show.' active' : '';
			$text .= '<div class="tab-pane'.$fade.$active.'" id="'.$key.'" role="tabpanel">'.$tab['text'].'</div>';
			$c++;
		}
		
		$text .= '
		</div>';

		return $text;

	}


	/**
	 * Render Bootstrap Carousel
	 * @param string $name : A unique name
	 * @param array $array
	 * @param array $options : default, interval, pause, wrap, navigation, indicators
	 * @return string|array
	 * @example
	 * $array = array(
	 *        'slide1' => array('caption' => 'Slide 1', 'text' => 'first slide content' ),
	 *        'slide2' => array('caption' => 'Slide 2', 'text' => 'second slide content' ),
	 *        'slide3' => array('caption' => 'Slide 3', 'text' => 'third slide content' )
	 *    );
	 */
	public function carousel($name= 'e-carousel', $array=array(), $options = null)
	{
		$indicators = '';
		$controls   = '';
				
		$act = varset($options['default'], 0);

		$navigation = isset($options['navigation']) ? $options['navigation'] : true;
		$indicate = isset($options['indicators']) ? $options['indicators'] : true;

		$prefix = ($this->_bootstrap > 4) ? 'data-bs-' : 'data-';

		$att = [
				'id'            => $name,
				'class'         => 'carousel slide'
		];

		$att[$prefix.'ride'] = 'carousel';

		if(isset($options['wrap']))
		{
			$att[$prefix.'wrap'] = (bool) $options['wrap'];
		}

		if(isset($options['interval']))
		{
			$att[$prefix.'interval'] = (int) $options['interval'];
		}

		if(isset($options['pause']))
		{
			$att[$prefix.'pause'] = (string) $options['pause'];
		}


		$start = '
		<!-- Carousel -->
		
		<div' .$this->attributes($att) . '>';

		if($indicate && (count($array) > 1))
		{
			$indicators = '
	        <!-- Indicators -->
	        <ol class="carousel-indicators">
			';

			$c = 0;
			foreach($array as $key=>$tab)
			{
				$active = ($c == $act) ? ' class="active"' : '';
				$indicators .=  '<li '.$prefix.'target="#'.$name.'" '.$prefix.'slide-to="'.$c.'" '.$active.'></li>';
				$c++;
			}

			$indicators .= '
			</ol>';
		}

		$inner = '

		<div class="carousel-inner">
		';

		
		$c=0;
		foreach($array as $key=>$tab)
		{
			$active = ($c == $act) ? ' active' : '';
			$label = !empty($tab['label']) ? ' '.$prefix.'label="'.$tab['label'].'"' : '';
			$inner .= '<div class="carousel-item item'.$active.'" id="'.$key.'"'.$label.'>';
			$inner .= $tab['text'];
			
			if(!empty($tab['caption']))
			{
				$inner .= '<div class="carousel-caption">'.$tab['caption'].'</div>';
			}
			
			$inner .= '</div>';
			$c++;
		}
		
		$inner .= '
		</div>';

		if($navigation && (count($array) > 1))
		{
			$controls = '
			<a class="left carousel-control carousel-left" href="#'.$name.'" role="button" '.$prefix.'slide="prev">
	        <span class="glyphicon glyphicon-chevron-left"></span>
			</a>
			<a class="right carousel-control carousel-right" href="#'.$name.'" role="button" '.$prefix.'slide="next">
			<span class="glyphicon glyphicon-chevron-right"></span>
			</a>';
		}

		$end = '</div><!-- End Carousel -->';

		if(!empty($options['data']))
		{
			return compact('start', 'indicators', 'inner', 'controls', 'end');
		}

		return $start.$indicators.$inner.$controls.$end; // $text;

	}

	/**
	 * Same as $this->text() except it adds input validation for urls.
	 * At this stage, checking only for spaces. Should include sef-urls.
	 *
	 * @param string  $name
	 * @param string $value
	 * @param int    $maxlength
	 * @param array  $options
	 * @return string
	 */
	public function url($name, $value = '', $maxlength = 80, $options= array())
	{
		$options['pattern'] = '^\S*$';
		return $this->text($name, $value, $maxlength, $options);
	}

	/**
	 * Text-Field Form Element
	 * @param $name
	 * @param $value
	 * @param $maxlength
	 * @param array|string $options
	 *  - size: mini, small, medium, large, xlarge, xxlarge
	 *  - class:
	 *  - typeahead: 'users'
	 *
	 * @return string
	 */
	public function text($name, $value = '', $maxlength = 80, $options= null)
	{
		if (is_string($options))
		{
			parse_str($options, $options);
		}

		$attributes = [
			'type'  => varset($options['type']) === 'email' ? 'email' : 'text',
			'name'  => $name,
			'value' => $value,
		];

		if (!vartrue($options['class']))
		{
			$options['class'] = 'tbox';
		}

		if (deftrue('BOOTSTRAP'))
		{
			$options['class'] .= ' form-control';
		}

		/*
		if(!vartrue($options['class']))
		{
			if($maxlength < 10)
			{
				$options['class'] = 'tbox input-text span3';
			}
			
			elseif($maxlength < 50)
			{
				$options['class'] = 'tbox input-text span7';	
			}
		
			elseif($maxlength > 99)
			{
				 $options['class'] = 'tbox input-text span7';
			}
			else
			{
				$options['class'] = 'tbox input-text';
			}
		}	
		*/

		if(!empty($options['selectize']))
		{
			e107::js('core', 'selectize/js/selectize.min.js', 'jquery');
			$css = !empty($this->_bootstrap) ? 'selectize/css/selectize.bootstrap'.$this->_bootstrap.'.css' : 'selectize/css/selectize.css';
			e107::css('core', $css, 'jquery');

			// Load selectize behavior.
			e107::js('core', 'selectize/js/selectize.init.js', 'jquery');

			$options['selectize']['wrapperClass'] = 'selectize-control';
			$options['selectize']['inputClass'] = 'form-control selectize-input ';
			$options['selectize']['dropdownClass'] = 'selectize-dropdown';
			$options['selectize']['dropdownContentClass'] = 'selectize-dropdown-content';
			$options['selectize']['copyClassesToDropdown'] = true;

			$jsSettings = array(
				'id'      => vartrue($options['id'], $this->name2id($name)),
				'options' => $options['selectize'],
				// Multilingual support.
				'strings' => array(
						'anonymous' => LAN_ANONYMOUS,
				),
			);

			// Merge field settings with other selectize field settings.
			e107::js('settings', array('selectize' => array($jsSettings)));

			$options['class'] = '';
		}

		// TODO: remove typeahead.
		if (!empty($options['typeahead']) && vartrue($options['typeahead']) === 'users')
		{
			$options['data-source'] = e_BASE . 'user.php';
			$options['class'] .= ' e-typeahead';
		}

		if (!empty($options['size']) && !is_numeric($options['size']))
		{
			$options['class'] .= ' input-' . $options['size'];
			unset($options['size']); // don't include in html 'size='. 	
		}

		$attributes['maxlength'] = !empty($maxlength) ? $maxlength : null;

		$options = $this->format_options('text', $name, $options);


		//never allow id in format name-value for text fields
		return "<input" . $this->attributes($attributes) . " " . $this->get_attributes($options, $name) . ' />';
	}


	/**
	 * Create a input [type number]
	 *
	 * Additional options:
	 *   - decimals: default 0; defines the number of decimals allowed in this field (0 = only integers; 1 = integers & floats with 1 decimal e.g. 4.1, etc.)
	 *   - step: default 1; defines the step for the spinner and the max. number of decimals. If decimals is given, step will be ignored
	 *   - min: default 0; minimum value allowed
	 *   - max: default empty; maximum value allowed
	 *   - pattern: default empty; allows to define an complex input pattern
	 * 
	 * @param string $name
	 * @param integer $value
	 * @param integer $maxlength
	 * @param array|string $options decimals, step, min, max, pattern
	 * @return string
	 */
	public function number($name, $value=0, $maxlength = 200, $options = null)
	{
		$attributes = [
			'type'  => 'number',
			'name'  => $name,
			'value' => $value,
		];

		if (is_string($options))
		{
			parse_str($options, $options);
		}

		if (!empty($options['maxlength']))
		{
			$maxlength = $options['maxlength'];
		}

		unset($options['maxlength']);

		if (empty($options['size']))
		{
			$options['size'] = 15;
		}
		if (empty($options['class']))
		{
			$options['class'] = 'tbox number e-spinner input-small ';
		}

		if (!empty($options['size']))
		{
			$options['class'] .= ' input-' . $options['size'];
			unset($options['size']);
		}

		$options['class'] .= ' form-control';
		$options['type'] = 'number';

		// Not used anymore
		//$mlength = vartrue($maxlength) ? "maxlength=".$maxlength : "";

		// Always define the min. parameter
		// defaults to 0
		// setting the min option to a negative value allows negative inputs
		$attributes['min'] = vartrue($options['min'], '0');
		$attributes['max'] = isset($options['max']) ? $options['max'] : null;


		if (empty($options['pattern']))
		{
			$options['pattern'] = '^';
			// ^\-?[0-9]*\.?[0-9]{0,2}
			if (varset($options['min'], 0) < 0)
			{
				$options['pattern'] .= '\-?';
			}
			$options['pattern'] .= '[0-9]*';

			// Integer & Floaat/Double value handling
			if (isset($options['decimals']))
			{
				if ((int) $options['decimals'] > 0)
				{
					$options['pattern'] .= '\.?[0-9]{0,'. (int) $options['decimals'] .'}';
				}

				// defined the step based on number of decimals 
				// 2 = 0.01 > allows integers and float numbers with up to 2 decimals (3.1 = OK; 3.12 = OK; 3.123 = NOK)
				// 1 = 0.1 > allows integers and float numbers with up to 2 decimals (3.1 = OK; 3.12 = NOK)
				// 0 = 1 > allows only integers, no float values
				if ((int) $options['decimals'] <= 0)
				{
					$attributes['step'] = "1";
				}
				else
				{
					$attributes['step'] = "0." . str_pad(1, (int) $options['decimals'], 0, STR_PAD_LEFT);
				}
			}
			else
			{
				// decimal option not defined
				// check for step option (1, 0.1, 0.01, and so on)
				// or set default step 1 (integers only)
				$attributes['step'] = vartrue($options['step'], '1');
			}

		}

		$options = $this->format_options('text', $name, $options);

		//never allow id in format name-value for text fields
		if (THEME_LEGACY === false)
		{
			return "<input" . $this->attributes($attributes) . " " . $this->get_attributes($options, $name) . ' />';
		}

		return $this->text($name, $value, $maxlength, $options);
	}


	/**
	 * @param string $name
	 * @param string $value
	 * @param int $maxlength
	 * @param array $options
	 * @return string
	 */
	public function email($name, $value, $maxlength = 200, $options = array())
	{
		$options['type'] = 'email';
		return $this->text($name,$value,$maxlength,$options);
	}


	/**
	 * @param $id
	 * @param $default
	 * @param $width
	 * @param $height
	 * @return string
	 */
	public function iconpreview($id, $default, $width='', $height='') // FIXME
	{
		unset($width,$height); // quick fix
		// XXX - $name ?!
	//	$parms = $name."|".$width."|".$height."|".$id;
		$sc_parameters = 'mode=preview&default='.$default.'&id='.$id;
		return $this->tp->parseTemplate('{ICONPICKER=' .$sc_parameters. '}');
	}

	/**
	 * @param $name
	 * @param $default - value
	 * @param $label
	 * @param $options - gylphs=1
	 * @param $ajax
	 * @return string
	 */
	public function iconpicker($name, $default, $label='', $options = array(), $ajax = true)
	{
		//v2.2.0
		unset($label,$ajax);  // no longer used.

		$options['icon'] = 1;
		$options['glyph'] = 1;
		$options['w'] = 64;
		$options['h'] = 64;
		$options['media'] = '_icon';

		if(!isset($options['legacyPath']))
		{
		       $options['legacyPath'] = '{e_IMAGE}icons';
		}

		return $this->mediapicker($name, $default, $options);


	/*	$options['media'] = '_icon';
		$options['legacyPath'] = "{e_IMAGE}icons";
		
		return $this->imagepicker($name, $default, $label, $options);*/
		

	}


	/**
	 * Internal Function used by imagepicker, filepicker, mediapicker()
	 * @param string $category
	 * @param string $label
	 * @param string $tagid
	 * @param null   $extras
	 * @return string
	 */
	public function mediaUrl($category = '', $label = '', $tagid='', $extras=null)
	{
		if (is_string($extras))
		{
			parse_str($extras, $extras);
		}

		$category = str_replace('+', '^', $category); // Bc Fix.

		$cat = ($category) ? '&for=' . urlencode($category) : '';
		$mode = vartrue($extras['mode'], 'main');
		$action = vartrue($extras['action'], 'dialog');


		if (empty($label))
		{
			$label = ' Upload an image or file';
		}

		// TODO - option to choose which tabs to display by default.

		$url = e_ADMIN_ABS . "image.php?mode={$mode}&action={$action}" . $cat;

		if(!empty($tagid))
		{
			$url .= '&tagid=' . $tagid;
		}

		if(!empty($extras['bbcode']))
		{
			$url .= '&bbcode=' . $extras['bbcode'];
		}

		$url .= '&iframe=1';
		
		if(!empty($extras['w']))
		{
			$url .= '&w=' . $extras['w'];
		}

		if(!empty($extras['image']))
		{
			$url .= '&image=1';
		}

		if(!empty($extras['glyphs']) || !empty($extras['glyph']))
		{
			$url .= '&glyph=1';
		}

		if(!empty($extras['icons']) || !empty($extras['icon']))
		{
			$url .= '&icon=1';
		}

		if(!empty($extras['youtube']))
		{
			$url .= '&youtube=1';
		}
		
		if(!empty($extras['video']))
		{
			$url .= ($extras['video'] == 2) ? '&video=2' : '&video=1';
		}

		if(!empty($extras['audio']))
		{
			$url .= '&audio=1';
		}

		if(!empty($extras['path']) && $extras['path'] === 'plugin')
		{
			$url .= '&path=' . deftrue('e_CURRENT_PLUGIN');
		}

		if(E107_DBG_BASIC)
		{

			$title = 'Media Manager : ' . $category;
		}
		else
		{
			$title = LAN_EDIT;
		}

		$class = !empty($extras['class']) ? $extras['class'] . ' ' : '';
		$title = !empty($extras['title']) ? $extras['title'] : $title;

		$ret = "<a" . $this->attributes([
				'title'              => $title,
				'class'              => "{$class}e-modal",
				'data-modal-submit'  => 'true',
				'data-modal-caption' => LAN_EFORM_007,
				'data-cache'         => 'false',
				'data-target'        => '#uiModal',
				'href'               => $url,
			]) . ">" . $label . '</a>'; // using bootstrap.

		if (!e107::getRegistry('core/form/mediaurl'))
		{
			e107::setRegistry('core/form/mediaurl', true);
		}

		return $ret;
	}


	/**
	 * Avatar Picker
	 * @param string $name - form element name ie. value to be posted.
	 * @param string $curVal - current avatar value. ie. the image-file name or URL.
	 * @param array $options
	 * @todo add a pref for allowing external or internal avatars or both.
	 * @return string
	 */
	public function avatarpicker($name, $curVal='', $options=array())
	{
		$tp 		= $this->tp;
		$pref 		= e107::getPref();
		
		$attr 		= 'aw=' .$pref['im_width']. '&ah=' .$pref['im_height'];
		$tp->setThumbSize($pref['im_width'],$pref['im_height']);
		
		$blankImg 	= $tp->thumbUrl(e_IMAGE. 'generic/blank_avatar.jpg',$attr);
		$localonly 	= true;
		$idinput 	= $this->name2id($name);
		$previnput	= $idinput. '-preview';
		$optioni 	= $idinput. '-options';
		
		
		$path = (strpos($curVal,'-upload-') === 0) ? '{e_AVATAR}upload/' : '{e_AVATAR}default/';
		$newVal = str_replace('-upload-','',$curVal);
	
		$img = (strpos($curVal, '://')!==false) ? $curVal : $tp->thumbUrl($path.$newVal);
				
		if(!$curVal)
		{
			$img = $blankImg;	
		}
		
		$parm = $options;
		$classlocal = (!empty($parm['class'])) ? "class='".$parm['class']." e-expandit  e-tip avatar'" : " class='img-rounded rounded e-expandit e-tip avatar ";
		$class = (!empty($parm['class'])) ? "class='".$parm['class']." e-expandit '" : " class='img-rounded rounded btn btn-default btn-secondary button e-expandit ";
	
		if($localonly == true)
		{
			$text = "<input class='tbox' style='width:80%' id='{$idinput}' type='hidden' name='image' value='{$curVal}'  />";
			$text .= "<img src='".$img."' id='{$previnput}' ".$classlocal." style='cursor:pointer; width:".$pref['im_width']. 'px; height:' .$pref['im_height']."px' title='".LAN_EFORM_001."' alt='".LAN_EFORM_001."' />";
		}
		else
		{			
			$text = "<input class='tbox' style='width:80%' id='{$idinput}' type='text' name='image' size='40' value='$curVal' maxlength='100' title=\"".LAN_SIGNUP_111. '" />';
			$text .= "<img src='".$img."' id='{$previnput}' style='display:none' />";
			$text .= '<input ' .$class." type ='button' style='cursor:pointer' size='30' value=\"".LAN_EFORM_002. '"  />';
		}
						
		$avFiles = e107::getFile()->get_files(e_AVATAR_DEFAULT, '.jpg|.png|.gif|.jpeg|.JPG|.GIF|.PNG');
			
		$text .= "\n<div id='{$optioni}' style='display:none;padding:10px' >\n"; //TODO unique id. 
		$count = 0;
		if (!empty($pref['avatar_upload']) && FILE_UPLOADS && !empty($options['upload']))
		{
				$diz = LAN_USET_32.($pref['im_width'] || $pref['im_height'] ? "\n".str_replace(array('[x]-','[y]'), array($pref['im_width'], $pref['im_height']), LAN_USER_86) : '');
	
				$text .= "<div style='margin-bottom:10px'>".LAN_USET_26."
				<input  class='tbox' name='file_userfile[avatar]' type='file' size='47' title=\"{$diz}\" />
				</div>";
				
				if(count($avFiles) > 0)
				{
					$text .= "<div class='divider'><span>".LAN_EFORM_003. '</span></div>';
					$count = 1;
				}
		}
		

		foreach($avFiles as $fi)
		{
			$img_path = $tp->thumbUrl(e_AVATAR_DEFAULT.$fi['fname']);	
			$text .= "\n<a class='e-expandit' title='".LAN_EFORM_004."' href='#{$optioni}'><img src='".$img_path."' alt=''  onclick=\"insertext('".$fi['fname']."', '".$idinput."');document.getElementById('".$previnput."').src = this.src;return false\" /></a> ";
			$count++;


			//TODO javascript CSS selector
		}
		
		if($count == 0)
		{
			$text .= "<div class='row'>";
			$text .= "<div class='alert alert-info'>".LAN_EFORM_005. '</div>';

			if(ADMIN)
			{
				$EAVATAR = e_AVATAR_DEFAULT;
				$text .= "<div class='alert alert-danger'>";
				$text .= $this->tp->lanVars($this->tp->toHTML(LAN_EFORM_006, true), array('x'=>$EAVATAR));
				$text .= '</div>';
			}

			$text .= '</div>';
		}
		
		
		$text .= '
		</div>';
		
		// Used by usersettings.php right now. 
		
	
		
		
		
		
		
		return $text;
		/*
		//TODO discuss and FIXME
		    // Intentionally disable uploadable avatar and photos at this stage
			if (false && $pref['avatar_upload'] && FILE_UPLOADS)
			{
				$text .= "<br /><span class='smalltext'>".LAN_SIGNUP_25."</span> <input class='tbox' name='file_userfile[]' type='file' size='40' />
				<br /><div class='smalltext'>".LAN_SIGNUP_34."</div>";
			}
		
			if (false && $pref['photo_upload'] && FILE_UPLOADS)
			{
				$text .= "<br /><span class='smalltext'>".LAN_SIGNUP_26."</span> <input class='tbox' name='file_userfile[]' type='file' size='40' />
				<br /><div class='smalltext'>".LAN_SIGNUP_34."</div>";
			}  */
	}


	/**
	 * Image Picker
	 *
	 * @param string $name          input name
	 * @param string $default       default value
	 * @param string $previewURL
	 * @param string $sc_parameters shortcode parameters
	 *                              --- SC Parameter list ---
	 *                              - media: if present - load from media category table
	 *                              - w: preview width in pixels
	 *                              - h: preview height in pixels
	 *                              - help: tooltip
	 *                              - video: when set to true, will enable the Youtube  (video) tab.
	 * @return string html output
	 * @example $frm->imagepicker('banner_image', $_POST['banner_image'], '', 'banner'); // all images from category 'banner_image' + common images.
	 * @example $frm->imagepicker('banner_image', $_POST['banner_image'], '', 'media=banner&w=600');
	 */
	public function imagepicker($name, $default, $previewURL = '', $sc_parameters = '')
	{
		if(is_string($sc_parameters))
		{
			if(strpos($sc_parameters, '=') === false)
			{
				$sc_parameters = 'media=' . $sc_parameters;
			}
			parse_str($sc_parameters, $sc_parameters);
		}
		elseif(empty($sc_parameters))
		{
			$sc_parameters = array();
		}

	//	$cat = $tp->toDB(vartrue($sc_parameters['media']));

		// v2.2.0
		unset($previewURL );
		$sc_parameters['image'] = 1;
		$sc_parameters['dropzone'] = 1;
		if(!empty($sc_parameters['video'])) // bc fix
		{
			$sc_parameters['youtube'] = 1;
		}

		return $this->mediapicker($name, $default, $sc_parameters);


	}


/**
	 * Media Picker
 *

	 * @param string $name input name
	 * @param string $default default value
	 * @param string $parms shortcode parameters
	 *  --- $parms list ---
	 * - media: if present - load from media category table
	 * - w: preview width in pixels
	 * - h: preview height in pixels
	 * - help: tooltip
	 * - youtube=1 (Enables the Youtube tab)
     * - image=1 (Enable the Images tab)
	 * - video=1 (Enable the Video tab)
	 * - audio=1  (Enable the Audio tab)
     * - glyph=1 (Enable the Glyphs tab).
     * - path=plugin (store in media/plugins/{current-plugin])
     * - edit=false (disable media-manager popup button)
	 * - rename (string) rename file to this value after upload.  (don't forget the extension)
	 * - resize array with numberic x values. (array 'w'=>x, 'h'=>x)  - resize the uploaded image before importing during upload.
     * - convert=jpg (override pref and convert uploaded image to jpeg format. )
	 * @return string html output
	 *@example $frm->imagepicker('banner_image', $_POST['banner_image'], '', 'media=banner&w=600');
	 */
	public function mediapicker($name, $default, $parms = '')
	{
		$tp = $this->tp;
		$name_id = $this->name2id($name);
		$meta_id = $name_id. '-meta';

		if(is_string($parms))
		{
			if(strpos($parms, '=') === false)
			{
				$parms = 'media=' . $parms;
			}
			parse_str($parms, $parms);
		}
		elseif(empty($parms))
		{
			$parms = array();
		}


		if(empty($parms['media']))
		{
			$parms['media'] = '_common';
		}

		$title = !empty($parms['help']) ? "title='".$parms['help']."'" : '';

		if(!isset($parms['w']))
		{
			$parms['w'] = 206;
		}

		if(!isset($parms['h']))
		{
			$parms['h'] = 190; // 178
		}

	//	$width = vartrue($parms['w'], 220);
	//	$height = vartrue($parms['h'], 190);
	// e107::getDebug()->log($parms);

		// Test Files...
	//	$default = '{e_MEDIA_VIDEO}2018-07/samplevideo_720x480_2mb.mp4';
	//	$default = '{e_MEDIA_FILE}2016-03/Colony_Harry_Gregson_Williams.mp3';
	//	$default = '{e_PLUGIN}gallery/images/butterfly.jpg';
	//	$default = 'NuIAYHVeFYs.youtube';
	//	$default = ''; // empty
	//	$default = '{e_MEDIA_IMAGE}2018-07/Jellyfish.jpg';

		$class = '';

		if(!empty($parms['icon']))
		{
			$class = 'icon-preview mediaselector-container-icon';
			$parms['type'] = 'icon';
		}

		$preview = e107::getMedia()->previewTag($default,$parms);

		$cat = $tp->toDB(vartrue($parms['media']));

		$ret = "<div  class='mediaselector-container e-tip well well-small ".$class."' {$title} style='position:relative;vertical-align:top;margin-right:15px; display:inline-block; width:".$parms['w']. 'px;min-height:' .$parms['h']."px;'>";

		$parms['class'] = 'btn btn-sm btn-default';

		$dropzone = !empty($parms['dropzone']) ? ' dropzone' : '';
	//	$parms['modal-delete-label'] = LAN_DELETE;

		if(empty($preview))
		{
			$parms['title'] = LAN_ADD;
			$editIcon        = $this->mediaUrl($cat, $tp->toGlyph('fa-plus', array('fw'=>1)), $name_id,$parms);
			$previewIcon     = '';
		}
		else
		{
			$editIcon       = $this->mediaUrl($cat, $tp->toGlyph('fa-edit', array('fw'=>1)), $name_id,$parms);
		//	$previewIcon    = "<a title='".LAN_PREVIEW."' class='btn btn-sm btn-default btn-secondary e-modal' data-modal-caption='".LAN_PREVIEW."' href='".$previewURL."'>".$tp->toGlyph('fa-search', array('fw'=>1))."</a>";
			$previewIcon    = '';
		}

		if(isset($parms['edit']) && $parms['edit'] === false) // remove media-manager add/edit button. ie. drag-n-drop only.
		{
			$editIcon = '';
		}


		if(!empty($parms['icon'])) // empty overlay without button.
		{
			$parms['class'] = '';
			$editIcon = $this->mediaUrl($cat, '<span><!-- --></span>', $name_id,$parms);
		}

		$ret .= "<div id='{$name_id}_prev' class='mediaselector-preview" . $dropzone . "'>";

		$ret .= $preview; // image, video. audio tag etc.

		$ret .= "</div><div class='overlay'>
				    <div class='text'>" . $editIcon . $previewIcon . "</div>
				  </div>";

		$ret .= "</div>\n";
		$ret .= "<input" . $this->attributes([
				'type'  => 'hidden',
				'name'  => $name,
				'id'    => $name_id,
				'value' => $default]) . " />";
		$ret .= "<input" . $this->attributes([
				'type' => 'hidden',
				'name' => "mediameta_$name",
				'id'   => $meta_id,
			]) . " />";

		if (empty($dropzone))
		{
			return $ret;
		}

		if (!isset($parms['label']))
		{
			$parms['label'] = defset('LAN_UI_DROPZONE_DROP_FILES', 'Drop files here to upload');
		}

		$qry = 'for=' .$cat;

		if(!empty($parms['path']) && $parms['path'] === 'plugin')
		{
			$qry .= '&path=' .deftrue('e_CURRENT_PLUGIN');
		}

		if(!empty($parms['rename']))
		{
			$qry .= '&rename=' .$parms['rename'];
		}

		if(!empty($parms['convert']))
		{
			$qry .= '&convert=' .$parms['convert'];
		}

		if(isset($parms['w']))
		{
			$qry .= '&w=' .(int) $parms['w'];
		}

		if(isset($parms['h']))
		{
			$qry .= '&h=' .(int) $parms['h'];
		}

		if(!empty($parms['resize']))
		{
			$resize = array('resize'=>$parms['resize']);
			$qry .= '&' .http_build_query($resize);
		}


		// Drag-n-Drop Upload
		// @see https://www.dropzonejs.com/#server-side-implementation

		e107::js('footer', e_WEB_ABS. 'lib/dropzone/dropzone.min.js');
		e107::css('url', e_WEB_ABS. 'lib/dropzone/dropzone.min.css');
		e107::css('inline', '
			.dropzone { background: transparent; border:0 }
		');



			$INLINEJS = "
				Dropzone.autoDiscover = false;
				$(function() {
				    $('#".$name_id."_prev').dropzone({ 
				        url: '".e_JS. 'plupload/upload.php?' .$qry."',
				        createImageThumbnails: false,
				        uploadMultiple :false,
						dictDefaultMessage: \"".$parms['label']. '",
				        maxFilesize: ' .(int) ini_get('upload_max_filesize').",
				         success: function (file, response) {
				            
				            file.previewElement.classList.add('dz-success');
				    
				         //   console.log(response);
				            
				            if(response)
				            {   
				                var decoded = jQuery.parseJSON(response);
				                console.log(decoded);
				                if(decoded.preview && decoded.result)
				                {
				                    $('#".$name_id."').val(decoded.result);
				                    $('#".$name_id."_prev').html(decoded.preview);
				                }
								else if(decoded.error)
								{
									file.previewElement.classList.add('dz-error');
									$('#".$name_id."_prev').html(decoded.error.message);
								}				            
				            }
				            
				        },
				        error: function (file, response) {
				            file.previewElement.classList.add('dz-error');
				        }
				    });
				});
			
			";


		e107::js('footer-inline', $INLINEJS);

		return $ret;

	}



	/**
	 * File Picker
	 *
	 * @param string name  eg. 'myfield' or 'myfield[]'
	 * @param mixed default
	 * @param string label
	 * @param mixed sc_parameters
	 * @return string
	 */
	public function filepicker($name, $default, $label = '', $sc_parameters = null)
	{
		$tp = $this->tp;
		$name_id = $this->name2id($name);
		unset($label);
				
		if(is_string($sc_parameters))
		{
			if(strpos($sc_parameters, '=') === false)
			{
				$sc_parameters = 'media=' . $sc_parameters;
			}
			parse_str($sc_parameters, $sc_parameters);
		}

		$cat = vartrue($sc_parameters['media']) ? $tp->toDB($sc_parameters['media']) : '_common_file';

		$ret = '';

		if(isset($sc_parameters['data']) && $sc_parameters['data'] === 'array')
		{
			// Do not use $this->hidden() method - as it will break 'id' value.
			foreach (['path', 'name', 'id'] as $key)
			{
				$ret .= "<input" . $this->attributes([
						'type'  => 'hidden',
						'name'  => "{$name}[{$key}]",
						'id'    => $this->name2id("{$name}[{$key}]"),
						'value' => varset($default[$key]),
					]) . "  />";
			}

			$default = $default['path'];
		}	
		else
		{
			$ret .=	"<input type='hidden' name='{$name}' id='{$name_id}' value='{$default}' style='width:400px' />"; 	
		}
		
		
		$default_label 				= ($default) ?: LAN_CHOOSE_FILE;
		$label 						= "<span id='{$name_id}_prev' class='btn btn-default btn-secondary btn-small'>".basename($default_label). '</span>';
			
		$sc_parameters['mode'] 		= 'main';
		$sc_parameters['action'] 	= 'dialog';	
			
		
	//	$ret .= $this->mediaUrl($cat, $label,$name_id,"mode=dialog&action=list");
		$ret .= $this->mediaUrl($cat, $label,$name_id,$sc_parameters);
	
		
	
		
		return $ret;
	
		
	}




	/**
	 *	Date field with popup calendar // NEW in 0.8/2.0
	 * on Submit returns unix timestamp or string value.
	 * @param string $name the name of the field
	 * @param int|bool $datestamp UNIX timestamp - default value of the field
	 * @param array|string {
	 *      @type string mode date or datetime
	 *      @type string format strftime format eg. '%Y-%m-%d'
	 *      @type string timezone eg. 'America/Los_Angeles' - intended timezone of the date/time entered. (offsets UTC value)
	 *      }
	 * @example $frm->datepicker('my_field',time(),'mode=date');
	 * @example $frm->datepicker('my_field',time(),'mode=datetime&inline=1');
	 * @example $frm->datepicker('my_field',time(),'mode=date&format=yyyy-mm-dd');
	 * @example $frm->datepicker('my_field',time(),'mode=datetime&format=MM, dd, yyyy hh:ii');
	 * @example $frm->datepicker('my_field',time(),'mode=datetime&return=string');
	 * 
	 * @url http://trentrichardson.com/examples/timepicker/
	 * @return string
	 */
	public function datepicker($name, $datestamp = false, $options = null)
	{
		if(!empty($options) && is_string($options))
		{
			parse_str($options,$options);
		}

		$mode = !empty($options['mode']) ? trim($options['mode']) : 'date'; // OR  'datetime'

		if(!empty($options['type'])) /** BC Fix. 'type' is @deprecated */
		{
			$mode = trim($options['type']);
		}

		$dateFormat  = !empty($options['format']) ? trim($options['format']) :e107::getPref('inputdate', '%Y-%m-%d');
		$ampm		 = (preg_match('/%l|%I|%p|%P/',$dateFormat)) ? 'true' : 'false';
		$value		 = null;
		$hiddenValue = null;
		$useUnix     = (isset($options['return']) && ($options['return'] === 'string')) ? 'false' : 'true';
		$id          = !empty($options['id']) ? $options['id'] : $this->name2id($name);
		$classes     = array('date' => 'tbox e-date', 'datetime' => 'tbox e-datetime');

		if($mode === 'datetime' && !varset($options['format']))
		{
			$dateFormat .= ' ' .e107::getPref('inputtime', '%H:%M:%S');
		}

		$dformat = e107::getDate()->toMask($dateFormat);

		// If default value is set.
		if ($datestamp && $datestamp !='0000-00-00') // date-field support.
		{
			if(!is_numeric($datestamp))
			{
				$datestamp = strtotime($datestamp);
			}

			// Convert date to proper (selected) format.
			$value = e107::getDate()->convert_date($datestamp, $dformat);
			$hiddenValue = $value;

			if ($useUnix === 'true')
			{
				$hiddenValue = $datestamp;
			}
		}

		$class 		= (isset($classes[$mode])) ? $classes[$mode] : 'tbox e-date';
		$size 		= !empty($options['size']) ? (int) $options['size'] : 40;
		$required 	= !empty($options['required']) ? 'required' : '';
		$firstDay	= isset($options['firstDay']) ? $options['firstDay'] : 0;
		$xsize		= (!empty($options['size']) && !is_numeric($options['size'])) ? $options['size'] : 'xlarge';
		$disabled 	= !empty($options['disabled']) ? 'disabled' : '';
		$placeholder = !empty($options['placeholder']) ? 'placeholder="'.$options['placeholder'].'"' : '';
		$extras    = '';


		if(!empty($options['timezone'])) // since datetimepicker does not support timezones and assumes the browser timezone is the intended timezone.
		{
			date_default_timezone_set($options['timezone']);
			$targetOffset = date('Z');
			date_default_timezone_set(USERTIMEZONE);
			$extras .= " data-date-timezone-offset='".$targetOffset."'";
		}

		if(!empty($options['minuteStep']))
		{
			$extras .= " data-minute-step='".(int) $options['minuteStep']."'";
		}

		if(!empty($options['startDate']))
		{
			$extras .= " data-start-date='". $options['startDate']."'";
		}

		if(!empty($options['showMeridian']))
		{
			$extras .= " data-show-meridian='". $options['showMeridian']."'";
		}

		$text = '';

		if(!empty($options['inline']))
		{
			$text .= "<div class='{$class}' id='inline-{$id}' data-date-format='{$dformat}' data-date-ampm='{$ampm}' data-date-firstday='{$firstDay}'></div>";
			$text .= "<input type='hidden' name='{$name}' id='{$id}' value='{$value}' data-date-format='{$dformat}' data-date-ampm='{$ampm}' data-date-firstday='{$firstDay}'  />";
		}
		else
		{
			$text .= "<input class='{$class} input-".$xsize." form-control' type='text' size='{$size}' id='e-datepicker-{$id}' value='{$value}' data-date-unix ='{$useUnix}' data-date-format='{$dformat}' data-date-ampm='{$ampm}' data-date-language='".e_LAN."' data-date-firstday='{$firstDay}' {$required} {$disabled} {$placeholder} {$extras} />";
			$ftype = (!empty($options['debug'])) ? 'text' : 'hidden';
			$text .= "<input type='{$ftype}' name='{$name}' id='{$id}' value='{$hiddenValue}' />";
		}

		// TODO use Library Manager...
		e107::css('core', 'bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css', 'jquery');
		e107::js('footer', '{e_WEB}js/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js', 'jquery', 4);
		e107::js('footer', '{e_WEB}js/bootstrap-datetimepicker/js/bootstrap-datetimepicker.init.js', 'jquery', 5);

		if(e_LANGUAGE !== 'English')
		{
			e107::js('footer-inline', e107::getDate()->buildDateLocale());
		}

		return $text;
	}


	/**
	 * Render a simple user dropdown list.
	 * @param string $name - form field name
	 * @param null $val - current value
	 * @param array $options
	 *
	 *      @type string 'group' if == 'class' then users will be sorted into userclass groups.
	 *      @type string 'fields'
	 *      @type string 'classes' - single or comma-separated list of user-classes members to include.
	 *      @type string 'excludeSelf' = exlude logged in user from list.
	 *      @type string 'return' if == 'array' an array is returned.
	 *      @type string 'return' if == 'sqlWhere' an sql query is returned.
	 * @return string|array select form element.
	 */
	public function userlist($name, $val=null, $options=array())
	{

		$fields = (!empty($options['fields']))  ? $options['fields'] : 'user_id,user_name,user_class';
		$class =  (!empty($options['classes']))   ? $options['classes'] : e_UC_MEMBER ; // all users sharing the same class as the logged-in user.

		$class = str_replace(' ', '',$class);

		switch ($class)
		{
			case e_UC_ADMIN:
				$where = 'user_admin = 1';
				$classList = e_UC_ADMIN;
				break;

			case e_UC_MEMBER:
				$where = 'user_ban = 0';
				$classList = e_UC_MEMBER;
				break;

			case e_UC_NOBODY:
				return '';
				break;

			case 'matchclass':
				$where = "user_class REGEXP '(^|,)(".str_replace(',', '|', USERCLASS).")(,|$)'";
				$classList = USERCLASS;
				$clist = explode(',',USERCLASS);
				if(!isset($options['group']) && count($clist) > 1) // group classes by default if more than one found.
				{
					$options['group'] = 'class';
				}
			break;

			default:
				$where = "user_class REGEXP '(^|,)(".str_replace(',', '|', $class).")(,|$)'";
				$classList = $class;
				break;
		}


		if(!empty($options['return']) && $options['return'] === 'sqlWhere') // can be used by user.php ajax method..
		{
			return $where;
		}

		$users =   e107::getDb()->retrieve('user',$fields, 'WHERE ' .$where. ' ORDER BY user_name LIMIT 1000',true);

		if(empty($users))
		{
			return LAN_UNAVAILABLE;
		}

		$opt = array();

		if(!empty($options['group']) && $options['group'] === 'class')
		{
			$classes = explode(',',$classList);

			foreach($classes as $cls)
			{
				$cname = e107::getUserClass()->getName($cls);

				$cname = str_replace('_',' ', trim($cname));
				foreach($users as $u)
				{
					$uclass = explode(',',$u['user_class']);

					if(($classList == e_UC_ADMIN) || ($classList == e_UC_MEMBER) || in_array($cls,$uclass))
					{
						$id = $u['user_id'];

						if(!empty($options['excludeSelf']) && ($id == USERID))
						{
							continue;
						}

						$opt[$cname][$id] = $u['user_name'];
					}
				}


			}

		}
		else
		{
			foreach($users as $u)
			{
				$id = $u['user_id'];
				$opt[$id] = $u['user_name'];
			}

		}


		ksort($opt);


		if(!empty($options['return']) && $options['return'] === 'array') // can be used by user.php ajax method..
		{
			return $opt;
		}

		return $this->select($name,$opt,$val,$options, varset($options['default'],null));

	}



	/**
	 * User auto-complete search
	 * XXX EXPERIMENTAL - subject to change.
	 * @param string $name_fld field name for user name
	 * @param string $id_fld field name for user id
	 * @param string $default_name default user name value
	 * @param integer $default_id default user id
	 * @param array|string $options [optional] 'readonly' (make field read only), 'name' (db field name, default user_name)
	 * @return string HTML text for display
	 */
	 /*
	function userpicker($name_fld, $id_fld='', $default_name, $default_id, $options = array())
	{
		if(!is_array($options))
		{
			parse_str($options, $options);
		}

		$default_name = vartrue($default_name, '');
		$default_id = vartrue($default_id, '');

		$default_options = array();
		if (!empty($default_name))
		{
			$default_options = array(
				array(
					'value' => $default_id,
					'label' => $default_name,
				),
			);
		}

		$defaults['selectize'] = array(
			'loadPath' => e_BASE . 'user.php',
			'create'   => false,
			'maxItems' => 1,
			'mode'     => 'multi',
			'options'  => $default_options,
		);

		//TODO FIXME Filter by userclass.  - see $frm->userlist().

		$options = array_replace_recursive($defaults, $options);

		$ret = $this->text($name_fld, $default_id, 20, $options);

		return $ret;
	}
	*/


	/**
	 * User Field - auto-complete search
	 * @param string $name form element name
	 * @param string|array $value comma separated list of user ids or array of userid=>username pairs.
	 * @param array|string $options [optional]
	 *      @type int 'limit' Maximum number of users
	 *      @type string 'id' Custom id
	 *      @type string 'inline' Inline ID.
	 *
	 * @example $frm->userpicker('author', 1);
	 * @example $frm->userpicker('authors', "1,2,3");
	 * @example $frm->userpicker('author', array('user_id'=>1, 'user_name'=>'Admin');
	 * @example $frm->userpicker('authors', array(0=>array('user_id'=>1, 'user_name'=>'Admin', 1=>array('user_id'=>2, 'user_name'=>'John'));
	 *
	 * @todo    $options['type'] = 'select' - dropdown selections box with data returned as array instead of comma-separated.
	 * @return string HTML text for display
	 */
	public function userpicker($name, $value, $options = array())
	{
		if(!is_array($options))
		{
			parse_str($options, $options);
		}

		$defaultItems = array();

		if(is_array($value))
		{
			if(isset($value[0]))// multiple users.
			{
				foreach($value as $val)
				{
					$defaultItems[] = array('value'=>$val['user_id'], 'label'=>$val['user_name']);
				}

			}
			else // single user
			{
				$defaultItems[] = array('value'=>$value['user_id'], 'label'=>$value['user_name']);
			}

		}
		elseif(!empty($value)) /// comma separated with user-id lookup.
		{
			$tmp = explode(',', $value);
			foreach($tmp as $uid)
			{
				if($user = e107::user($uid))
				{
					$defaultItems[] = array('value'=>$user['user_id'], 'label'=>$user['user_name']);
				}
			}
		}

		// defaults.
		$parms = array(
			'selectize' => array(
				'loadPath' => e_HTTP.'user.php',
				'create'   => false,
				'maxItems' => 1,
				'mode'     => 'multi',
				'options'  => $defaultItems
			)
		);

		if(!empty($options['loadPath']))
		{
			$parms['selectize']['loadPath'] = $options['loadPath'];
			unset($options['loadPath']);
		}

		if(!empty($options['plugins'])) // eg. array('remove_button')
		{
			$parms['selectize']['plugins'] = $options['plugins']; // 'plugins'  => array('remove_button')
			unset($options['plugins']);
		}


		if(!empty($options['limit']))
		{
			$parms['selectize']['maxItems'] = (int) $options['limit'];
		}

		if(!empty($options['id']))
		{
			$parms['id'] = $options['id'];
		}

		if(!empty($options['inline']))
		{
			$parms['selectize']['e_editable'] = $options['inline'];
		}

		//TODO FIXME Filter by userclass.  - see $frm->userlist().

		$defValues = array();

		foreach($defaultItems as $val)
		{
			$defValues[] = $val['value'];
		}

		$parms = array_merge($parms, $options);

		return $this->text($name, implode(',',$defValues), 100, $parms);

	}


	/**
	 * A Rating element
	 *
	 * @param string $table
	 * @param int $id
	 * @param array $options
	 * @return string
	 */
	public function rate($table, $id, $options=array())
	{		
		$table 	= preg_replace('/\W/', '', $table);
		$id 	= (int) $id;
		
		return e107::getRate()->render($table, $id, $options);	
	}

	/**
	 * @param $table
	 * @param $id
	 * @param $options
	 * @return string
	 */
	public function like($table, $id, $options=null)
	{
		$table 	= preg_replace('/\W/', '', $table);
		$id 	= (int) $id;
		
		return e107::getRate()->renderLike($table,$id,$options); 	
	}


	/**
	 * File Upload form element.
	 * @param $name
	 * @param array $options (optional)  array('multiple'=>1)
	 * @return string
	 */
	public function file($name, $options = array())
	{
		if (deftrue('e_ADMIN_AREA') && empty($options['class']))
		{
			$options = array('class' => 'tbox well file');
		}

		$options = $this->format_options('file', $name, $options);


		//never allow id in format name-value for text fields
		return "<input" . $this->attributes([
				'type' => 'file',
				'name' => $name,
			]) . $this->get_attributes($options, $name) . ' />';
	}

	/**
	 * Upload Element. (for the future)
	 *
	 * @param       $name
	 * @param array $options
	 * @return string
	 */
	public function upload($name, $options = array())
	{
		unset($name,$options);
		return 'Ready to use upload form fields, optional - file list view';
	}

	/**
	 * @param $name
	 * @param string $value
	 * @param int $maxlength
	 * @param array|string $options
	 * @return string
	 */
	public function password($name, $value = '', $maxlength = 50, $options = null)
	{
		if(is_string($options))
		{
			parse_str($options, $options);
		}
		
		$addon = '';
		$gen = '';
		
		if(!empty($options['generate']))
		{
			$gen = '&nbsp;<a href="#" class="btn btn-default btn-secondary btn-small e-tip" id="Spn_PasswordGenerator" title=" '.LAN_GEN_PW.' " >'.LAN_GENERATE.'</a> ';
			
			if(empty($options['nomask']))
			{
				$gen .= '<a class="btn btn-default btn-secondary btn-small e-tip" href="#" id="showPwd" title=" '.LAN_DISPL_PW.' ">'.LAN_SHOW.'</a><br />';
			}
		}
		
		if(!empty($options['strength']))
		{
			$addon .= "<div style='margin-top:4px'><div  class='progress' style='float:left;display:inline-block;width:218px;margin-bottom:0'><div class='progress-bar bar' id='pwdMeter' style='width:0%' ></div></div> <div id='pwdStatus' class='smalltext' style='float:left;display:inline-block;width:150px;margin-left:5px'></span></div>";
		}
		
		$options['pattern'] = vartrue($options['pattern'],'[\S].{2,}[\S]');
		$options['required'] = varset($options['required'], 1);
		$options['class'] = vartrue($options['class'],'e-password tbox');


		e107::js('core', 	'password/jquery.pwdMeter.js', 'jquery', 2);

		e107::js('footer-inline', '
			$(".e-password").pwdMeter({
	            minLength: 6,
	            displayGeneratePassword: true,
	            generatePassText: "Generate",
	            randomPassLength: 12
	        });
	    ');

		if(deftrue('BOOTSTRAP'))
		{
			$options['class'] .= ' form-control';
		}
		
		if(!empty($options['size']) && !is_numeric($options['size']))
		{
			$options['class'] .= ' input-' . $options['size'];
			unset($options['size']); // don't include in html 'size='. 	
		}

		$type = empty($options['nomask']) ? 'password' : 'text';

		$options = $this->format_options('text', $name, $options);


		//never allow id in format name-value for text fields
		$text = "<input" . $this->attributes([
				'type'      => $type,
				'name'      => $name,
				'value'     => $value,
				'maxlength' => $maxlength,
			]) . $this->get_attributes($options, $name) . ' />';

		if (empty($gen) && empty($addon))
		{
			return $text;
		}

		return "<span class='form-inline'>" . $text . $gen . '</span>' . vartrue($addon);

	}


	/**
	 * Render Pagination using 'nextprev' shortcode.
	 * @param string $url eg. e_REQUEST_SELF.'?from=[FROM]'
	 * @param int $total total records
	 * @param int $from value to replace [FROM] with in the URL
	 * @param int $perPage number of items per page
	 * @param array $options template, type, glyphs
	 * @return string
	 */
	public function pagination($url='', $total=0, $from=0, $perPage=10, $options=array())
	{

		if(empty($total) || empty($perPage))
		{
			return '';
		}
		
		if(defined('BOOTSTRAP') && BOOTSTRAP === 4)
		{
			return '<a' . $this->attributes([
					'class' => 'pager-button btn btn-primary',
					'href'  => $url,
				]) . '>' . $total . '</a>';
		}

		if(!is_numeric($total))
		{
			return '<ul class="pager"><li><a' . $this->attributes(['href' => $url]) . '>' . $total . '</a></li></ul>';
		}



		require_once(e_CORE. 'shortcodes/single/nextprev.php');

		$nextprev = array(
			'tmpl_prefix'	=> varset($options['template'],'default'),
			'total'			=> (int) $total,
			'amount'		=> (int) $perPage,
			'current'		=> (int) $from,
			'url'			=> urldecode($url),
			'type'          => varset($options['type'],'record'), // page|record
			'glyphs'        => vartrue($options['glyphs'],false) // 1|0
		);

	//	e107::getDebug()->log($nextprev);

		return nextprev_shortcode($nextprev);
	}


	/**
	 * Render a bootStrap ProgressBar.
	 *
	 * @param string        $name
	 * @param number|string $value
	 * @param array         $options
	 * @return string
	 * @example  Use
	 */
	public function progressBar($name,$value,$options=array())
	{
		if(!deftrue('BOOTSTRAP')) // Legacy ProgressBar.
		{
			$barl = (file_exists(THEME.'images/barl.png') ? THEME_ABS.'images/barl.png' : e_PLUGIN_ABS.'poll/images/barl.png');
			$barr = (file_exists(THEME.'images/barr.png') ? THEME_ABS.'images/barr.png' : e_PLUGIN_ABS.'poll/images/barr.png');
			$bar = (file_exists(THEME.'images/bar.png') ? THEME_ABS.'images/bar.png' : e_PLUGIN_ABS.'poll/images/bar.png');

			return "<div style='background-image: url($barl); width: 5px; height: 14px; float: left;'></div>
			<div style='background-image: url($bar); width: ". (int) $value ."%; height: 14px; float: left;'></div>
			<div style='background-image: url($barr); width: 5px; height: 14px; float: left;'></div>";
		}
			
		$class = vartrue($options['class']);
		$target = $this->name2id($name);
		
		$striped = (vartrue($options['btn-label'])) ? ' progress-striped active' : '';	

		if(strpos($value,'/')!==false)
		{
			$label = $value;
			list($score,$denom) = explode('/',$value);

		//	$multiplier = 100 / (int) $denom;

			$value = !empty($denom) ? ((int) $score / (int) $denom) * 100 : 0;

		//	$value = (int) $score * (int) $multiplier;
			$percVal = round((float) $value).'%';
		}
		else
		{
			$percVal = round((float) $value).'%';
			$label = $percVal;
		}

		if(!empty($options['label']))
		{
			$label = $options['label'];
		}

		$id = !empty($options['id']) ? "id='".$options['id']."'" : '';

		$text =	"<div {$id} class='progress {$striped}' >
   		 	<div id='".$target."' class='progress-bar bar ".$class."' role='progressbar' aria-valuenow='". (int) $value ."' aria-valuemin='0' aria-valuemax='100' style='min-width: 2em;width: ".$percVal."'>";
   		$text .= $label;
   		 	$text .= '</div>
    	</div>';
		
		$loading = vartrue($options['loading'], defset('LAN_LOADING', 'Loading'));
		
		$buttonId = $target.'-start';
		
		
		
		if(!empty($options['btn-label']))
		{
			$interval = vartrue($options['interval'],1000);
			$text .= '<a id="'.$buttonId.'" data-loading-text="'.$loading.'" data-progress-interval="'.$interval.'" data-progress-target="'.$target.'" data-progress="' . $options['url'] . '" data-progress-mode="'.varset($options['mode'],0).'" data-progress-show="'.varset($options['show'],0).'" data-progress-hide="'.$buttonId.'" class="btn btn-primary e-progress" >'.$options['btn-label'].'</a>';
			$text .= ' <a data-progress-target="'.$target.'" class="btn btn-danger e-progress-cancel" >'.LAN_CANCEL.'</a>';
		}
		
		
		return $text;
		
	}


	/**
	 * Textarea Element
	 * @param string $name
	 * @param string $value
	 * @param int $rows
	 * @param int $cols
	 * @param array|string $options
	 * @param int|bool $counter
	 * @return string
	 */
	public function textarea($name, $value, $rows = 10, $cols = 80, $options = null, $counter = false)
	{
		if(is_string($options))
		{
			 parse_str($options, $options);
		}
		else
		{
			$options = (array) $options;
		}

		// auto-height support

		if(empty($options['class']))
		{
			$options['class'] = '';
		}

		if(!empty($options['size']) && !is_numeric($options['size']))
		{
			$options['class'] .= ' form-control input-' .$options['size'];
			unset($options['size']); // don't include in html 'size='. 	
		}
		elseif (empty($options['noresize']))
		{
			$options['class'] = (isset($options['class']) && $options['class']) ? $options['class'] . ' e-autoheight' : 'tbox col-md-7 span7 e-autoheight form-control';
		}

		$options = $this->format_options('textarea', $name, $options);

//		print_a($options);
		//never allow id in format name-value for text fields
		return "<textarea" . $this->attributes([
				'name' => $name,
				'rows' => $rows,
				'cols' => $cols,
			]) . $this->get_attributes($options, $name) . ">{$value}</textarea>" .
			($counter !== false ? $this->hidden('__' . $name . 'autoheight_opt', $counter) : '');
	}

	/**
	 * Bbcode Area. Name, value, template, media-Cat, size, options array eg. counter
	 * IMPORTANT: $$mediaCat is also used is the media-manager category identifier
	 *
	 * @param string $name
	 * @param mixed  $value
	 * @param string $template
	 * @param string $mediaCat _common
	 * @param string $size     : small | medium | large
	 * @param array  $options  {
	 *  @type bool wysiwyg  when set to false will disable wysiwyg if active.
	 *  @type string class override class.
	 *                         }

	 * @return string
	 */
	public function bbarea($name, $value='', $template = '', $mediaCat='_common', $size = 'large', $options = array())
	{
		if(is_string($options))
		{
			parse_str($options, $options);
		}
		//size - large|medium|small
		//width should be explicit set by current admin theme
	//	$size = 'input-large';
		$height = '';
		$cols = 70;
		
		switch($size)
		{
			case 'tiny':
				$rows = '3';
				$cols = 50;
			//	$height = "style='height:250px'"; // inline required for wysiwyg
			break;
			
			
			case 'small':
				$rows = '7';
				$height = "style='height:230px'"; // inline required for wysiwyg
				$size = 'input-block-level';
			break;
						
			case 'medium':
				$rows = '10';
               
				$height = "style='height:375px'"; // inline required for wysiwyg
				$size = 'input-block-level';
			break;

			case 'large':
			default:
				$rows = '20';
				$size = 'large input-block-level';
			//	$height = "style='height:500px;width:1025px'"; // inline required for wysiwyg
			break;
		}

		if(!empty($options['rows']))
		{
			$rows = $options['rows'];
		}

		if(!empty($options['style']))
		{
			$height = "style='".$options['style']."'";
		}

		// auto-height support
/*
		$bbbar 				= '';
		$wysiwyg = null;
		$wysiwygClass = ' e-wysiwyg';

		if(isset($options['wysiwyg']))
		{
			$wysiwyg = $options['wysiwyg'];
		}

		if($wysiwyg === false)
		{
			$wysiwygClass = '';
		}

		$options['class'] 	= 'tbox bbarea '.($size ? ' '.$size : '').$wysiwygClass.' e-autoheight form-control';
*/
		$options['class'] 	= 'tbox bbarea '.($size ? ' '.$size : '').' e-wysiwyg e-autoheight form-control';

		if (isset($options['id']) && !empty($options['id']))
		{
			$help_tagid 		= $this->name2id($options['id']). '--preview';
		}
		else
		{
			$help_tagid 		= $this->name2id($name). '--preview';
		}

		if (!isset($options['wysiwyg']))
		{
			$options['wysiwyg'] = true;
		}

		//if(e107::wysiwyg(true) === false || $wysiwyg === false) // bbarea loaded, so activate wysiwyg (if enabled in preferences)
		if(e107::wysiwyg($options['wysiwyg'],true) === 'bbcode') // bbarea loaded, so activate wysiwyg (if enabled in preferences)
		{
			$options['other'] 	= "onselect='storeCaret(this);' onclick='storeCaret(this);' onkeyup='storeCaret(this);' {$height}";
		}
		else
		{
			$options['other'] 	= ' ' .$height;
		}


		$counter 			= vartrue($options['counter'],false); 
		
		$ret = "<div class='bbarea {$size}'>
		<div class='field-spacer'><!-- --></div>\n";


		if(e107::wysiwyg() === true) // && $wysiwyg !== false)
		{
			$eParseList = e107::getConfig()->get('e_parse_list');

			if(!empty($eParseList))
			{
				$opts = array(
					'field' => $name,
				);

				foreach($eParseList as $plugin)
				{
					$hookObj = e107::getAddon($plugin, 'e_parse');

					if($tmp = e107::callMethod($hookObj, 'toWYSIWYG', $value, $opts))
					{
						$value = $tmp;
					}
				}
			}
		
            if (!check_class(e107::getConfig()->get('post_html', e_UC_MAINADMIN))) 
            {
                $ret .=	e107::getBB()->renderButtons($template,$help_tagid);
            }
        
        }
        else 
        {
            $ret .=	e107::getBB()->renderButtons($template,$help_tagid);
        }
        
		$ret .=	$this->textarea($name, $value, $rows, $cols, $options, $counter); // higher thank 70 will break some layouts.
			
		$ret .= "</div>\n";
		
		$_SESSION['media_category'] = $mediaCat; // used by TinyMce. 


	
		
		return $ret;
		
		// Quick fix - hide TinyMCE links if not installed, dups are handled by JS handler
		/*
		
				e107::getJs()->footerInline("
						if(typeof tinyMCE === 'undefined')
						{
							\$$('a.e-wysiwyg-switch').invoke('hide');
						}
				");
		*/
		
		
	}

	/**
	 * Checks for a theme snippet and returns it
	 * @param string $type
	 * @return string|false
	 */
	private function getSnippet($type)
	{
		if($this->_snippets === false || deftrue('e_ADMIN_AREA'))
		{
			return false;
		}

		$regId = 'core/form/snippet/'.$type;

		if($snippet = e107::getRegistry($regId))
		{
			return $snippet;
		}

		$snippetPath = THEME. 'snippets/form_' .$type. '.html';

		if(!file_exists($snippetPath))
		{
			return false;
		}

		$content =  file_get_contents($snippetPath, false, null, 0, 1024);

		e107::setRegistry($regId, $content);

		return $content;

	}

	/**
	 * @param string $snippet
	 * @param array $options
	 * @param string $name
	 * @param int|string $value
	 * @return string
	 */
	private function renderSnippet($snippet, $options, $name, $value)
	{
		$snip  = (array) $options;

		if(!empty($options['class']))
		{
			$snip['class'] = trim($options['class']);
			unset($options['class']);
		}

		if(!empty($options['label']))
		{
			$snip['label'] = trim($options['label']);
			unset($options['label']);
		}

		$snip['id'] = $this->_format_id(varset($options['id']), $name, $value, null);
		unset($options['id']);

		$snip['attributes'] = "name='".$name."' value='".$value."' ".$this->get_attributes($options, $name, $value);

		foreach($snip as $k=>$v)
		{
			$search[] = '{'.$k.'}';
		}

		return str_replace($search, array_values($snip), $snippet);

	}

	/**
	* Render a checkbox 
	* @param string $name
	* @param mixed $value
	* @param boolean $checked
	* @param mixed $options query-string or array or string for a label. eg. label=Hello&foo=bar or array('label'=>Hello') or 'Hello'
	* @return string
	*/
	public function checkbox($name, $value, $checked = false, $options = array())
	{
		if(!is_array($options))
		{
			if(strpos($options, '=')!==false)
			{
			 	parse_str($options, $options);
			}
			elseif(is_string($options))
			{
				$options = array('label'=>$options);
			}

		}

		$labelClass = (!empty($options['inline'])) ? 'checkbox-inline' : 'checkbox form-check';
		$labelTitle = '';

		$options = $this->format_options('checkbox', $name, $options);
		
		$options['checked'] = $checked; //comes as separate argument just for convenience
		
		$text = '';


		$active = ($checked === true) ? ' active' : ''; // allow for styling if needed.

		if(!empty($options['label'])) // add attributes to <label>
		{
			if(!empty($options['title']))
			{
				$labelTitle = ' title="' .$options['title']. '"';
				unset($options['title']);
			}

			if(!empty($options['class']))
			{
				$labelClass .= ' ' .$options['class'];
				unset($options['class']);
			}
		}

		if(!isset($options['class']))
        {
	        $options['class'] = '';
        }

		$options['class'] .= ' form-check-input';

		if ($snippet = $this->getSnippet('checkbox'))
		{
			return $this->renderSnippet($snippet, $options, $name, $value);
		}

		$pre = (!empty($options['label'])) ? "<label class='" . $labelClass . $active . "'{$labelTitle}>" : ''; // Bootstrap compatible markup
		$post = (!empty($options['label'])) ? '<span>' . $options['label'] . '</span></label>' : '';
		unset($options['label']); // not to be used as attribute;


		return $pre . "<input" . $this->attributes([
				'type'  => 'checkbox',
				'name'  => $name,
				'value' => $value,
			]) . $this->get_attributes($options, $name, $value) . ' />' . $post;


	}


	/**
	 * Render an array of checkboxes. 
	 * @param string $name
	 * @param array $option_array
	 * @param mixed $checked
	 * @param array $options [optional useKeyValues]
	 */
	public function checkboxes($name, $option_array=array(), $checked=null, $options=array())
	{
		$name = (strpos($name, '[') === false) ? $name.'[]' : $name;

		if(!is_array($checked))
		{
			$checked = explode(',', $checked);
		}
		
		$text = array();

		$cname = $name;

		foreach($option_array as $k=>$label)
		{
			if(!empty($options['useKeyValues'])) // ie. auto-generated
			{
				$key = $k;
				$c = in_array($k, $checked) ? true : false;
			}
			elseif(!empty($options['useLabelValues']))
			{
				$key = $label;
				//print_a($label);
				$c = in_array($label, $this->tp->toDB($checked));
			}
			else
			{
				$key = 1;
				$cname = str_replace('[]','['.$k.']',$name);
				$c = vartrue($checked[$k]);
			}

			/**
			 * Label overwrote the other supplied options (if any)
			 * and also failed in case it contained a "=" character
			 */
			$options['label'] = $label;
			$text[] = $this->checkbox($cname, $key, $c, $options);
		}

		$id = empty($options['id']) ? $this->name2id($name).'-container' : $options['id'];

	//	return print_a($checked,true);
		if(isset($options['list']) && $options['list'])
		{
			return "<ul id='".$id."' class='checkboxes checkbox'><li>".implode('</li><li>',$text). '</li></ul>';
		}


		if(!empty($text))
		{
			return "<div id='".$id."' class='checkboxes checkbox' style='display:inline-block'>".implode('',$text). '</div>';
		}

		return $text;
		
	}


	/**
	 * @param string $label_title
	 * @param string $name
	 * @param $value
	 * @param mixed $checked
	 * @param array $options
	 * @return string
	 */
	public function checkbox_label($label_title, $name, $value, $checked = false, $options = array())
	{
		return $this->checkbox($name, $value, $checked, $options).$this->label($label_title, $name, $value);
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $checked
	 * @param $label
	 * @return string
	 */
	public function checkbox_switch($name, $value, $checked = false, $label = '')
	{
		return $this->checkbox($name, $value, $checked).$this->label($label ?: LAN_ENABLED, $name, $value);
	}

	/**
	 * @param $name
	 * @param $selector
	 * @param $id
	 * @param $label
	 * @return string
	 */
	public function checkbox_toggle($name, $selector = 'multitoggle', $id = false, $label='') //TODO Fixme - labels will break this. Don't use checkbox, use html.
	{
		$selector = 'jstarget:'.$selector;
		if($id)
		{
			$id = $this->name2id($id);
		}

		return $this->checkbox($name, $selector, false, array('id' => $id,'class' => 'checkbox checkbox-inline toggle-all','label'=>$label));
	}

	/**
	 * @param $name
	 * @param $current_value
	 * @param $uc_options
	 * @param $field_options
	 * @return string
	 */
	public function uc_checkbox($name, $current_value, $uc_options, $field_options = array())
	{
		if(!is_array($field_options))
		{
			parse_str($field_options, $field_options);
		}
		return '
			<div class="check-block">
				'.$this->_uc->vetted_tree($name, array($this, '_uc_checkbox_cb'), $current_value, $uc_options, $field_options).'
			</div>
		';
	}


	/**
	 *	Callback function used with $this->uc_checkbox
	 *
	 *	@see user_class->select() for parameters
	 */
	public function _uc_checkbox_cb($treename, $classnum, $current_value, $nest_level, $field_options)
	{
		if($classnum == e_UC_BLANK)
		{
			return '';
		}

		if (!is_array($current_value))
		{
			$tmp = explode(',', $current_value);
		}

		$classIndex = abs($classnum);			// Handle negative class values
		$classSign = (strpos($classnum, '-') === 0) ? '-' : '';

		$style = '';
		$class = $style;
		if($nest_level == 0)
		{
			$class = ' strong';
		}
		else
		{
			$style = " style='text-indent:" . (1.2 * $nest_level) . "em'";
		}
		$descr = varset($field_options['description']) ? ' <span class="smalltext">('.$this->_uc->getDescription($classnum).')</span>' : '';

		return "<div class='field-spacer{$class}'{$style}>".$this->checkbox($treename.'[]', $classnum, in_array($classnum, $tmp), $field_options).$this->label($this->_uc->getName($classIndex).$descr, $treename.'[]', $classnum)."</div>\n";
	}


	/**
	 * @param string $classnum Class Number
	 * @return string
	 */
	/**
	 * @param $classnum
	 * @return string
	 */
	public function uc_label($classnum)
	{
		return $this->_uc->getName($classnum);
	}

	/**
	 * A Radio Button Form Element
	 * @param $name
	 * @param $value
	 * @param $checked boolean
	 * @param null $options
	 * @return string
	 */
	public function radio($name, $value, $checked = false, $options = null)
	{

		if(!is_array($options))
		{
			parse_str((string) $options, $options);
		}
		
		if(is_array($value))
		{
			return $this->radio_multi($name, $value, $checked, $options);
		}
		
		$labelFound = vartrue($options['label']);
		unset($options['label']); // label attribute not valid in html5

		$options = $this->format_options('radio', $name, $options);
		$options['checked'] = $checked; //comes as separate argument just for convenience

		if(empty($options['id']))
		{
			unset($options['id']);
		}

		if ($snippet = $this->getSnippet('radio'))
		{
			$options['label'] = $labelFound;

			return $this->renderSnippet($snippet, $options, $name, $value);
		}

		// $options['class'] = 'inline';	
		$text = '';

		//	return print_a($options,true);
		if ($labelFound) // Bootstrap compatible markup
		{
			$defaultClass = (deftrue('BOOTSTRAP')) ? 'radio-inline form-check-inline' : 'radio inline';
			$dis = (!empty($options['disabled'])) ? ' disabled' : '';
			$text .= "<label class='{$defaultClass}{$dis}'>";

		}


		$text .= "<input" . $this->attributes([
				'class' => 'form-check-input',
				'type'  => 'radio',
				'name'  => $name,
				'value' => $value,
			]) . $this->get_attributes($options, $name, $value) . ' />';

		if (!empty($options['help']))
		{
			$text .= "<div class='field-help'>" . $options['help'] . '</div>';
		}

		if ($labelFound)
		{
			$text .= ' <span>' . $labelFound . '</span></label>';
		}

		return $text;
	}

	/**
	 * Boolean Radio Buttons / Checkbox (with Bootstrap Switch).
	 *
	 * @param string $name
	 *  Form element name.
	 * @param bool $checked_enabled
	 *  Use the checked attribute or not.
	 * @param string $label_enabled
	 *  Default is LAN_ENABLED
	 * @param string $label_disabled
	 *  Default is LAN_DISABLED
	 * @param array|string $options
	 *  - 'inverse' => 1 (invert values)
	 *  - 'reverse' => 1 (switch display order)
	 *  - 'switch'  => 'normal' (size for Bootstrap Switch... mini, small, normal, large)
	 *
	 * @return string $text
	 */
	public function radio_switch($name, $checked_enabled = false, $label_enabled = '', $label_disabled = '', $options = null)
	{
		if(is_string($options))
		{
			parse_str($options, $options);
		}

		$options_on = varset($options['enabled'], array());
		$options_off = varset($options['disabled'], array());

		unset($options['enabled'], $options['disabled']);

		$options_on = array_merge($options_on, $options);
		$options_off = array_merge($options_off, $options);


		if(!empty($options['expandit']) || vartrue($options['class']) === 'e-expandit' ) // See admin->prefs 'Single Login' for an example.
		{
			$options_on = array_merge($options, array('class' => 'e-expandit-on'));
			$options_off = array_merge($options, array('class' => 'e-expandit-off'));
		}

		if(deftrue('e_ADMIN_AREA'))
		{
			$options['switch'] = 'small';
			$label_enabled = ($label_enabled) ? strtoupper($label_enabled) : strtoupper(LAN_ON);
			$label_disabled = ($label_disabled) ?  strtoupper($label_disabled): strtoupper(LAN_OFF);
		}


		$options_on['label'] = $label_enabled ? defset($label_enabled, $label_enabled) : LAN_ENABLED;
		$options_off['label'] = $label_disabled ? defset($label_disabled, $label_disabled) : LAN_DISABLED;

		if (!empty($options['switch']))
		{
			return $this->flipswitch($name,$checked_enabled, array('on'=>$options_on['label'],'off'=>$options_off['label']),$options);
		}

		if(!empty($options['inverse']))
		{
			$text = $this->radio($name, 0, !$checked_enabled, $options_on) . ' 	' . $this->radio($name, 1, $checked_enabled, $options_off);

		}
		elseif(!empty($options['reverse'])) // reverse display order.
		{
			$text = $this->radio($name, 0, !$checked_enabled, $options_off) . ' ' . $this->radio($name, 1, $checked_enabled, $options_on);
		}
		else
		{
			$text = $this->radio($name, 1, $checked_enabled, $options_on) . ' 	' . $this->radio($name, 0, !$checked_enabled, $options_off);
		}

		return $text;
	}


	/**
	 * @param string $name
	 * @param bool|false $checked_enabled
	 * @param array $labels on & off
	 * @param array $options
	 * @return string
	 */
	public function flipswitch($name, $checked_enabled = false, $labels=null, $options = array())
	{

		if(empty($labels))
		{
			$labels = array('on' =>strtoupper(LAN_ON), 'off' =>strtoupper(LAN_OFF));
		}

		$value = $checked_enabled;

		if(!empty($options['inverse']))
		{
			$checked_enabled = !$checked_enabled;
		}

		if(!empty($options['reverse']))
		{
			$on = $labels['on'];
			$options_on['label'] = $labels['off'];
			$options_off['label'] = $on;
			unset($on);

		}

		if(empty($options['switch']))
		{
			$options['switch'] = 'small';
		}


		$switchName = $this->name2id($name) . '__switch'; // fixes array names.

		$switchAttributes = array(
			'data-type'    => 'switch',
			'data-name'    => $name,
			'data-size'    => $options['switch'],
			'data-on'      => $labels['on'],
			'data-off'     => $labels['off'],
			'data-inverse' => (int) !empty($options['inverse']),
		);

		$options += $switchAttributes;

		if(deftrue('e_ADMIN_AREA'))
		{
			$options['data-wrapper'] = 'wrapper form-control';

		}

		e107::library('load', 'bootstrap.switch');
		e107::js('footer', '{e_WEB}js/bootstrap.switch.init.js', 'jquery', 5);

		$text = $this->hidden($name, (int) $value);
		$text .= $this->checkbox($switchName, (int) $checked_enabled, $checked_enabled, $options);

		return $text;
	}


	/**
	 * XXX INTERNAL ONLY - Use radio() instead. array will automatically trigger this internal method.
	 * @param string $name
	 * @param array|string $elements = arrays value => label
	 * @param $checked
	 * @param array $options
	 * @param mixed $help array of field help items or string of field-help (to show on all)
	 * @return string
	 */
	private function radio_multi($name, $elements, $checked, $options=array(), $help = null)
	{
		
		
		
		/* // Bootstrap Test. 
		 return'    <label class="checkbox">
    <input type="checkbox" value="">
    Option one is this and that—be sure to include why its great
    </label>
     
    <label class="radio">
    <input type="radio" name="optionsRadios" id="optionsRadios1" value="option1" checked>
    Option one is this and that—be sure to include why its great
    </label>
    <label class="radio">
    <input type="radio" name="optionsRadios" id="optionsRadios2" value="option2">
    Option two can be something else and selecting it will deselect option one
    </label>';
		*/
		
		
		$text = array();
				
		if(is_string($elements))
		{
			parse_str($elements, $elements);
		}
		if(!is_array($options))
		{
			parse_str((string) $options, $options);
		}

		if(!empty($options['help']))
		{
			$help = "<div class='field-help'>".$options['help']. '</div>';
			unset($options['help']);
		}
		
		foreach ($elements as $value => $label)
		{
			$label = defset($label, $label);
			
			$helpLabel = (is_array($help)) ? vartrue($help[$value]) : $help;
		
		// Bootstrap Style Code - for use later. 	
			$options['label'] = $label;
			$options['help'] = $helpLabel;
			$text[] = $this->radio($name, $value, (string) $checked === (string) $value, $options);
	
		//	$text[] = $this->radio($name, $value, (string) $checked === (string) $value)."".$this->label($label, $name, $value).(isset($helpLabel) ? "<div class='field-help'>".$helpLabel."</div>" : '');
		}
		
	//	if($multi_line === false)
	//	{
		//	return implode("&nbsp;&nbsp;", $text);
	//	}
		
		// support of UI owned 'newline' parameter
		if(!varset($options['sep']) && vartrue($options['newline']))
		{
			$options['sep'] = '<br />';
		} // TODO div class=separator?
		$separator = varset($options['sep'], ' ');
	//	return print_a($text,true);
		return implode($separator, $text).$help;
		
		// return implode("\n", $text);
		//XXX Limiting markup. 
	//	return "<div class='field-spacer' style='width:50%;float:left'>".implode("</div><div class='field-spacer' style='width:50%;float:left'>", $text)."</div>";

	}

	/**
	 * Just for BC - use the $options['label'] instead. 
	 */
	public function label($text, $name = '', $value = '')
	{
	//	$backtrack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2); 
	//	e107::getMessage()->addDebug("Deprecated \$frm->label() used in: ".print_a($backtrack,true));
		$for_id = $this->_format_id('', $name, $value, 'for');
		return "<label$for_id class='e-tip legacy'>{$text}</label>";
	}


	/**
	 * @param $text
	 * @return string|null
	 */
	public function help($text)
	{
		if(empty($text) || $this->_helptip === 0)
		{
			return null;
		}

		$ret = '';
	//	$ret .= '<i class="admin-ui-help-tip far fa-question-circle"><!-- --></i>';

		$ret .= $this->tp->toGlyph('far-question-circle', ['class'=>'admin-ui-help-tip', 'placeholder'=>'<!-- -->']);

		$ret .= '<div class="field-help" data-placement="left" style="display:none">'.defset($text,$text).'</div>'; // display:none to prevent visibility during page load. 

		return $ret;
	}

	/**
	 * @param $name
	 * @param $options
	 * @return string
	 */
	public function select_open($name, $options = array())
	{

		if(!is_array($options))
		{
			parse_str((string) $options, $options);
		}


		if(!empty($options['size']) && !is_numeric($options['size']))
		{
			if(!empty($options['class']))
			{
				$options['class'] .= ' form-control input-' .$options['size'];
			}
			else
			{
				$options['class'] = 'form-control input-' .$options['size'];
			}

			unset($options['size']); // don't include in html 'size='. 	
		}

        if(!empty($options['title']) && is_array($options['title']))
        {
            unset($options['title']);
        }


		$options = $this->format_options('select', $name, $options);
	
		return "<select name='{$name}'".$this->get_attributes($options, $name). '>';
	}


	/**
	 * @deprecated - use select() instead.
	 */
	public function selectbox($name, $option_array, $selected = false, $options = array(), $defaultBlank= false)
	{
		trigger_error('<b>'.__METHOD__.' is deprecated.</b> Use select() instead.', E_USER_DEPRECATED); // NO LAN

		return $this->select($name, $option_array, $selected, $options, $defaultBlank);	
	}



	/**
	 *
	 * @param string        $name
	 * @param array|string  $option_array
	 * @param boolean       $selected [optional]
	 * @param string|array  $options = [
	 *      'useValues'		=> (bool)   when true uses array values as the key.
	 *      'disabled'		=> (array)  list of $option_array keys which should be disabled. eg. array('key_1', 'key_2');
	 * ]
	 * @param bool|string   $defaultBlank [optional] set to TRUE if the first entry should be blank, or to a string to use it for the blank description.
	 * @return string       HTML text for display
	 */
	public function select($name, $option_array, $selected = false, $options = array(), $defaultBlank= false)
	{
		if(!is_array($options))
		{
			parse_str((string) $options, $options);
		}

		if($option_array === 'yesno')
		{
			$option_array = array(1 => LAN_YES, 0 => LAN_NO);
		}

		if(!empty($options['multiple']))
		{
			$name = (strpos($name, '[') === false) ? $name.'[]' : $name;
			if(!is_array($selected))
			{
				$selected = explode(',', $selected);
			}

		}

		$text = $this->select_open($name, $options)."\n";

		if(isset($options['default']))
		{
			if($options['default'] === 'blank')
			{
				$options['default'] = '&nbsp;';			
			}
			$text .= $this->option($options['default'], varset($options['defaultValue']));
		}
		elseif($defaultBlank)
		{
			$diz = is_string($defaultBlank) ? $defaultBlank : '&nbsp;';
			$text .= $this->option($diz, '');
		}
		
		if(!empty($options['useValues'])) // use values as keys.
		{
			$new = array();
			foreach($option_array as $v)
			{
				$new[$v] = (string) $v;
			}	
			$option_array = $new;	
		}

		$text .= $this->option_multi($option_array, $selected, $options)."\n".$this->select_close();
		return $text;
	}
	
	
	
	
	

	/**
	 * Universal Userclass selector - checkboxes, dropdown, everything. 
	 * @param string $name - form element name
	 * @param int $curval - current userclass value(s) as array or comma separated.
	 * @param string $type - checkbox|dropdown  default is dropdown.
	 * @param string|array $options = [ classlist or query string or key=value pair.
	 *      'options'   => (string) comma-separated list of display options. 'options=admin,mainadmin,classes&vetted=1&exclusions=0' etc.
	 *  ]
	 * @example $frm->userclass('name', 0, 'dropdown', 'classes'); // display all userclasses
	 * @example $frm->userclass('name', 0, 'dropdown', 'classes,matchclass'); // display only classes to which the user belongs.
	 * @return string form element(s)
	 */
	public function userclass($name, $curval=255, $type=null, $options=null)
	{
		if(!empty($options))
		{
			if(is_array($options))
			{
				$opt = $options;
			}
			elseif(strpos($options,'=')!==false)
			{
				parse_str($options,$opt);
			}
			else
			{
				$opt = array('options'=>$options);
			}

		}
		else
		{
			$opt = array();
		}

		$optlist = vartrue($opt['options'],null);

		switch ($type)
		{
			case 'checkbox':
				return e107::getUserClass()->uc_checkboxes($name, $curval, $optlist, null,false);
			break;

			case 'dropdown':
			default:
				return e107::getUserClass()->uc_dropdown($name, $curval, $optlist, $opt);
			break;
		}

	}
	
	
	/**
	 * Renders a generic search box. If $filter has values, a filter box will be included with the options provided. 
	 * 
	 */
	public function search($name, $searchVal, $submitName, $filterName='', $filterArray=false, $filterVal=false)
	{
		$tp = $this->tp;
		
		$text = '<span class="input-append input-group e-search">
    		'.$this->text($name, $searchVal,20,'class=search-query&placeholder='.LAN_SEARCH.'&hellip;').'
   			 <span class="input-group-btn"><button class="btn btn-primary" name="'.$submitName.'" type="submit">'.$tp->toGlyph('fa-search').'</button></span>
    	</span>';
		
		
		
		if(is_array($filterArray))
		{
			$text .= $this->select($filterName, $filterArray, $filterVal);
		}
		
	//	$text .= $this->admin_button($submitName,LAN_SEARCH,'search');
		
		return $text;
		
		/*
		$text .= 
		
						<select style="display: none;" data-original-title="Filter the results below" name="filter_options" id="filter-options" class="e-tip tbox select filter" title="">
							<option value="">Display All</option>
							<option value="___reset___">Clear Filter</option>
								<optgroup class="optgroup" label="Filter by&nbsp;Category">
<option value="faq_parent__1">General</option>
<option value="faq_parent__2">Misc</option>
<option value="faq_parent__4">Test 3</option>
	</optgroup>

						</select><div class="btn-group bootstrap-select e-tip tbox select filter"><button id="filter-options" class="btn dropdown-toggle clearfix" data-toggle="dropdown" data-bs-toggle="dropdown"><span class="filter-option pull-left">Display All</span>&nbsp;<span class="caret"></span></button><ul style="max-height: none; overflow-y: auto;" class="dropdown-menu" role="menu"><li rel="0"><a tabindex="-1" class="">Display All</a></li><li rel="1"><a tabindex="-1" class="">Clear Filter</a></li><li rel="2"><dt class="optgroup-div">Filter by&nbsp;Category</dt><a tabindex="-1" class="opt ">General</a></li><li rel="3"><a tabindex="-1" class="opt ">Misc</a></li><li rel="4"><a tabindex="-1" class="opt ">Test 3</a></li></ul></div>
						<div class="e-autocomplete"></div>
						
						
			<button type="submit" name="etrigger_filter" value="etrigger_filter" id="etrigger-filter" class="btn filter e-hide-if-js btn-primary"><span>Filter</span></button>
		
						<span class="indicator" style="display: none;">
							<img src="/e107_2.0/e107_images/generic/loading_16.gif" class="icon action S16" alt="Loading...">
						</span>	
		
		*/
	}


	/**
	 * @param       $name
	 * @param null  $current_value
	 * @param null  $uc_options
	 * @param array $select_options multiple, default
	 * @param array $opt_options
	 * @return string
	 */
	public function uc_select($name, $current_value=null, $uc_options=null, $select_options = array(), $opt_options = array())
	{

/*	var_dump($name);
	var_dump($current_value);
var_dump($uc_options);
var_dump($select_options);*/


		if(!empty($select_options['multiple']) && substr($name,-1) !== ']')
		{
			$name .= '[]';
		}

		if(($current_value === null || $current_value === '') && !empty($uc_options)) // make the first in the opt list the default value.
		{
			$tmp = explode(',', $uc_options);
			$current_value =  e107::getUserClass()->getClassFromKey($tmp[0]);

			if(isset($select_options['default']))
			{
				$current_value = (int) $select_options['default'];
			}
		}

		if(!empty($current_value) && !is_numeric($current_value)) // convert name to id.
		{
			//$current_value = $this->_uc->getID($current_value);
			// issue #3249 Accept also comma separated values
			if (!is_array($current_value))
			{
				$current_value = explode(',', $current_value);
			}
			$tmp = array();
			foreach($current_value as $val)
			{
				if (!empty($val))
				{
					$tmp[] = !is_numeric($val) ? $this->_uc->getID(trim($val)) : (int) $val;
				}
			}
			$current_value = implode(',', $tmp);
			unset($tmp);
		}

		$text = $this->select_open($name, $select_options)."\n";
		$text .= $this->_uc->vetted_tree($name, array($this, '_uc_select_cb'), $current_value, $uc_options, $opt_options)."\n";
		$text .= $this->select_close();

		return $text;
	}

	// Callback for vetted_tree - Creates the option list for a selection box

	/**
	 * @param $treename
	 * @param $classnum
	 * @param $current_value
	 * @param $nest_level
	 * @return string
	 */
	public function _uc_select_cb($treename, $classnum, $current_value, $nest_level)
	{
		$classIndex = abs($classnum);			// Handle negative class values
		$classSign = (strpos($classnum, '-') === 0) ? '-' : '';
		
		if($classnum == e_UC_BLANK)
		{
			return $this->option('&nbsp;', '');
		}

		$tmp = explode(',', $current_value);
		if($nest_level == 0)
		{
			$prefix = '';
			$style = 'font-weight:bold; font-style: italic;';
		}
		elseif($nest_level == 1)
		{
			$prefix = '&nbsp;&nbsp;';
			$style = 'font-weight:bold';
		}
		else
		{
			$prefix = '&nbsp;&nbsp;'.str_repeat('--', $nest_level - 1).'&gt;';
			$style = '';
		}

		return $this->option($prefix.$this->_uc->getName($classnum), $classSign.$classIndex, ($current_value !== '' && in_array($classnum, $tmp)), array('style' => ($style)))."\n";
	}


	/**
	 * @param $label
	 * @param $disabled
	 * @param $options
	 * @return string
	 */
	public function optgroup_open($label, $disabled = false, $options = null)
	{
		$unique = 'optgroup-'.$this->name2id($label);
		return "<optgroup class='optgroup $unique ".varset($options['class'])."' label='{$label}'".($disabled ? " disabled='disabled'" : '').">\n";
	}

	/**
	 * <option> tag generation.
	 * @param $option_title
	 * @param $value
	 * @param bool $selected
	 * @param string $options (eg. disabled=1)
	 * @return string
	 */
	public function option($option_title, $value, $selected = false, $options = '')
	{
	    if(is_string($options))
	    {
		    parse_str($options, $options);
	    }

		if ($value === false)
		{
			$value = '';
		}

		$options = $this->format_options('option', '', $options);
		$options['selected'] = $selected; //comes as separate argument just for convenience

		$ltitle = is_string($option_title) ? strtolower($option_title) : $option_title;

		$label = ($ltitle === 'true' || $ltitle === 'false') ? $option_title : defset($option_title, $option_title);

		return "<option" . $this->attributes(['value' => $value]) . $this->get_attributes($options) . '>'
			. $label .
			'</option>';
	}


    /**
    * Use selectbox() instead.
    */
	public function option_multi($option_array, $selected = false, $options = array())
	{
		if(is_string($option_array))
		{
			parse_str($option_array, $option_array);
		}

		$text = '';

		if(empty($option_array))
		{
			return $this->option('','');
		}

		$opts = $options;


		if(isset($options['empty']) && (empty($selected) || $selected === array(0=>'')))
		{
			$selected = $options['empty'];
		}


		if($selected === array(0=>'') ) // quick fix. @see github issue #4609
		{
		//	$selected = 0;
		}

		foreach ((array) $option_array as $value => $label)
		{

			if(is_array($label))
			{
				$text .= $this->optgroup($value, $label, $selected, $options, 0);
			}
			else
			{

				$sel = is_array($selected) ? in_array($value, $selected) : ($value == $selected); // comparison as int/string currently required for admin-ui to function correctly.

				if(!empty($options['optDisabled']) && is_array($options['optDisabled']))
				{
					$opts['disabled'] = in_array($value, $options['optDisabled']);
				}

				if(!empty($options['title'][$value]))
				{
					$opts['data-title'] = $options['title'][$value];
				}
				elseif(isset($opts['data-title']))
				{
					unset($opts['data-title']);
				}

				$text .= $this->option($label, $value, $sel, $opts)."\n";
			}
		}

		return $text;
	}


	/**
	 * No compliant, but it works.
	 * @param $value
	 * @param $label
	 * @param $selected
	 * @param $options
	 * @param int $level
	 * @return string
	 */
	private function optgroup($value, $label, $selected, $options, $level=1)
	{
		$level++;
		$text = $this->optgroup_open($value, null, array('class'=>'level-'.$level));

		$opts = $options;

		foreach($label as $val => $lab)
		{
			if(is_array($lab))
			{
				$text .= $this->optgroup($val,$lab,$selected,$options,$level);
			}
			else
			{
				if(!empty($options['optDisabled']) && is_array($options['optDisabled']))
				{
					$opts['disabled'] = in_array($val, $options['optDisabled']);
				}

				$text .= $this->option($lab, $val, (is_array($selected) ? in_array($val, $selected) : $selected == $val), $opts)."\n";
			}

		}

		$text .= $this->optgroup_close();

		return $text;
	}


	/**
	 * @return string
	 */
	public function optgroup_close()
	{
		return "</optgroup>\n";
	}

	/**
	 * @return string
	 */
	public function select_close()
	{
		return '</select>';
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $options
	 * @return string
	 */
	public function hidden($name, $value, $options = array())
	{
		$options = $this->format_options('hidden', $name, $options);

		return "<input" . $this->attributes([
				'type'  => 'hidden',
				'name'  => $name,
				'value' => $value,
			]) . $this->get_attributes($options, $name, $value) . ' />';
	}

	/**
	 * Generate hidden security field
	 * @return string
	 */
	public function token()
	{
		return "<input" . $this->attributes([
				'type'  => 'hidden',
				'name'  => 'e-token',
				'value' => defset('e_TOKEN'),
			]) . " />";
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $options
	 * @return string
	 */
	public function submit($name, $value, $options = array())
	{
		$options = $this->format_options('submit', $name, $options);

		return "<input" . $this->attributes([
				'type'  => 'submit',
				'name'  => $name,
				'value' => $value,
			]) . $this->get_attributes($options, $name, $value) . ' />';
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $image
	 * @param $title
	 * @param $options
	 * @return string
	 */
	public function submit_image($name, $value, $image, $title='', $options = array())
	{
		$tp = $this->tp;

		if(!empty($options['icon']))
		{
			$customIcon = $options['icon'];
			unset($options['icon']);
		}

		$options = $this->format_options('submit_image', $name, $options);
		switch ($image)
		{
			case 'edit':
				$icon = deftrue('e_ADMIN_AREA') ? defset('ADMIN_EDIT_ICON') : $tp->toIcon('e-edit-32');
				$options['class'] = $options['class'] === 'action' ? 'btn btn-success action edit' : $options['class'];
			break;

			case 'delete':
				$icon = deftrue('e_ADMIN_AREA') ? defset('ADMIN_DELETE_ICON') : $tp->toIcon('fa-trash.glyph');
				$options['class'] = $options['class'] === 'action' ? 'btn btn-danger action delete' : $options['class'];
				$options['data-confirm'] = LAN_JSCONFIRM;
			break;

			case 'execute':
				$icon = deftrue('e_ADMIN_AREA') ? defset('ADMIN_EXECUTE_ICON') : $tp->toIcon('fa-power-off.glyph');
				$options['class'] = $options['class'] === 'action' ? 'btn btn-default btn-secondary action execute' : $options['class'];
			break;

			case 'view':
				$icon = $tp->toIcon('e-view-32');
				$options['class'] = $options['class'] === 'action' ? 'btn btn-default btn-secondary action view' : $options['class'];
				break;
		}

		$options['title'] = $title;//shorthand

		if (!empty($customIcon))
		{
			$icon = $customIcon;
		}

		return "<button" . $this->attributes([
				'type'           => 'submit',
				'name'           => $name,
				'data-placement' => 'left',
				'value'          => $value,
			]) . $this->get_attributes($options, $name, $value) . '  >' . $icon . '</button>';


	}

	/**
	 * Alias of admin_button, adds the etrigger_ prefix required for UI triggers
	 * @see e_form::admin_button()
	 */
	public function admin_trigger($name, $value, $action = 'submit', $label = '', $options = array())
	{
		return $this->admin_button('etrigger_'.$name, $value, $action, $label, $options);
	}


	/**
	 * Generic Button Element. 
	 * @param string $name
	 * @param string|array $value
	 * @param string $action [optional] default is submit - use 'dropdown' for a bootstrap dropdown button. 
	 * @param string $label [optional]
	 * @param string|array $options [optional]
	 * @return string
	 */
	public function button($name, $value, $action = 'submit', $label = '', $options = array())
	{
		if($action === 'dropdown' && deftrue('BOOTSTRAP') && is_array($value))
		{
		//	$options = $this->format_options('admin_button', $name, $options);
			$options['class'] = vartrue($options['class']);
			
			$align = vartrue($options['align'],'left');
					
			$text = '<div class="btn-group pull-'.$align.'">
			    <a class="btn dropdown-toggle '.$options['class'].'" data-toggle="dropdown" data-bs-toggle="dropdown" href="#">
			    '.($label ?: LAN_NO_LABEL_PROVIDED).'
			    <span class="caret"></span>
			    </a>
			    <ul class="dropdown-menu">
			    ';
			
			foreach($value as $k=>$v)
			{
				$text .= '<li class="dropdown-item">'.$v.'</li>';
			}
			
			$text .= '
			    </ul>
			    </div>';
			
			return $text;	
		}			
				

		
		return $this->admin_button($name, $value, $action, $label, $options);
		
	}

	/**
	 * Render a Breadcrumb in Bootstrap format. 
	 * @param array $array =[
	 *      'url'		=> (string)		
	 *      'text'		=> (string)	
	 * ]	
	 * @param bool $force - used internally to prevent duplicate {--BREADCUMB---} and template breadcrumbs from both displaying at once.
	 */
	public function breadcrumb($array, $force = false)
	{
		if($force === false && defset('THEME_VERSION') === 2.3) // ignore template breadcrumb.
		{
			return null;
		}

		if(deftrue('e_FRONTPAGE'))
		{
			return null;
		}

		if(!is_array($array)){ return; }
		
		$opt = array();

		if(!empty($array['home']['icon'])) // custom home icon.
		{
			$homeIcon = $array['home']['icon'];
			unset($array['home']['icon']);
		}
		else
		{
			$fallbackIcon = '<svg class="svg-inline--fa fa-home fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><!-- Font Awesome Free 5.15.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) --><path d="M280.37 148.26L96 300.11V464a16 16 0 0 0 16 16l112.06-.29a16 16 0 0 0 15.92-16V368a16 16 0 0 1 16-16h64a16 16 0 0 1 16 16v95.64a16 16 0 0 0 16 16.05L464 480a16 16 0 0 0 16-16V300L295.67 148.26a12.19 12.19 0 0 0-15.3 0zM571.6 251.47L488 182.56V44.05a12 12 0 0 0-12-12h-56a12 12 0 0 0-12 12v72.61L318.47 43a48 48 0 0 0-61 0L4.34 251.47a12 12 0 0 0-1.6 16.9l25.5 31A12 12 0 0 0 45.15 301l235.22-193.74a12.19 12.19 0 0 1 15.3 0L530.9 301a12 12 0 0 0 16.9-1.6l25.5-31a12 12 0 0 0-1.7-16.93z"></path></svg>';
			$homeIcon = ($this->_fontawesome) ? $this->tp->toGlyph('fa-home.glyph') : $fallbackIcon;
		}

		
		$opt[] = "<a href='".e_HTTP."' aria-label='Homepage'>".$homeIcon. '</a>'; // Add Site-Pref to disable?

		$text = "\n<ul class=\"breadcrumb\">\n";
		$text .= '<li class="breadcrumb-item">';

		foreach($array as $val)
		{
			if(!isset($val['url']) || ($val['url'] === e_REQUEST_URI)) // automatic link removal for current page.
			{
				$val['url'] = null;
			}

			$ret = '';
			$ret .= !empty($val['url']) ? "<a href='".$val['url']."'>" : '';
			$ret .= vartrue($val['text']);
			$ret .= !empty($val['url']) ? '</a>' : '';
			
			if($ret != '')
			{
				$opt[] = $ret;
			}	
		}
	
		$sep = (deftrue('BOOTSTRAP')) ? '' : "<span class='divider'>/</span>";
	
		$text .= implode($sep."</li>\n<li class='breadcrumb-item'>",$opt);
	
		$text .= "</li>\n</ul>";
		
	//	return print_a($opt,true);
	
		return $text;	

	}


	/**
	 * Render a direct link admin-edit button on the frontend.
	 * @param $url
	 * @param string $perms
	 * @return string
	 */
	public function instantEditButton($url, $perms='0')
	{
		if(deftrue('BOOTSTRAP') && getperms($perms))
		{
			return "<span class='e-instant-edit hidden-print'><a" . $this->attributes([
					'target' => '_blank',
					'title'  => LAN_EDIT,
					'href'   => $url,
				]) . ">" . $this->tp->toGlyph('fa-edit') . '</a></span>';
		}

		return '';

	}




	/**
	 * Admin Button - for front-end, use button();
	 * @param string $name
	 * @param string $value
	 * @param string $action [optional] default is submit
	 * @param string $label [optional]
	 * @param string|array $options [optional]
	 * @return string
	 */
	public function admin_button($name, $value, $action = 'submit', $label = '', $options = array())
	{
		$action = (string) $action;
		$btype = 'submit';
		if (strpos($action, 'action') === 0 || $action === 'button')
		{
			$btype = 'button';
		}

		$attributes = [
			'type'  => $btype,
			'name'  => $name,
			'value' => $value,
		];

		if (isset($options['loading']) && ($options['loading'] == false))
		{
			unset($options['loading']);
		}
		else
		{
			$attributes = ['data-loading-icon' => $this->_fontawesome ? 'fa-spinner' : null] + $attributes; // data-disable breaks db.php charset Fix.
		}

		$confirmation = LAN_JSCONFIRM;

		if(!empty($options['confirm']))
		{
			$confirmation = $options['confirm'];
		}

		$options = $this->format_options('admin_button', $name, $options);

		$class = 'btn';
		$class .= (!empty($action) && $action !== 'button') ? ' '. $action : '';

		if(!empty($options['class']))
		{
			$class .= ' '.$options['class'];
		}
				// Ability to use any kind of button class for the selected action.
		if(!$this->defaultButtonClassExists($class))
		{
			$class .= ' ' . $this->getDefaultButtonClassByAction($action);
		}


		$options['class'] = $class;

		if(empty($label))
		{
			$label = $value;
		}


		switch ($action)
		{
			case 'checkall':
				$options['class'] .= ' btn-mini btn-xs';
				break;

			case 'delete':
			case 'danger':
				$options['data-confirm'] = $confirmation;
				break;

			case 'batch':
			case 'batch e-hide-if-js':
				// FIXME hide-js shouldn't be here.
				break;

			case 'filter':
			case 'filter e-hide-if-js':
				$options['class'] = 'btn btn-default';
				break;
		}

		return '<button' . $this->attributes($attributes) . $this->get_attributes($options, $name) . "><span>{$label}</span></button>";
	}

	/**
	 * Helper function to check if a (CSS) class already contains a button class?
	 *
	 * @param string $class
	 *  The class we want to check.
	 *
	 * @return bool
	 *  True if $class already contains a button class. Otherwise false.
	 */
	private function defaultButtonClassExists($class = '')
	{
		// Bootstrap button classes.
		// @see http://getbootstrap.com/css/#buttons-options
		$btnClasses = array(
			'btn-default',
			'btn-primary',
			'btn-success',
			'btn-info',
			'btn-warning',
			'btn-danger',
			'btn-link',
		);

		foreach($btnClasses as $btnClass)
		{
			if(strpos($class, $btnClass) !== false)
			{
				return true;
			}
		}

		return false;
	}






	/**
	 * Helper function to get default button class by action.
	 *
	 * @param string $action
	 *  Action for a button. See button().
	 *
	 * @return string $class
	 *  Default button class.
	 */
	private function getDefaultButtonClassByAction($action)
	{
		switch($action)
		{
			case 'update':
			case 'create':
			case 'import':
			case 'submit':
			case 'execute':
			case 'success':
				$class = 'btn-success';
				break;

			case 'delete':
			case 'danger':
				$class = 'btn-danger';
				break;

			case 'other':
			case 'login':
			case 'batch e-hide-if-js':
			case 'filter e-hide-if-js':
			case 'batch':
			case 'filter':
			case 'primary':
				$class = 'btn-primary';
				break;

			case 'warning':
			case 'confirm':
				$class = 'btn-warning';
				break;

			case 'default':
			case 'checkall':
			case 'cancel':
			default:
				$class = 'btn-default';
				break;
		}

		return $class;
	}

	/**
	 * @return int
	 */
	public function getNext()
	{
		if(!$this->_tabindex_enabled)
		{
			return 0;
		}
		++$this->_tabindex_counter;
		return $this->_tabindex_counter;
	}

	/**
	 * @return int
	 */
	public function getCurrent()
	{
		if(!$this->_tabindex_enabled)
		{
			return 0;
		}
		return $this->_tabindex_counter;
	}

	/**
	 * @param $reset
	 * @return void
	 */
	public function resetTabindex($reset = 0)
	{
		$this->_tabindex_counter = $reset;
	}

	/**
	 * Build a series of HTML attributes from the provided array
	 *
	 * @param array $attributes Key-value pairs of HTML attributes. The value must not be HTML-encoded. If the value is
	 *                          boolean true, the value will be set to the key (e.g. `['required' => true]` becomes
	 *                          "required='required'").
	 * @return string The HTML attributes to concatenate inside an HTML tag
	 */
	private function attributes($attributes)
	{
		return $this->tp->toAttributes($attributes, true);
	}

	/**
	 * @param $options
	 * @param $name
	 * @param $value
	 * @return string
	 */
	public function get_attributes($options, $name = '', $value = '')
	{
		$ret = '';
		//
		foreach ($options as $option => $optval)
		{
			if ($option !== 'other')
			{
				$optval = html_entity_decode(trim((string) $optval));
			}
			switch ($option)
			{

				case 'id':
					$ret .= $this->_format_id($optval, $name, $value);
					break;

				case 'class':
				case 'size':
				case 'title':
				case 'label':
				case 'maxlength':
				case 'wrap':
				case 'autocomplete':
				case 'pattern':
					$ret .= $this->attributes([$option => $optval]);
					break;

				case 'readonly':
				case 'multiple':
				case 'selected':
				case 'checked':
				case 'disabled':
				case 'required':
				case 'autofocus':
					$ret .= $this->attributes([$option => (bool) $optval]);
					break;

				case 'placeholder':
					if($optval) {  
					  $optval = deftrue($optval, $optval);  
					  $ret .= $this->attributes([$option => $optval]);
					}
					break;

				case 'tabindex':
					if($optval)
					{
						$ret .= " tabindex='{$optval}'";
					}
					elseif($optval === false || !$this->_tabindex_enabled)
					{
						break;
					}
					else
					{
						++$this->_tabindex_counter;
						$ret .= $this->attributes([$option => $this->_tabindex_counter]);
					}
					break;

				case 'other':
					if($optval)
					{
						$ret .= " $optval";
					}
					break;

				default:
					if(strpos($option,'data-') === 0)
					{
						$ret .= $this->attributes([$option => $optval]);
					}
				break;
			}


				
		}

		return $ret;
	}

	/**
	 * Auto-build field attribute id
	 *
	 * @param string $id_value value for attribute id passed with the option array
	 * @param string $name the name attribute passed to that field
	 * @param mixed $value the value attribute passed to that field
	 * @return string formatted id attribute
	 */
	protected function _format_id($id_value, $name, $value = null, $return_attribute = 'id')
	{
		if($id_value === false)
		{
			 return '';
		}

		if(is_array($value))
		{
			$value = null;
		}

		//format data first
		$name = trim($this->name2id($name), '-');
		$value = trim(preg_replace('#[^a-zA-Z0-9\-]#', '-', $value), '-');
		//$value = trim(preg_replace('#[^a-z0-9\-]#/i','-', $value), '-');		// This should work - but didn't for me!
		$value = trim(str_replace('/', '-', $value), '-');                    // Why?
		if (!$id_value && is_numeric($value))
		{
			$id_value = $value;
		}

		// clean - do it better, this could lead to dups
		$id_value = trim($id_value, '-');

		if($return_attribute === null) // return only the value.
		{
			if (empty($id_value))
			{
				$ret = ($name) . ($value ? "-{$value}" : '');
			}
			elseif (is_numeric($id_value) && $name) // also useful when name is e.g. name='my_name[some_id]'
			{
				$ret = "{$name}-{$id_value}";
			}
			else // also useful when name is e.g. name='my_name[]'
			{
				$ret = (string) ($id_value);
			}

			return $ret;
		}

		if (empty($id_value))
		{
			$ret = "{$name}" . ($value ? "-{$value}" : '');
		}
		elseif (is_numeric($id_value) && $name) // also useful when name is e.g. name='my_name[some_id]'
		{
			$ret = "{$name}-{$id_value}";
		}
		else // also useful when name is e.g. name='my_name[]'
		{
			$ret = "{$id_value}";
		}

		return " $return_attribute='" . htmlentities($ret, ENT_QUOTES) . "'";
	}

	/**
	 * @param $name
	 * @return string
	 */
	public function name2id($name)
	{
		$name = strtolower($name);
		$name = $this->tp->toASCII($name);
		return rtrim(str_replace(array('[]', '[', ']', '_', '/', ' ','.', '(', ')', '::', ':', '?','=',"'",','), array('-', '-', '', '-', '-', '-', '-','','','-','','-','-','',''), $name), '-');
	}

	/**
	 * Format options based on the field type,
	 * merge with default
	 *
	 * @param string $type
	 * @param string $name form name attribute value
	 * @param array|string $user_options
	 * @return array merged options
	 */
	public function format_options($type, $name, $user_options=null)
	{
		if(is_string($user_options))
		{
			parse_str($user_options, $user_options); 
		}

		$def_options = $this->_default_options($type);
	

		if(is_array($user_options))
		{
			$user_options['name'] = $name; //required for some of the automated tasks
			
			foreach (array_keys($user_options) as $key)
			{
				if(!isset($def_options[$key]) && strpos($key,'data-') !== 0)
				{
					unset($user_options[$key]); // data-xxxx exempt //remove it?
				}
			}	
		}
		else 
		{
			$user_options = array('name' => $name); //required for some of the automated tasks	
		}
		
		return array_merge($def_options, $user_options);
	}

	/**
	 * Get default options array based on the field type
	 *
	 * @param string $type
	 * @return array default options
	 */
	protected function _default_options($type)
	{
		if(isset($this->_cached_attributes[$type]))
		{
			return $this->_cached_attributes[$type];
		}

		$def_options = array(
			'id' 			=> '',
			'class' 		=> '',
			'title' 		=> '',
			'size' 			=> '',
			'readonly' 		=> false,
			'selected' 		=> false,
			'checked' 		=> false,
			'disabled' 		=> false,
			'required' 		=> false,	
			'autofocus'		=> false,	
			'tabindex' 		=> 0,
			'label' 		=> '',
			'placeholder' 	=> '',
			'pattern'		=> '',
			'other' 		=> '',
			'autocomplete' 	=> '',
			'maxlength'		=> '',
			'wrap'          => '',
			'multiple'      => '',

			//	'multiple' => false, - see case 'select'
		);

		$form_control = (THEME_LEGACY !== true) ? ' form-control' : '';

		switch ($type) {
			case 'hidden':
				$def_options = array('id' => false, 'disabled' => false, 'other' => '');
				break;

			case 'text':
				$def_options['class'] = 'tbox input-text'.$form_control;
				unset($def_options['selected'], $def_options['checked']);
				break;

			case 'number':
				$def_options['class'] = 'tbox '.$form_control;
				break;

			case 'file':
				$def_options['class'] = 'tbox file';
				unset($def_options['selected'], $def_options['checked']);
				break;

			case 'textarea':
				$def_options['class'] = 'tbox textarea'.$form_control;
				unset($def_options['selected'], $def_options['checked'], $def_options['size']);
				break;

			case 'select':
				$def_options['class'] = 'tbox select'.$form_control;
				$def_options['multiple'] = false;
				unset($def_options['checked']);
				break;

			case 'option':
				$def_options = array('class' => '', 'selected' => false, 'other' => '', 'disabled' => false, 'label' => '');
				break;

			case 'radio':
				//$def_options['class'] = ' ';
				$def_options = array('class' => '', 'id'=>'');
				unset($def_options['size'], $def_options['selected']);
				break;

			case 'checkbox':
				unset($def_options['size'],  $def_options['selected']);
				break;

			case 'submit':
				$def_options['class'] = 'button btn btn-primary';
				unset($def_options['checked'], $def_options['selected'], $def_options['readonly']);
				break;

			case 'submit_image':
				$def_options['class'] = 'action';
				unset($def_options['checked'], $def_options['selected'], $def_options['readonly']);
				break;

			case 'admin_button':
				unset($def_options['checked'],  $def_options['selected'], $def_options['readonly']);
				break;

		}

		$this->_cached_attributes[$type] = $def_options;
		return $def_options;
	}

	/**
	 * @param $columnsArray
	 * @param $columnsDefault
	 * @param $id
	 * @return string
	 */
	public function columnSelector($columnsArray, $columnsDefault = array(), $id = 'column_options')
	{
		$columnsArray = array_filter($columnsArray);
		$tabs = []; 

		if($adminUI = e107::getAdminUI())
		{
			try
			{
				$tabs = $adminUI->getController()->getTabs();
			}
			catch (Exception $e)
			{
			   // do something
			}
		}

		
	// navbar-header nav-header
	// navbar-header nav-header
		$text = '<div class="col-selection dropdown e-tip pull-right float-right" data-placement="left">
    <a class="dropdown-toggle" title="'.LAN_EFORM_008.'" data-toggle="dropdown" data-bs-toggle="dropdown" href="#"><i class="icon fas fa-sliders"></i></a>
    <ul class="list-group dropdown-menu  col-selection e-noclick" role="menu" aria-labelledby="dLabel">
   
    <li class="list-group-item "><h5 class="list-group-item-heading">'.LAN_EFORM_009.'</h5></li>
    <li class="list-group-item col-selection-list">
     <ul class="nav scroll-menu" >';
		
        unset($columnsArray['options'], $columnsArray['checkboxes']);

		foreach($columnsArray as $key => $fld)
		{
			if(!isset($fld['type']) || $fld['type'] === null) // Fixes #4083
			{
				continue;
			}

			$theType = vartrue($fld['type']);
			if (empty($fld['forced']) && empty($fld['nolist']) && $theType !== 'hidden' && $theType !== 'upload')
			{
				$checked = (in_array($key,$columnsDefault)) ?  TRUE : FALSE;
				$title = '';
				if(isset($fld['tab']))
				{
					$tb = $fld['tab'];
					if(!empty($tabs[$tb]))
					{
						$title = $tabs[$tb].": ";
					}
				}

				$ttl = isset($fld['title']) ? defset($fld['title'], $fld['title']) : $key;
				$title .= $ttl;

				$text .= "
					<li role='menuitem'><a href='#' title=\"$title\">
						".$this->checkbox('e-columns[]', $key, $checked,'label='.$ttl). '
					</a>
					</li>
				';
			}
		}

		// has issues with the checkboxes.
        $text .= "
				</ul>
				</li>
				 <li class='list-group-item'>
				<div id='{$id}-button' class='right'>
					".$this->admin_button('etrigger_ecolumns', LAN_SAVE, 'btn btn-primary btn-small'). '
				</div>
				 </li>
				</ul>
			</div>';
			
	//	$text .= "</div></div>";

		$text .= '';
	
	
	/*
	$text = '<div class="dropdown">
    <a class="dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" href="#"><b class="caret"></b></a>
    <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
    <li>hi</li>
    </ul>
    </div>';
	*/	
		return $text;
	}


	/**
	 * @param $fieldarray
	 * @param $columnPref
	 * @return string
	 */
	public function colGroup($fieldarray, $columnPref = '')
	{
        $text = '';

		foreach($fieldarray as $key=>$val)
		{
			if (($key === 'options' || in_array($key, $columnPref) ||  !empty($val['forced'])) && empty($val['nolist']))
			{

				$class = vartrue($val['class']) ? 'class="'.$val['class'].'"' : '';
				$width = vartrue($val['width']) ? ' style="width:'.$val['width'].'"' : '';
				$text .= '<col '.$class.$width.' />
				';
			}
		}

		return '
			<colgroup>
				'.$text.'
			</colgroup>
		';
	}

	/**
	 * @param $fieldarray
	 * @param $columnPref
	 * @param $querypattern
	 * @param $requeststr
	 * @return string
	 */
	public function thead($fieldarray, $columnPref = array(), $querypattern = '', $requeststr = '')
	{
        $text = '';

        $querypattern = strip_tags($querypattern);
        if(!$requeststr)
        {
	        $requeststr = rawurldecode(e_QUERY);
        }
        $requeststr = strip_tags($requeststr);

		// Recommended pattern: mode=list&field=[FIELD]&asc=[ASC]&from=[FROM]
		if(strpos($querypattern,'&')!==FALSE)
		{
			// we can assume it's always $_GET since that's what it will generate
			// more flexible (e.g. pass default values for order/field when they can't be found in e_QUERY) & secure
			$tmp = str_replace('&amp;', '&', $requeststr ?: e_QUERY);
			parse_str($tmp, $tmp);

			$etmp = array();
			parse_str(str_replace('&amp;', '&', $querypattern), $etmp);
		}
		else // Legacy Queries. eg. main.[FIELD].[ASC].[FROM]
		{
			$tmp = explode('.', ($requeststr ?: e_QUERY));
			$etmp = explode('.', $querypattern);
		}

		foreach($etmp as $key => $val)    // I'm sure there's a more efficient way to do this, but too tired to see it right now!.
		{
        	if($val === '[FIELD]')
			{
            	$field = varset($tmp[$key]);
			}

			if($val === '[ASC]')
			{
            	$ascdesc = varset($tmp[$key]);
			}
			if($val === '[FROM]')
			{
            	$fromval = varset($tmp[$key]);
			}
		}

		if(!varset($fromval)){ $fromval = 0; }

		$sorted = varset($ascdesc);
        $ascdesc = ($sorted === 'desc') ? 'asc' : 'desc';

		foreach($fieldarray as $key=>$val)
		{
     		if ((in_array($key, $columnPref) || ($key === 'options' && isset($val['title'])) || (vartrue($val['forced']))) && !vartrue($val['nolist']))
			{
				$cl = (vartrue($val['thclass'])) ? " class='".$val['thclass']."'" : '';

				$aClass = ($key === $field) ? "sorted-" . $sorted : null;

				$text .= "
					<th id='e-column-".str_replace('_', '-', $key)."'{$cl}>
				";

				if ($querypattern != '' && $key !== 'options' && $key !== 'checkboxes' && !vartrue($val['nosort']))
				{
					$from = ($key == $field) ? $fromval : 0;
					$srch = array('[FIELD]', '[ASC]', '[FROM]');
					$repl = array($key, $ascdesc, $from);
					$val['url'] = e_SELF . '?' . str_replace($srch, $repl, $querypattern);
				}


				$text .= (vartrue($val['url'])) ? '<a' . $this->attributes([
						'class' => $aClass,
						'title' => LAN_SORT,
						'href'  => str_replace('&amp;', '&', $val['url']),
					]) . ">" : '';  // Really this column-sorting link should be auto-generated, or be autocreated via unobtrusive js.
				$text .= !empty($val['title']) ? defset($val['title'], $val['title']) : '';
				$text .= ($val['url']) ? '</a>' : '';
				$text .= ($key === 'options' && !vartrue($val['noselector'])) ? $this->columnSelector($fieldarray, $columnPref) : '';
				$text .= ($key === 'checkboxes') ? $this->checkbox_toggle('e-column-toggle', vartrue($val['toggle'], 'multiselect')) : '';


				$text .= '
					</th>
				';
			}
		}

		return '
		<thead>
	  		<tr>' . $text . '</tr>
		</thead>
		';

	}


	/**
	 * Render Table cells from hooks.
	 * @param array $data 
	 * @return string
	 */
	public function renderHooks($data)
	{
		$hooks = e107::getEvent()->triggerHook($data);
				
		$text = '';
		
		if(!empty($hooks))
		{
			foreach($hooks as $plugin => $hk)
			{
				$text .= "\n\n<!-- Hook : {$plugin} -->\n";
				
				if(!empty($hk))
				{
					foreach($hk as $hook)
					{
						$text .= "\t\t\t<tr>\n";
						$text .= "\t\t\t<td>".$hook['caption']."</td>\n";
						$text .= "\t\t\t<td>".$hook['html']. '';
						$text .= (varset($hook['help'])) ? "\n<span class='field-help'>".$hook['help']. '</span>' : '';
						$text .= "</td>\n\t\t\t</tr>\n";
					}

					
				}
			}
		}
		
		return $text;			
	}



			
	/**
	 * Render Related Items for the current page/news-item etc. 
	 * @param array $parm : comma separated list. ie. plugin folder names.
	 * @param string $tags : comma separated list of keywords to return related items of.
	 * @param array $curVal. eg. array('page'=> current-page-id-value);
	 * @param string $template
	 */
	public function renderRelated($parm, $tags, $curVal, $template=null) //XXX TODO Cache!
	{
		
		if(empty($tags))
		{
			return;	
		}

		$tags = str_replace(', ',',', $tags); //BC Fix, all tags should be comma separated without spaces ie. one,two NOT one, two

		e107::setRegistry('core/form/related',$tags); // TODO Move to elsewhere so it works without rendering? e107::related() set and get by plugins?

		if(!varset($parm['limit']))
		{
			$parm = array('limit' => 5);
		}
		
		if(!varset($parm['types']))
		{
			$parm['types'] = 'news';	
		}

		if(empty($template))
		{
			$TEMPLATE['start'] = '<hr><h4>' .defset('LAN_RELATED', 'Related')."</h4><ul class='e-related'>";
			$TEMPLATE['item'] = "<li><a href='{RELATED_URL}'>{RELATED_TITLE}</a></li>";
			$TEMPLATE['end'] = '</ul>';

		}
		else
		{
			$TEMPLATE = $template;
		}

		
			
		$tp = $this->tp;
			
		$types = explode(',',$parm['types']);
		$list = array();
		
		$head = $tp->parseTemplate($TEMPLATE['start']);

		foreach($types as $plug)
		{
		
			if(!$obj = e107::getAddon($plug,'e_related'))
			{
				continue;
			}
			
			$parm['current'] = (int) varset($curVal[$plug]);



		
			$tmp = $obj->compile($tags,$parm);	
		
			if(is_array($tmp) && count($tmp))
			{
				foreach($tmp as $val)
				{

					$row = array(
						'RELATED_URL'       => $tp->replaceConstants($val['url'],'full'),
						'RELATED_TITLE'     => $val['title'],
						'RELATED_IMAGE'     => $tp->toImage($val['image']),
						'RELATED_SUMMARY'   => $tp->toHTML($val['summary'],true,'SUMMARY'),
						'RELATED_DATE'		=> $val['date'],	
					);

					$list[] = $tp->simpleParse($TEMPLATE['item'], $row);

				}
			}		
		}
		
		if(count($list))
		{
			$text = "<div class='e-related clearfix hidden-print'>".$head.implode("\n",$list).$tp->parseTemplate($TEMPLATE['end']). '</div>';
			$caption = $tp->parseTemplate(varset($TEMPLATE['caption']));
			return e107::getRender()->tablerender($caption, $text, 'related', true);

		}
		
	}		



	/**
	 * Render Table cells from field listing.
	 * @param array $fieldarray - eg. $this->fields
	 * @param array $currentlist - eg $this->fieldpref
	 * @param array $fieldvalues - eg. $row
	 * @param string $pid - eg. table_id
	 * @return string|null
	 */
	public function renderTableCells($fieldarray, $currentlist, $fieldvalues, $pid)
	{

		$cnt = 0;
		$text = '';


		foreach ($fieldarray as $field => $data)
		{

			if(!isset($data['readParms']) || $data['readParms'] === '' )
			{
				$data['readParms'] = array();
			}
			elseif(is_string($data['readParms'])) // fix for readParms = '';
			{
				parse_str($data['readParms'],$data['readParms']);
			}
			// shouldn't happen... test with Admin->Users with user_xup visible and NULL values in user_xup table column before re-enabling this code.
			/*
			if(!isset($fieldvalues[$field]) && vartrue($data['alias']))
			{
				$fieldvalues[$data['alias']] = $fieldvalues[$data['field']];
				$field = $data['alias'];
			}
			*/

			//Not found
			if(!empty($data['nolist']) || (empty($data['forced']) && !in_array($field, $currentlist)))
			{
				continue;
			}

			if(vartrue($data['type']) !== 'method' && empty($data['forced']) && !isset($fieldvalues[$field]) && $fieldvalues[$field] !== null)
			{
				$text .= "
					<td>
						Not Found! ($field)
					</td>
				";

				continue;
			}

			$tdclass = vartrue($data['class']);

            if($field === 'checkboxes')
            {
	            $tdclass = $tdclass ? $tdclass . ' autocheck e-pointer' : 'autocheck e-pointer';
            }

			if($field === 'options')
			{
				$tdclass = $tdclass ? $tdclass . ' options' : 'options';
			}


			// there is no other way for now - prepare user data
			if(vartrue($data['type']) === 'user' /* && isset($data['readParms']['idField'])*/)
			{
				if(varset($data['readParms']) && is_string($data['readParms']))
				{
					parse_str($data['readParms'], $data['readParms']);
				}
				if(isset($data['readParms']['idField']))
				{
					$data['readParms']['__idval'] = $fieldvalues[$data['readParms']['idField']];
				}
				elseif(isset($fieldvalues['user_id'])) // Default
				{
					$data['readParms']['__idval'] = $fieldvalues['user_id'];
				}

				if(isset($data['readParms']['nameField']))
				{
					$data['readParms']['__nameval'] = $fieldvalues[$data['readParms']['nameField']];
				}
				elseif(isset($fieldvalues['user_name'])) // Default
				{
					$data['readParms']['__nameval'] = $fieldvalues['user_name'];
				}

			}

			$value = $this->renderValue($field, varset($fieldvalues[$field]), $data, varset($fieldvalues[$pid]));

			$text .= '
				<td' . $this->attributes(['class' => $tdclass]) . '>
					'.$value.'
				</td>
			';

			$cnt++;
		}

		if($cnt)
		{
			return $text;
		}

		return null;

	}

	/**
	 * Render Table row and cells from field listing.
	 *
	 * @param array  $fieldArray  - eg. $this->fields
	 * @param array  $fieldPref   - eg $this->fieldpref
	 * @param array  $fieldValues - eg. $row
	 * @param string $pid         - eg. table_id
	 * @return string
	 */
	public function renderTableRow($fieldArray, $fieldPref, $fieldValues, $pid)
	{

		if(!$ret = $this->renderTableCells($fieldArray, $fieldPref, $fieldValues, $pid))
		{
			return '';
		}

		unset($fieldValues['__trclass']);

		return '
				<tr id="row-' . $fieldValues[$pid] . '">
					'.$ret.'
				</tr>
			';
	}



	/**
	 * Inline Token
	 * @return string
	 */
	private function inlineToken()
	{
		$this->_inline_token = $this->_inline_token ?:
			password_hash(session_id(), PASSWORD_DEFAULT, ['cost' => 04]);
		return $this->_inline_token;
	}

	/**
	 * Create an Inline Edit link. 
	 * @param string $dbField : field being edited
	 * @param int $pid : primary ID of the row being edited.
	 * @param string $fieldName - Description of the field name (caption)
	 * @param mixed $curVal : existing value of in the field
	 * @param mixed $linkText : existing value displayed
	 * @param string $type text|textarea|select|date|checklist
	 * @param array $array : array data used in dropdowns etc.
	 */
	public function renderInline($dbField, $pid, $fieldName, $curVal, $linkText, $type='text', $array=null, $options=array())
	{
		$jsonArray = array();
				
		if(!empty($array))
		{
			foreach($array as $k=>$v)
			{
				$jsonArray[] = ['value' => $k, 'text' => str_replace("'", '`', (string) $v)]; // required format to retain order of elements.
			}
		}

		$source = $this->tp->toJSON($jsonArray);
		
		$mode = preg_replace('/[\W]/', '', vartrue($_GET['mode']));

		if(!isset($options['url']))
		{
			$options['url'] = e_SELF . "?mode={$mode}&action=inline&id={$pid}&ajax_used=1";
		}

		if (!empty($pid))
		{
			$options['pk'] = $pid;
		}

		$title = varset($options['title'], (LAN_EDIT . ' ' . defset($fieldName,$fieldName)));
		$class = varset($options['class']);

		unset($options['title']);

		$attributes = [
			'class'           => "e-tip e-editable editable-click $class",
			'data-name'       => $dbField,
			'data-source'     => is_array($array) ? $source : null,
			'title'           => $title,
			'data-type'       => $type,
			'data-inputclass' => 'x-editable-' . $this->name2id($dbField) . ' ' . $class,
			'data-value'      => $curVal,
			'href'            => '#',
		];

		$options['token'] = $this->inlineToken();

		if (!empty($options))
		{
			foreach ($options as $k => $opt)
			{
				$attributes += ['data-' . $k => $opt];
			}
		}

		return "<a" . $this->attributes($attributes) . ">$linkText</a>";
	}

	/**
	 * Check if a value should be linked and wrap in <a> tag if required.
	 * @todo global pref for the target option?
	 * @param mixed $value
	 * @param array $parms
	 * @param $id
	 * @example $frm->renderLink('label', array('link'=>'{e_PLUGIN}myplugin/myurl.php','target'=>'blank')
	 * @example $frm->renderLink('label', array('link'=>'{e_PLUGIN}myplugin/myurl.php?id=[id]','target'=>'blank')
	 * @example $frm->renderLink('label', array('link'=>'{e_PLUGIN}myplugin/myurl.php?id=[field-name]','target'=>'blank')
	 * @example $frm->renderLink('label', array('link'=>'db-field-name','target'=>'blank')
	 * @example $frm->renderLink('label', array('url'=>'e_url.php key','title'=>'click here');
	 * @return string
	 */
	public function renderLink($value, $parms, $id=null)
	{
		if(empty($parms['link']) && empty($parms['url']))
		{
			return $value;
		}

		/** @var e_admin_model $model */
		if (!$model = e107::getRegistry('core/adminUI/currentListModel')) // Try list model
		{
			$model = e107::getRegistry('core/adminUI/currentModel'); // try create/edit model.
		}

		$dialog = vartrue($parms['target']) === 'dialog' ? ' e-modal' : ''; // iframe
		$ext = vartrue($parms['target']) === 'blank' ? "external" : null; // new window
		$modal = vartrue($parms['target']) === 'modal' ? [
			"data-toggle"    => 'modal',
			"data-bs-toggle" => 'modal',
			"data-cache"     => 'false',
			"data-target"    => '#uiModal'
		] : [];

		$link = null;

		if (!empty($parms['url']) && !empty($model)) // ie. use e_url.php
		{
			//$plugin = $this->getController()->getPluginName();
			if ($plugin = e107::getRegistry('core/adminUI/currentPlugin'))
			{
				$data = $model->getData();
				$link = e107::url($plugin, $parms['url'], $data);
			}
		}
		elseif (!empty($model)) // old way.
		{
			$tp = $this->tp;

			$data = $model->getData();

			$link = str_replace('[id]', $id, $parms['link']);
			$link = $tp->replaceConstants($link); // SEF URL is not important since we're in admin.

			if ($parms['link'] === 'sef')
			{
				if (!$model->getUrl())
				{
					/** @var e_admin_controller_ui $controller */
					$controller = $this->getController();
					$model->setUrl($controller->getUrl());
				}

				// assemble the url
				$link = $model->url(null);
			}
			elseif(!empty($data[$parms['link']])) // support for a field-name as the link. eg. link_url.
			{
				$link = $tp->replaceConstants(vartrue($data[$parms['link']]));
			}
			elseif(strpos($link,'[')!==false && preg_match('/\[(\w+)\]/',$link, $match)) // field-name within [ ] brackets.
			{
				$field = $match[1];
				$link = str_replace($match[0], $data[$field],$link);
			}
		}
					// in case something goes wrong...
		if($link)
		{
			$attributes = [
					'class' => "e-tip{$dialog}",
					'rel'   => $ext,
					'href'  => $link,
					'title' => varset($parms['title'], LAN_EFORM_010),
				] + $modal;

			return "<a" . $this->attributes($attributes) . ">" . $value . '</a>';
		}

		return $value;

	}

	/**
	 * @param $parms
	 * @param $id
	 * @param $attributes
	 * @return string
	 */
	private function renderOptions($parms, $id, $attributes)
	{
		$tp = $this->tp;
		$cls = false;

		$editIconDefault = deftrue('ADMIN_EDIT_ICON', $tp->toGlyph('fa-edit'));
		$deleteIconDefault = deftrue('ADMIN_DELETE_ICON', $tp->toGlyph('fa-trash'));

		// option to set custom icons. @see e107_admin/image.php media_form_ui::options
		if(!empty($attributes['icons']))
		{
			$editIconDefault = !empty($attributes['icons']['edit']) ? $attributes['icons']['edit'] : $editIconDefault;
			$deleteIconDefault = !empty($attributes['icons']['delete']) ? $attributes['icons']['delete'] : $deleteIconDefault;
			unset($attributes['icons']);
		}

/*
		if($attributes['grid'])
		{
			$editIconDefault = $tp->toGlyph('fa-edit');
			$deleteIconDefault = $tp->toGlyph('fa-trash');
		}
*/
		/** @var e_admin_controller_ui $controller */
		$controller = $this->getController();
		$sf = $controller->getSortField();

		if(!isset($parms['sort']) && !empty($sf))
		{
			$parms['sort'] = true;
		}

		$text = "<div class='btn-group'>";

		if(!empty($parms['sort']) && empty($attributes['grid']))
		{
			$mode = preg_replace('/[\W]/', '', vartrue($_GET['mode']));
			$from = (int) vartrue($_GET['from'], 0);
			$text .= "<a" . $this->attributes([
					'class'       => 'e-sort sort-trigger btn btn-default',
					'style'       => 'cursor:move',
					'data-target' => e_SELF . "?mode=$mode&action=sort&ajax_used=1&from=$from",
					'title'       => LAN_RE_ORDER,
				]) . ">" . defset('ADMIN_SORT_ICON') . '</a> ';
		}


		if(varset($parms['editClass']))
		{
			$cls = (deftrue($parms['editClass'])) ? constant($parms['editClass']) : $parms['editClass'];
		}

		if(($cls === false || check_class($cls)) && varset($parms['edit'],1) == 1)
		{

			$qry = isset($attributes['query']) ? $attributes['query'] : e_QUERY; // @see image.php - media_form_ui::options()

			parse_str(str_replace('&amp;', '&', $qry), $query); //FIXME - FIX THIS
					// keep other vars in tact
			$query['action'] = 'edit';
			$query['id'] = $id;


			if(!empty($parms['target']) && $parms['target'] === 'modal')
			{
				$eModal = ' e-modal ';
				$eModalCap = !empty($parms['modalCaption']) ? $parms['modalCaption'] : "#" . $id;
				$query['iframe'] = 1;
			}
			else
			{
				$eModal = '';
				$eModalCap = null;
			}

			$query = http_build_query($query);

			$att = [
					'href'               => e_SELF . "?$query",
					'class'              => "btn btn-default btn-success$eModal",
					'data-modal-caption' => $eModalCap,
					'title'              => LAN_EDIT,
			//		'data-toggle'        => 'tooltip',
				//	'data-bs-toggle'     => 'tooltip',
					'data-placement'     => 'left',
				];

			if (!empty($parms['modalSubmit']))
			{
				$att['data-modal-submit'] = 'true';
			}
			
			$text .= '<a' . $this->attributes($att) . '>' . $editIconDefault . '</a>';
		}

		$delcls = !empty($attributes['noConfirm']) ? ' no-confirm' : '';
		if(varset($parms['deleteClass']) && varset($parms['delete'],1) == 1)
		{
			$cls = (deftrue($parms['deleteClass'])) ? constant($parms['deleteClass']) : $parms['deleteClass'];

			if(check_class($cls))
			{
				$parms['class'] =  'action delete btn btn-danger'.$delcls;
				unset($parms['deleteClass']);
				$parms['icon'] = $deleteIconDefault;
				$text .= $this->submit_image('etrigger_delete['.$id.']', $id, 'delete', LAN_DELETE.' [ ID: '.$id.' ]', $parms);
			}
		}
		else
		{
			$parms['class'] =  'action delete btn btn-danger'.$delcls;
			$parms['icon'] = $deleteIconDefault;
			$text .= $this->submit_image('etrigger_delete['.$id.']', $id, 'delete', LAN_DELETE.' [ ID: '.$id.' ]', $parms);
		}

				//$attributes['type'] = 'text';
		$text .= '</div>';

		return $text;

	}

	/**
	 * Render Field Value
	 * @param string $field field name
	 * @param mixed $value field value
	 * @param array $attributes field attributes including render parameters, element options - see e_admin_ui::$fields for required format
	 * @return string|null
	 */
	public function renderValue($field, $value, $attributes, $id = 0)
	{

		if(!empty($value) && !empty($attributes['data']) && ($attributes['data'] === 'array' || $attributes['data'] === 'json'))
		{
			$value = e107::unserialize($value);
		}

		if(!empty($attributes['multilan']) && is_array($value))
		{
			$value = varset($value[e_LANGUAGE]);
		}

		$parms = array();
		if(isset($attributes['readParms']))
		{
			if(!is_array($attributes['readParms']))
			{
				parse_str($attributes['readParms'], $attributes['readParms']);
			}
			$parms = $attributes['readParms'];
		}

		// @see custom fields in cpage which accept json params.
		if(!empty($attributes['writeParms']) && $tmpOpt = $this->tp->isJSON($attributes['writeParms']))
		{
			$attributes['writeParms'] = $tmpOpt;
			unset($tmpOpt);
		}



		if(!empty($attributes['inline']))
		{
			$parms['editable'] = true;
		} // attribute alias
		if(!empty($attributes['sort']))
		{
			$parms['sort'] = true;
		} // attribute alias
		
		if(!empty($parms['type'])) // Allow the use of a different 'type' in readMode. eg. type=method.
		{
			$attributes['type'] = $parms['type'];	
		}

		$this->renderValueTrigger($field, $value, $parms, $id);

		$tp = $this->tp;
		switch($field) // special fields
		{
			case 'options':
				
				if(!empty($attributes['type']) && ($attributes['type'] === 'method')) // Allow override with 'options' function.
				{
					$attributes['mode'] = 'read';
					if(isset($attributes['method']) && $attributes['method'] && method_exists($this, $attributes['method']))
					{
						$method = $attributes['method'];
						return $this->$method($parms, $value, $id, $attributes);
						
					}
					elseif(method_exists($this, 'options'))
					{
						//return  $this->options($field, $value, $attributes, $id); 
						// consistent method arguments, fixed in admin cron administration
						$attributes['type'] = null; // prevent infinite loop.

						return $this->options($parms, $value, $id, $attributes);
					}
				}

				if(!$value)
				{
					$value = $this->renderOptions($parms, $id, $attributes);
				}

				return $value;
			break;

			case 'checkboxes':

				//$attributes['type'] = 'text';
				if(empty($attributes['writeParms'])) // avoid comflicts with a field called 'checkboxes'
				{
					$value = $this->checkbox(vartrue($attributes['toggle'], 'multiselect').'['.$id.']', $id);
					return $value;
				}


			break;
		}

		if(!empty($attributes['grid']) && empty($attributes['type']))
		{
			return null;
		}

		if(empty($attributes['type']))
		{
			e107::getDebug()->log("Field '".$field."' is missing a value for 'type'.");
		//	e107::getDebug()->log($value);
		//	e107::getDebug()->log($attributes);
		}


		switch($attributes['type'])
		{
			case 'number':
				if(!$value)
				{
					$value = '0';
				}

				if($parms)
				{
					if (!empty($parms['format']) && $parms['format'] === 'bytes')
					{
                        $value = eHelper::parseMemorySize($value, varset($parms['decimals'], 2)); // Use 'decimals' from parms or default to 2
					}
					elseif(isset($parms['sep']))
					{
						$value = number_format($value, varset($parms['decimals'],0), vartrue($parms['point'], '.'), vartrue($parms['sep'], ' '));
					}
					else
					{
						$value = number_format($value, varset($parms['decimals'], 0));
					}
				}

				if(empty($attributes['noedit']) && !empty($parms['editable']) && empty($parms['link'])) // avoid bad markup, better solution coming up
				{
					$value = $this->renderInline($field,$id,$attributes['title'],$value, $value, 'number');
				}
				elseif(!empty($parms['link']))
				{
					$value = $this->renderLink($value,$parms,$id);
				}

				$value = vartrue($parms['pre']).$value.vartrue($parms['post']);
				// else same
			break;

			case 'country':

				$_value = $this->getCountry($value);

				if(empty($attributes['noedit']) && !empty($parms['editable']) && empty($parms['link'])) // avoid bad markup, better solution coming up
				{
					$arr = $this->getCountry();
					$value = $this->renderInline($field,$id,$attributes['title'],$value, $_value, 'select', $arr);
				}
				else
				{
					$value = $_value;
				}

			break;

			case 'ip':
				//$e107 = e107::getInstance();
				$value = "<span title='".$value."'>".e107::getIPHandler()->ipDecode($value).'</span>';
				// else same
			break;

			case 'templates':
			case 'layouts':

				if(!empty($attributes['writeParms']) && is_string($attributes['writeParms']))
				{
					parse_str($attributes['writeParms'], $attributes['writeParms']);
				}

				if(empty($attributes['noedit']) && !empty($parms['editable']) && empty($parms['link'])) // avoid bad markup, better solution coming up
				{
					$wparms     = $attributes['writeParms'];

					$location   = vartrue($wparms['plugin']); // empty - core
					$ilocation  = vartrue($wparms['id'], $location); // omit if same as plugin name
					$where      = vartrue($wparms['area'], 'front'); //default is 'front'
					$filter     = varset($wparms['filter']);
					$merge      = isset($wparms['merge']) ? (bool) $wparms['merge'] : true;

					$layouts    = e107::getLayouts($location, $ilocation, $where, $filter, $merge, false);

					$label   = varset($layouts[$value], $value);

					$value = $this->renderInline($field, $id, $attributes['title'], $value, $label, 'select', $layouts);
				}

				$value = vartrue($parms['pre']) . $value . vartrue($parms['post']);
			break;

			case 'checkboxes':
			case 'comma':
			case 'dropdown':
				// XXX - should we use readParams at all here? see writeParms check below

			//	if($parms && is_array($parms)) // FIXME - add support for multi-level arrays (option groups)
			//	{
					//FIXME return no value at all when 'editable=1' is a readParm. See FAQs templates. 
				//	$value = vartrue($parms['pre']).vartrue($parms[$value]).vartrue($parms['post']);
				//	break; 
			//	}
				
				// NEW - multiple (array values) support
				// FIXME - add support for multi-level arrays (option groups)
				if(!is_array($attributes['writeParms']))
				{
					parse_str($attributes['writeParms'], $attributes['writeParms']);
				}
				$wparms = $attributes['writeParms'];

				if (!isset($wparms['__options'])) $wparms['__options'] = null;
				if(!is_array($wparms['__options']))
				{
					parse_str((string) $wparms['__options'], $wparms['__options']);
				}

				if(!empty($wparms['optArray']))
				{
					$fopts = $wparms;
					$wparms = $fopts['optArray'];
					unset($fopts['optArray']);
					$wparms['__options'] = $fopts;
				}


				$opts = $wparms['__options'];
				unset($wparms['__options']);
				$_value = $value;
				
				if($attributes['type'] === 'checkboxes' || $attributes['type'] === 'comma')
				{
					$opts['multiple'] = true;	
				}
			
				if(!empty($opts['multiple']))
				{
					$ret = array();
					$value = is_array($value) ? $value : explode(',', $value);
					foreach ($value as $v)
					{
						if(isset($wparms[$v]))
						{
							$ret[] = $wparms[$v];
						}
					}
					$value = implode(', ', $ret);


				}
				else
				{
					$ret = '';
					if(isset($wparms[$value]))
					{
						$ret = $wparms[$value];
					}
					$value = $ret;
				}
			
				$value = ($value ? vartrue($parms['pre']).defset($value, $value).vartrue($parms['post']) : '');
				
				if(empty($attributes['noedit']) && !empty($parms['editable']) && empty($parms['link'])) // avoid bad markup, better solution coming up
				{				
					$xtype = ($attributes['type'] === 'dropdown') ? 'select' : 'checklist';
					$value = $this->renderInline($field, $id, $attributes['title'], $_value, $value, $xtype, $wparms);
				}
								
				// return ;
			break;

			case 'radio':


				if($parms && isset($parms[$value])) // FIXME - add support for multi-level arrays (option groups)
				{
					$value = vartrue($parms['pre']).vartrue($parms[$value]).vartrue($parms['post']);
					break;
				}

				if(!is_array($attributes['writeParms']))
				{
					parse_str($attributes['writeParms'], $attributes['writeParms']);
				}

				if(!empty($attributes['writeParms']['optArray']))
				{
					$radioValue = $attributes['writeParms']['optArray'][$value];

					if(empty($attributes['noedit']) && !empty($parms['editable']) && empty($parms['link'])) // avoid bad markup, better solution coming up
					{
						$radioValue = $this->renderInline($field, $id, $attributes['title'], $value, $radioValue, 'select', $attributes['writeParms']['optArray']);
					}
				}
				else
				{
					$radioValue = vartrue($attributes['writeParms'][$value]);
				}


				$value = vartrue($attributes['writeParms']['__options']['pre']).$radioValue.vartrue($attributes['writeParms']['__options']['post']);
			break;

			case 'tags':
				if(!empty($parms['constant']))
				{
					$value = defset($value, $value);
				}

				if(!empty($parms['truncate']))
				{
					$value = $tp->text_truncate($value, $parms['truncate'], '...');
				}
				elseif(!empty($parms['htmltruncate']))
				{
					$value = $tp->html_truncate($value, $parms['htmltruncate']);
				}
				if(!empty($parms['wrap']))
				{
					$value = $tp->htmlwrap($value, (int) $parms['wrap'], varset($parms['wrapChar'], ' '));
				}

				$value = $this->renderLink($value,$parms,$id);

				if(empty($value))
				{
					$value = '-';
					$setValue = null;
				}
				else
				{
					$setValue = '';

					if($attributes['type'] === 'tags' && !empty($value))
					{
						$setValue = $value;
						$value = str_replace(',', ', ', $value); // add spaces so it wraps, but don't change the actual values.
					}
				}


				if(!vartrue($attributes['noedit']) && vartrue($parms['editable']) && !vartrue($parms['link'])) // avoid bad markup, better solution coming up
				{
					$options['selectize'] = array(
						'create'     => true,
						'maxItems'   => vartrue($parms['maxItems'], 7),
						'mode'       => 'multi',
						'e_editable' => $field . '_' . $id,
					);

					$maxlength = vartrue($parms['maxlength'], 80);
					unset($parms['maxlength']);

					$tpl = $this->text($field, $value, $maxlength, $options);

					$mode = preg_replace('/[\W]/', '', vartrue($_GET['mode']));
					$value = "<a" . $this->attributes([
							'id'             => "{$field}_{$id}",
							'class'          => 'e-tip e-editable editable-click editable-tags',
							'data-emptytext' => '-',
							'data-tpl'       => $tpl,
							'data-name'      => $field,
							'data-token'     => $this->inlineToken(),
							'title'          => LAN_EDIT . ' ' . $attributes['title'],
							'data-type'      => 'text',
							'data-pk'        => $id,
							'data-value'     => $setValue,
							'data-url'       => e_SELF . "?mode=$mode&action=inline&id=$id&ajax_used=1",
							'href'           => '#',
						]) . ">" . $value . '</a>';
				}

				$value = vartrue($parms['pre']) . $value . vartrue($parms['post']);
				break;

			case 'text':

				if(!empty($parms['constant']))
				{
					$value = defset($value,$value);
				}

				if(is_array($value) && ($attributes['data'] === 'json'))
				{
					$value = e107::serialize($value, 'json');
				}

				if(!empty($parms['truncate']))
				{
					$value = $tp->text_truncate($value, $parms['truncate'], '...');
				}
				elseif(!empty($parms['htmltruncate']))
				{
					$value = $tp->html_truncate($value, $parms['htmltruncate']);
				}
				if(!empty($parms['wrap']))
				{
					$value = $tp->htmlwrap($value, (int)$parms['wrap'], varset($parms['wrapChar'], ' '));
				}

				$value = $this->renderLink($value,$parms,$id);


				if(empty($value))
				{
					$value = '-';
				}
				else
				{
					if($attributes['type'] === 'tags' && !empty($value))
					{
						$value = str_replace(',', ', ', $value); // add spaces so it wraps, but don't change the actual values.
					}
				}

					
				if(empty($attributes['noedit']) && !empty($parms['editable']) && empty($parms['link'])) // avoid bad markup, better solution coming up
				{
					$value = $this->renderInline($field,$id,$attributes['title'],$value, $value);
				}

				$value = vartrue($parms['pre']).$value.vartrue($parms['post']);
			break;
            
            

			case 'bbarea':
			case 'textarea':
				
				
				if($attributes['type'] === 'textarea' && !vartrue($attributes['noedit']) && vartrue($parms['editable']) && !vartrue($parms['link'])) // avoid bad markup, better solution coming up
				{
					return $this->renderInline($field,$id,$attributes['title'],$value,substr($value,0,50). '...','textarea'); //FIXME.
				}


				$expand = '<span class="e-expandit-ellipsis">...</span>';
				$toexpand = false;
				if($attributes['type'] === 'bbarea' && !isset($parms['bb']))
				{
					$parms['bb'] = true;
				} //force bb parsing for bbareas
				$elid = trim(str_replace('_', '-', $field)).'-'.$id;
				if(!vartrue($parms['noparse']))
				{
					$value = $tp->toHTML($value, (vartrue($parms['bb']) ? true : false), vartrue($parms['parse']));
				}
				if(!empty($parms['expand']) || !empty($parms['truncate']) || !empty($parms['htmltruncate']))
				{
					$ttl = vartrue($parms['expand']);
					if($ttl == 1)
					{
						$dataAttr = "data-text-more='" . LAN_MORE . "' data-text-less='" . LAN_LESS . "'";
						$ttl = $expand."<button class='btn btn-default btn-secondary btn-xs btn-mini pull-right' {$dataAttr}>" . LAN_MORE . '</button>';
					}
					
					$expands = '<a href="#'.$elid.'-expand" class="e-show-if-js e-expandit e-expandit-inline">'.defset($ttl, $ttl). '</a>';
				}

				$oldval = $value;
				if(!empty($parms['truncate']))
				{
					$oldval = strip_tags($value);
					$value = $oldval;
					$value = $tp->text_truncate($value, $parms['truncate'], '');
					$toexpand = $value != $oldval;
				}
				elseif(!empty($parms['htmltruncate']))
				{
					$value = $tp->html_truncate($value, $parms['htmltruncate'], '');
					$toexpand = $value != $oldval;
				}
				if($toexpand)
				{
					// force hide! TODO - core style .expand-c (expand container)
					$value .= '<span class="expand-c" style="display: none" id="'.$elid.'-expand"><span>'.str_replace($value,'',$oldval).'</span></span>';
					$value .= varset($expands); 	// 'More..' button. Keep it at the bottom so it does't cut the sentence.
				}
				
				
				
			break;

			case 'icon':

				$value = "<span class='icon-preview'>".$tp->toIcon($value,$parms). '</span>';
				
			break;
			
			case 'file':
				if(!empty($parms['base']))
				{
					$url = $parms['base'].$value;
				}
				else
				{
					$url = $this->tp->replaceConstants($value, 'full');
				}
				$name = basename($value);
				$value = '<a href="'.$url.'" title="Direct link to '.$name.'" rel="external">'.$name.'</a>';
			break;

			case 'image': //js tooltip...

				$thparms = array();
				$createLink = true;

						// Support readParms example: thumb=1&w=200&h=300
						// Support readParms example: thumb=1&aw=80&ah=30
				if(isset($parms['h']))		{ 	$thparms['h'] 	= (int) $parms['h']; 		}
				if(isset($parms['ah']))		{ 	$thparms['ah'] 	= (int) $parms['ah']; 	}
				if(isset($parms['w']))		{ 	$thparms['w'] 	= (int) $parms['w']; 		}
				if(isset($parms['aw']))		{ 	$thparms['aw'] 	= (int) $parms['aw']; 	}
				if(isset($parms['crop']))	{ 	$thparms['crop'] = $parms['crop']; 	}



				if($value)
				{
					
					if(strpos($value, ',')!==false)
					{
						$tmp = explode(',',$value);
						$value = $tmp[0];
						unset($tmp);	
					}		

					if(empty($parms['thumb_aw']) && !empty($parms['thumb']) && strpos($parms['thumb'],'x')!==false)
					{
						list($parms['thumb_aw'],$parms['thumb_ah']) = explode('x',$parms['thumb']);
					}

					$vparm = array('thumb'=>'tag','w'=> vartrue($parms['thumb_aw'],'80'));
					
					if($video = $tp->toVideo($value,$vparm))
					{
						return $video;
					}

					$fileOnly = basename($value);

					// Not an image but a file.  (media manager)  
					if(!preg_match("/\.(png|jpg|jpeg|gif|webp|PNG|JPG|JPEG|GIF|WEBP)/", $fileOnly) && strpos($fileOnly,'.') !== false)
					{
						$icon = '{e_IMAGE}filemanager/zip_32.png';
						$src = $tp->replaceConstants(vartrue($parms['pre']).$icon, 'abs');
					//	return $value;
						return $tp->toGlyph('fa-file','size=2x');
				//		return '<img src="'.$src.'" alt="'.$value.'" class="e-thumb" title="'.$value.'" />';
					}


					
					if(!empty($parms['thumb']))
					{

						if(isset($parms['link']) && empty($parms['link']))
						{
							$createLink = false;
						}
						
						// Support readParms example: thumb=200x300 (wxh)
						if(strpos($parms['thumb'],'x')!==false)
						{
							list($thparms['w'],$thparms['h']) = explode('x',$parms['thumb']); 	
						}
						
						// Support readParms example: thumb={width}
						if(!isset($parms['w']) && is_numeric($parms['thumb']) && $parms['thumb'] != '1')
						{
							$thparms['w'] = (int) $parms['thumb'];
						}
						elseif(!empty($parms['thumb_aw'])) // Legacy v2.
						{
							$thparms['aw'] = (int) $parms['thumb_aw'];
						}

						if(!empty($parms['legacyPath']))
						{
							$thparms['legacy'] = $parms['legacyPath'];
							$parms['pre'] = rtrim($parms['legacyPath'],'/').'/';
						}
					//	return print_a($thparms,true); 

						if(!empty($value[0]) && $value[0] === '{') // full path to convert.
						{
							$src = $tp->replaceConstants($value, 'abs');
						}
						else // legacy link without {e_XXX} path. eg. downloads thumbs.
						{
							$src = $tp->replaceConstants(vartrue($parms['pre']).$value, 'abs');
						}

						$alt = basename($src);


						$thparms['alt'] = $alt;
						$thparms['class'] = 'thumbnail e-thumb';

						//	e107::getDebug()->log($value);

						$ttl = $tp->toImage($value, $thparms);

						if ($createLink === false)
						{
							return $ttl;
						}


						$value = '<a' . $this->attributes([
								'href'               => $src,
								'data-modal-caption' => $alt,
								'data-target'        => '#uiModal',
								'class'              => "e-modal e-image-preview",
								'title'              => $alt,
								'rel'                => 'external',
							]) . '>' . $ttl . '</a>';
					}
					else
					{
						$src = $tp->replaceConstants(vartrue($parms['pre']) . $value, 'abs');
						$alt = $src; //basename($value);
						$ttl = vartrue($parms['title'], 'LAN_PREVIEW');
						$value = '<a' . $this->attributes([
								'href'  => $src,
								'class' => "e-image-preview",
								'title' => $alt,
								'rel'   => 'external',
							]) . '>' . defset($ttl, $ttl) . '</a>';
					}
				}
				elseif(!empty($parms['fallback']))
				{
					$value = $parms['fallback'];
					$thparms['class'] = 'thumbnail e-thumb fallback';
					return $tp->toImage($value, $thparms);
				}
			break;


			case 'media':
			case 'images':
				$firstItem = !empty($value[0]['path']) ? $value[0]['path'] : null; // display first item.
				return e107::getMedia()->previewTag($firstItem, $parms);
			break;
			
			case 'files':

				if(!empty($value))
				{
					if(!is_array($value))
					{
						return "Type 'files' must have a data type of 'array' or 'json'";
					}

					$ret = '<ol>';
					for ($i=0; $i < 5; $i++)
					{
						$ival 	= $value[$i]['path'];

						if(empty($ival))
						{
							continue;
						}

						$ret .=  '<li>'.$ival.'</li>';
					}
					$ret .= '</ol>';
					$value = $ret;
				}


			break; 
			
			case 'datestamp':
				$value = $value ? e107::getDate()->convert_date($value, vartrue($parms['mask'], 'short')) : '';
			break;
			
			case 'date':

				if(empty($attributes['noedit']) && !empty($parms['editable']) && empty($parms['link'])) // avoid bad markup, better solution coming up
				{
					$value = $this->renderInline($field,$id,$attributes['title'],$value, $value);
				}

				// just show original value
			break;

			case 'userclass':
				$dispvalue = $this->_uc->getName($value);
					// Inline Editing.  
				if(empty($attributes['noedit']) && !empty($parms['editable']) && empty($parms['link'])) // avoid bad markup, better solution coming up
				{
					// $mode = preg_replace('/[^\w]/', '', vartrue($_GET['mode'], ''));

					$uc_options = vartrue($parms['classlist'], 'public,guest,nobody,member,admin,main,classes'); // defaults to 'public,guest,nobody,member,classes' (userclass handler)
					unset($parms['classlist']);

					$array = e107::getUserClass()->uc_required_class_list($uc_options); //XXX Ugly looking (non-standard) function naming - TODO discuss name change.

					$value = $this->renderInline($field, $id, $attributes['title'], $value, $dispvalue, 'select', $array, array('placement'=>'left'));
				}
				else 
				{
					$value = $dispvalue;	
				}
			break;

			case 'userclasses':
			//	return $value;
				$classes = explode(',', $value);

				$uv = array();
				foreach ($classes as $cid)
				{
					if(!empty($parms['defaultLabel']) && $cid === '')
					{
						$uv[] = $parms['defaultLabel'];
						continue;
					}

					$uv[] = $this->_uc->getName($cid);
				}



				$dispvalue = implode(vartrue($parms['separator'], '<br />'), $uv);

				// Inline Editing.  
				if(!vartrue($attributes['noedit']) && vartrue($parms['editable']) && !vartrue($parms['link'])) // avoid bad markup, better solution coming up
				{
					$uc_options = vartrue($parms['classlist'], 'public,guest, nobody,member,admin,main,classes'); // defaults to 'public,guest,nobody,member,classes' (userclass handler)
					$array = e107::getUserClass()->uc_required_class_list($uc_options); //XXX Ugly looking (non-standard) function naming - TODO discuss name change.

					//NOTE Leading ',' required on $value; so it picks up existing value.
					$value = $this->renderInline($field,$id,$attributes['title'],",$value",$dispvalue,'checklist',$array,['placement'=>'bottom']);
				}
				else 
				{
					$value = $dispvalue;	
				}

				unset($parms['classlist']);
				
			break;

			/*case 'user_name':
			case 'user_loginname':
			case 'user_login':
			case 'user_customtitle':
			case 'user_email':*/
			case 'user':
				
				/*if(is_numeric($value))
				{
					$value = e107::user($value);
					if($value)
					{
						$value = $value[$attributes['type']] ? $value[$attributes['type']] : $value['user_name'];
					}
					else
					{
						$value = 'not found';
					}
				}*/
				$row_id = $id;
				// Dirty, but the only way for now
				$id = 0;
				$ttl = LAN_ANONYMOUS;

				//Defaults to user_id and user_name (when present) and when idField and nameField are not present.


				// previously set - real parameters are idField && nameField
				$id = vartrue($parms['__idval']);
				if($value && !is_numeric($value))
				{
					$id = vartrue($parms['__idval']);
					$ttl = $value;
				}
				elseif($value && is_numeric($value))
				{
					$id = $value;

					if (vartrue($parms['__nameval']))
					{
						$ttl = $parms['__nameval'];
					}
					else
					{
						$user = e107::user($value);
						if (vartrue($user['user_name']))
						{
							$ttl = $user['user_name'];
						}
					}
				}


				if(!empty($parms['link']) && $id && $ttl && is_numeric($id))
				{
					// Stay in admin area.
					$link = e_ADMIN . 'users.php?mode=main&action=edit&id=' . $id . '&readonly=1&iframe=1'; // e107::getUrl()->create('user/profile/view', array('id' => $id, 'name' => $ttl))

					$value = '<a' . $this->attributes([
							'class'              => "e-modal",
							'data-modal-caption' => "User #$id : $ttl",
							'href'               => $link,
							'title'              => LAN_EFORM_011
						]) . '>' . $ttl . '</a>';
				}
				else
				{
					$value = $ttl;
				}

				// Inline Editing.
				if(!vartrue($attributes['noedit']) && vartrue($parms['editable']) && !vartrue($parms['link'])) // avoid bad markup, better solution coming up
				{
					// Need a Unique Field ID to store field settings using e107::js('settings').
					$fieldID = $this->name2id($field . '_' . microtime(true));
					// Unique ID for each rows.
					$eEditableID = $this->name2id($fieldID . '_' . $row_id);
					//	$tpl = $this->userpicker($field, '', $ttl, $id, array('id' => $fieldID, 'selectize' => array('e_editable' => $eEditableID)));

					$tpl = $this->userpicker($fieldID, array('user_id' => $id, 'user_name' => $ttl), array('id' => $fieldID, 'inline' => $eEditableID));
					$mode = preg_replace('/[\W]/', '', vartrue($_GET['mode']));
					$value = "<a" . $this->attributes([
							'id'         => $eEditableID,
							'class'      => 'e-tip e-editable editable-click editable-userpicker',
							'data-clear' => 'false',
							'data-token' => $this->inlineToken(),
							'data-tpl'   => $tpl,
							'data-name'  => $field,
							'title'      => LAN_EDIT . ' ' . $attributes['title'],
							'data-type'  => 'text',
							'data-pk'    => $row_id,
							'data-value' => $id,
							'data-url'   => e_SELF . "?mode=$mode&action=inline&id=$row_id&ajax_used=1",
							'href'       => '#'
						]) . ">" . $ttl . '</a>';
				}
				
			break;

			/**
			 * $parms['true']  - label to use for true
			 * $parms['false'] - label to use for false
			 * $parms['enabled'] - alias of $parms['true']
			 * $parms['disabled'] - alias of $parms['false']
			 * $parms['reverse'] - use 0 for true and 1 for false.
			 */
			case 'bool':
			case 'boolean':
				$false = vartrue($parms['trueonly']) ? '' : defset('ADMIN_FALSE_ICON');

				if(!empty($parms['enabled']))
				{
					$parms['true'] = $parms['enabled'];
				}

				if(!empty($parms['disabled']))
				{
					$parms['false'] = $parms['disabled'];
				}

				$true = isset($parms['true']) ? $parms['true'] : defset('ADMIN_TRUE_ICON');

				if(!vartrue($attributes['noedit']) && vartrue($parms['editable']) && !vartrue($parms['link'])) // avoid bad markup, better solution coming up
				{
					if(isset($parms['false'])) // custom representation for 'false'. (supports font-awesome when set by css)
					{
						$false = $parms['false'];	
					}
					else
					{
						// https://stackoverflow.com/questions/2965971/how-to-add-images-in-select-list

						$false = ($value === '') ? '&square;' : '&#10799;'; // "&cross;";
					}
					
					$true = varset($parms['true'], '&#10004;' /*'&check;'*/); // custom representation for 'true'. (supports font-awesome when set by css)

			//		$true = '&#xf00c';
			//		$false = '\f00d';

					$value = (int) $value;
							
					$wparms = (vartrue($parms['reverse'])) ? array(0=>$true, 1=>$false) : array(0=>$false, 1=>$true);
					$dispValue = $wparms[$value];
					$styleClass = '';

                    if($true ==='&#10004;')
                    {
					    $styleClass = ($value === 1) ? 'admin-true-icon' : 'admin-false-icon';
                    }

					if(!isset($attributes['title']))
					{
						trigger_error("$field is missing the 'title' key/attribute", E_USER_WARNING);
					}
					return $this->renderInline($field, $id, $attributes['title'], $value, $dispValue, 'select', $wparms, array('class'=>'e-editable-boolean '.$styleClass));
				}
				
				if(!empty($parms['reverse']))
				{
					$value = ($value) ? $false : $true;
				}
				else
				{
					$value = $value ? $true : $false;
				}	
							
			break;

			case 'url':
				if(!$value)
				{
					break;
				}
				$ttl = $value;
				if (!empty($parms['href']))
				{
					return $tp->replaceConstants(vartrue($parms['pre']) . $value, varset($parms['replace_mod'], 'abs'));
				}
				if (!empty($parms['truncate']))
				{
					$ttl = $tp->text_truncate($value, $parms['truncate'], '...');
				}

				$target = (!empty($parms['target'])) ? $parms['target'] : null;
				$class = (!empty($parms['class'])) ? $parms['class'] : null;

				$value = '<a' . $this->attributes([
						'target' => $target,
						'class'  => $class,
						'href'   => $tp->replaceConstants(vartrue($parms['pre']) . $value, 'abs'),
						'title'  => $value,
					]) . ">" . $ttl . '</a>';
				break;

			case 'email':
				if (!$value)
				{
					break;
				}
				$ttl = $value;
				if (!empty($parms['truncate']))
				{
					$ttl = $tp->text_truncate($value, $parms['truncate'], '...');
				}

				$target = (!empty($parms['target'])) ? $parms['target'] : null;
				$class = (!empty($parms['class'])) ? $parms['class'] : null;

				$value = '<a' . $this->attributes([
						'target' => $target,
						'class'  => $class,
						'href'   => "mailto:$value",
						'title'  => $value,
					]) . ">" . $ttl . '</a>';
				break;

			case 'method': // Custom Function			
				$method = varset($attributes['field']); // prevents table alias in method names. ie. u.my_method.
				$_value = $value;

				$meth = (!empty($attributes['method'])) ? $attributes['method'] : $method;

				if(strpos($meth,'::')!==false)
				{
					list($className,$meth) = explode('::', $meth);
					$cls = new $className();
				}
				else
				{
					$cls = $this;
				}

				if(method_exists($cls,$meth))
				{
					$parms['field'] = $field;
					$mode = (!empty($attributes['mode'])) ? $attributes['mode'] :'read';
					$value = call_user_func_array(array($cls, $meth), array($value, $mode, $parms));
				}
				else
				{
					$className = get_class($cls);
					e107::getDebug()->log('Missing Method: ' .$className. '::' .$meth. ' ' .print_a($attributes,true));
					return "<span class='label label-important label-danger' title='".$className. '::' .$meth."'>Missing Method</span>";
				}
			//	 print_a($attributes);
					// Inline Editing.  
				if(empty($attributes['noedit']) && !empty($parms['editable'])) // avoid bad markup, better solution coming up
				{
					
					$mode = preg_replace('/[\W]/', '', vartrue($_GET['mode']));
					$methodParms = call_user_func_array(array($this, $meth), array($_value, 'inline', $parms));

					$inlineParms = (!empty($methodParms['inlineParms'])) ? $methodParms['inlineParms'] : null;

					if(!empty($methodParms['inlineType']))
					{
						$attributes['inline'] = $methodParms['inlineType'];
						$methodParms = (!empty($methodParms['inlineData'])) ? $methodParms['inlineData'] : null;
					}



					if(is_string($attributes['inline'])) // text, textarea, select, checklist. 
					{
						switch ($attributes['inline']) 
						{
					
							case 'checklist':
								$xtype = 'checklist';		
							break;
							
							case 'select':
							case 'dropdown':
								$xtype = 'select';		
							break;
							
							case 'textarea':
								$xtype = 'textarea';		
							break;
							
							
							default:
								$xtype = 'text';
								 $methodParms = null;
							break;
						}
					}

					if(!empty($xtype))
					{
						$value = varset($inlineParms['pre']).$this->renderInline($field, $id, $attributes['title'], $_value, $value, $xtype, $methodParms,$inlineParms).varset($inlineParms['post']);
					}

				}
							
			break;

			case 'hidden':
				if(!empty($parms['show']))
				{
					return ($value ?: vartrue($parms['empty']));
				}

				return '';
			break;
			
			case 'language': // All Known Languages. 
					
				if(!empty($value))
				{
					$_value = $value;
					if(strlen($value) === 2)
					{
						$value = e107::getLanguage()->convert($value);
					}
				}
				
				if(!vartrue($attributes['noedit']) && vartrue($parms['editable'])) 
				{
					$wparms = e107::getLanguage()->getList();
					return $this->renderInline($field, $id, $attributes['title'], $_value, $value, 'select', $wparms);	
				}	
				
				return $value;
				
			break;

			case 'lanlist': // installed languages. 
				$options = e107::getLanguage()->getLanSelectArray();

				if($options) // FIXME - add support for multi-level arrays (option groups)
				{
					if(!is_array($attributes['writeParms']))
					{
						parse_str($attributes['writeParms'], $attributes['writeParms']);
					}
					$wparms = $attributes['writeParms'];
					if(!is_array(varset($wparms['__options'])))
					{
						parse_str($wparms['__options'], $wparms['__options']);
					}
					$opts = $wparms['__options'];
					if($opts['multiple'])
					{
						$ret = array();
						$value = is_array($value) ? $value : explode(',', $value);
						foreach ($value as $v)
						{
							if(isset($options[$v]))
							{
								$ret[] = $options[$v];
							}
						}
						$value = implode(', ', $ret);
					}
					else
					{
						$ret = '';
						if(isset($options[$value]))
						{
							$ret = $options[$value];
						}
						$value = $ret;
					}
					$value = ($value ? vartrue($parms['pre']).$value.vartrue($parms['post']) : '');
				}
				else
				{
					$value = '';
				}
			break;

			//TODO - order

			default:
				$value = $this->renderLink($value,$parms,$id);
				//unknown type
			break;
		}

		return $value;
	}

	/**
	 * Auto-render Form Element
	 * @param string $key
	 * @param mixed $value
	 * @param array $attributes = [ field attributes including render parameters, element options - see e_admin_ui::$fields for required format
	 *      #param array (under construction) $required_data required array as defined in e_model/validator
	 *  'default'		=> (mixed)		 default value when empty (or default option when type='dropdown')
	 *  'defaultValue'  => (mixed)		 default option value when type='dropdown'
	 *  'empty'         => (mixed)		 default value when value is empty (dropdown and hidden only right now)
	 * ]
	 * @return string
	 */
	public function renderElement($key, $value, $attributes, $required_data = array(), $id = 0)
	{
		// Workaround for accepting poorly normalized values from the database where the data would have been stored
		// with HTML entities escaped.
		$key = html_entity_decode($key, ENT_QUOTES);

		if (!empty($value) && !empty($attributes['data']) && ($attributes['data'] === 'array' || $attributes['data'] === 'json'))
		{
			$value = e107::unserialize($value);
		}
		elseif (is_string($value))
		{
			// Workaround for accepting poorly normalized values from the database where the data would have been stored
			// with HTML entities escaped.
			$value = html_entity_decode($value, ENT_QUOTES);
		}

		$tp = $this->tp;
		$ret = '';

		$parms = vartrue($attributes['writeParms'], array());

		if($tmpOpt = $tp->isJSON($parms))
		{
			$parms = $tmpOpt;
			unset($tmpOpt);
		}

		if(is_string($parms))
		{
			parse_str($parms, $parms);
		}

		$ajaxParms = array();

		if(!empty($parms['ajax']))
		{
			$ajaxParms['data-src'] = varset($parms['ajax']['src']);
			$ajaxParms['data-target'] = varset($parms['ajax']['target']);
			$ajaxParms['data-method'] = varset($parms['ajax']['method'], 'html');
			$ajaxParms['data-loading'] = varset($parms['ajax']['loading'], 'fa-spinner'); //$tp->toGlyph('fa-spinner', array('spin'=>1))

			unset($attributes['writeParms']['ajax']);

		//	e107::getDebug()->log($parms['ajax']);
		}

		if(!empty($attributes['multilan']))
		{
			$value = is_array($value) ? varset($value[e_LANGUAGE]) : $value;
			$parms['post'] = "<small class='e-tip admin-multilanguage-field input-group-addon' style='cursor:help; padding-left:10px' title='".LAN_EFORM_012. ' (' .e_LANGUAGE.")'>".$tp->toGlyph('fa-language'). '</small>' .varset($parms['post']);
			$key .= '[' . e_LANGUAGE . ']';
		}

		if(empty($value) && isset($parms['default']) && $attributes['type'] !== 'dropdown') // Allow writeParms to set default value.
		{
			$value = $parms['default'];
		}

		// Two modes of read-only. 1 = read-only, but only when there is a value, 2 = read-only regardless.
		if(!empty($attributes['readonly']) && (!empty($value) || vartrue($attributes['readonly'])===2)) // quick fix (maybe 'noedit'=>'readonly'?)
		{
			if(!empty($attributes['writeParms'])) // eg. different size thumbnail on the edit page.
			{
				$attributes['readParms'] = $attributes['writeParms'];
			}

			$ret = $this->renderValue($key, $value, $attributes);

		//	if(!is_array($value)) // avoid value of 'Array'
			{
				$ret .= $this->hidden($key, $value);  // subject to removal - in most cases, there's no point posting fields that don't need to be saved.
			}

			return $ret;
		}
		
		// FIXME standard - writeParams['__options'] is introduced for list elements, bundle adding to writeParms is non reliable way
		$writeParamsOptionable =  array('dropdown', 'comma', 'radio', 'lanlist', 'language', 'user');
		$writeParamsDisabled =  array('layouts', 'templates', 'userclass', 'userclasses');

		// FIXME it breaks all list like elements - dropdowns, radio, etc
		if(!empty($required_data[0]) || !empty($attributes['required'])) // HTML5 'required' attribute
		{
			// FIXME - another approach, raise standards, remove checks
			if(in_array($attributes['type'], $writeParamsOptionable))
			{
				$parms['__options']['required'] = 1;	
			}
			elseif(!in_array($attributes['type'], $writeParamsDisabled))
			{
				$parms['required'] = 1;	
			}
		}
		
		// FIXME it breaks all list like elements - dropdowns, radio, etc
		if(!empty($required_data[3]) || !empty($attributes['pattern'])) // HTML5 'pattern' attribute
		{
			// FIXME - another approach, raise standards, remove checks
			if(in_array($attributes['type'], $writeParamsOptionable))
			{
				$parms['__options']['pattern'] = vartrue($attributes['pattern'], $required_data[3]);
			}
			elseif(!in_array($attributes['type'], $writeParamsDisabled))
			{
				$parms['pattern'] = vartrue($attributes['pattern'], $required_data[3]);	
			}
		}



		// XXX Fixes For the above.  - use optArray variable. eg. $field['key']['writeParms']['optArray'] = array('one','two','three');
		if(($attributes['type'] === 'dropdown' || $attributes['type'] === 'radio' || $attributes['type'] === 'checkboxes') && isset($parms['optArray']))
		{
			$fopts = $parms;
			$parms = $fopts['optArray'];
			unset($fopts['optArray']);
			$parms['__options'] = $fopts;
		}

		$this->renderElementTrigger($key, $value, $parms, $required_data, $id);
		
		switch($attributes['type'])
		{
			case 'number':
				$maxlength = vartrue($parms['maxlength'], 255);
				unset($parms['maxlength']);
				if(!vartrue($parms['size']))
				{
					$parms['size'] = 'small';
				}
				if(!vartrue($parms['class']))
				{
					$parms['class'] = 'tbox number e-spinner ';
				}
				if(!$value)
				{
					$value = '0';
				}
				$ret =  vartrue($parms['pre']).$this->number($key, $value, $maxlength, $parms).vartrue($parms['post']);
			break;

			case 'country':
				$ret = vartrue($parms['pre']).$this->country($key, $value, $parms).vartrue($parms['post']);
			break;

			case 'ip':
				$ret = vartrue($parms['pre']).$this->text($key, e107::getIPHandler()->ipDecode($value), 45, $parms).vartrue($parms['post']);
			break;

			case 'email':
				$maxlength = vartrue($parms['maxlength'], 255);
				unset($parms['maxlength']);
				$ret =  vartrue($parms['pre']).$this->email($key, $value, $maxlength, $parms).vartrue($parms['post']); // vartrue($parms['__options']) is limited. See 'required'=>true
			break;

			case 'url':
				$maxlength = vartrue($parms['maxlength'], 255);
				unset($parms['maxlength']);
				$ret =  vartrue($parms['pre']).$this->url($key, $value, $maxlength, $parms).vartrue($parms['post']); // vartrue($parms['__options']) is limited. See 'required'=>true
		
			break; 
		//	case 'email':
		
			case 'password': // encrypts to md5 when saved. 
				$maxlength = vartrue($parms['maxlength'], 255);
				unset($parms['maxlength']);
				if(!isset($parms['required']))
				{

					$parms['required'] = false;
				}
				$ret =  vartrue($parms['pre']).$this->password($key, $value, $maxlength, $parms).vartrue($parms['post']); // vartrue($parms['__options']) is limited. See 'required'=>true
			
			break; 

			case 'text':
			case 'progressbar':

				$maxlength = vartrue($parms['maxlength'], 255);
				unset($parms['maxlength']);

				if(!empty($parms['sef']) && e_LANGUAGE !== 'Japanese' && e_LANGUAGE !== 'Korean' && e_LANGUAGE !== 'Hebrew') // unsupported languages.(FIXME there are more)
				{
					$sefSource = $this->name2id($parms['sef']);
					$sefTarget = $this->name2id($key);
					if (!empty($parms['tdClassRight']))
					{
						$parms['tdClassRight'] .= 'input-group';
					}
					else
					{
						$parms['tdClassRight'] = 'input-group';
					}

					$parms['post'] = "<span class='form-inline input-group-btn pull-left'><a" . $this->attributes([
							'class'                     => 'e-sef-generate btn btn-default',
							'data-src'                  => $sefSource,
							'data-target'               => $sefTarget,
							'data-sef-generate-confirm' => LAN_WILL_OVERWRITE_SEF . ' ' . LAN_JSCONFIRM,
						]) . '>' . LAN_GENERATE . '</a></span>';
				}
				elseif(!empty($parms['counter']) && empty($parms['post']))
				{
					$parms['class'] = 'tbox e-count';
					$parms['data-char-count'] = $parms['counter'];

					if(!isset($parms['pattern']))
					{
						$parms['pattern'] = '.{0,'.$parms['counter'].'}';
					}
					$charMsg = $tp->lanVars(defset('LAN_X_CHARS_REMAINING', '[x] chars remaining'), "<span>" . $parms['counter'] . "</span>");
					$parms['post'] = "<small" . $this->attributes([
							'id'    => $this->name2id($key) . "-char-count",
							'class' => 'text-muted e-count-display',
							'style' => 'display:none',
						]) . ">" . $charMsg . "</small>";
				}


				if(!empty($parms['password'])) // password mechanism without the md5 storage. 
				{
					$ret =  vartrue($parms['pre']).$this->password($key, $value, $maxlength, $parms).vartrue($parms['post']);
				}
				else
				{
					$ret =  vartrue($parms['pre']).$this->text($key, $value, $maxlength, $parms).vartrue($parms['post']); // vartrue($parms['__options']) is limited. See 'required'=>true
				}


				if(!empty($attributes['multilan']))
				{
					$msize = vartrue($parms['size'], 'xxlarge');
					$ret = "<span class='input-group input-".$msize."'>".$ret. '</span>';
				}
				
			break;
			
			case 'tags':
				$maxlength = vartrue($parms['maxlength'], 255);
				$ret =  vartrue($parms['pre']).$this->tags($key, $value, $maxlength, $parms).vartrue($parms['post']); // vartrue($parms['__options']) is limited. See 'required'=>true
			break;

			case 'textarea':
				$text = '';
				if(!empty($parms['append']) && !empty($value)) // similar to comments - TODO TBD. a 'comment' field type may be better.
				{
					$attributes['readParms'] = 'bb=1';
					
					$text = $this->renderValue($key, $value, $attributes);					
					$text .= '<br />';
					$value = '';
					
					// Appending needs is  performed and customized using function: beforeUpdate($new_data, $old_data, $id)
				}

				if(empty($parms['size']))
				{
					$parms['size'] = 'xxlarge';
				}

				if(!empty($parms['counter']) && empty($parms['post']))
				{
					$parms['class'] = 'tbox e-count';

					if(!empty($value) && (strlen($value) > (int) $parms['counter']))
					{
						$parms['class'] .= " has-error";
					}

					if(!isset($parms['pattern']))
					{
						$parms['pattern'] = '.{0,'.$parms['counter'].'}';
					}


					$parms['data-char-count'] = $parms['counter'];
					$charMsg = $tp->lanVars(defset('LAN_X_CHARS_REMAINING', '[x] chars remaining'), "<span>" . $parms['counter'] . "</span>");
					$parms['post'] = "<small" . $this->attributes([
							'id'    => $this->name2id($key) . "-char-count",
							'class' => 'text-muted e-count-display',
							'style' => 'display:none',
						]) . ">" . $charMsg . "</small>";
				}

				$text .= vartrue($parms['pre']).$this->textarea($key, $value, vartrue($parms['rows'], 5), vartrue($parms['cols'], 40), vartrue($parms['__options'],$parms), varset($parms['counter'], false)).vartrue($parms['post']);
				$ret =  $text;
			break;

			case 'bbarea':
				$options = array('counter' => varset($parms['counter'], false)); 
				// Media = media-category owner used by media-manager. 
				$ret =  vartrue($parms['pre']).$this->bbarea($key, $value, vartrue($parms['template']), vartrue($parms['media'],'_common_image'), vartrue($parms['size'], 'medium'),$options ).vartrue($parms['post']);
			break;

			case 'video':
			case 'image': //TODO - thumb, image list shortcode, js tooltip...
				$label = varset($parms['label'], 'LAN_EDIT');

				if(!empty($parms['optArray']))
				{

					return $this->imageradio($key,$value,$parms);
				}


				unset($parms['label']);

				if($attributes['type'] === 'video')
				{
					$parms['video'] = 2; // ie. video only.
					$parms['w'] = 280;
				}


				$ret =  $this->imagepicker($key, $value, defset($label, $label), $parms);
			break;
			
			case 'images':

				$ret = '';
				$label = varset($parms['label'], 'LAN_EDIT');
				$max = varset($parms['max'],5);

				$ret .= "<div class='mediaselector-multi field-element-images'>";

				for ($i=0; $i < $max; $i++)
				{				
					$k 		= $key.'['.$i.'][path]';
					$ival 	= $value[$i]['path'];
					
					$ret .=  $this->imagepicker($k, $ival, defset($label, $label), $parms);		
				}

				$ret .= '</div>';
			break;

			/** Generic Media Pick for combinations of images, audio, video, glyphs, files, etc. Field Type = json */
			case 'media':

				$max = varset($parms['max'],1);

				$ret = "<div class='mediaselector-multi field-element-media'>";
				for ($i=0; $i < $max; $i++)
				{
					$k 		= $key.'['.$i.'][path]';
					$ival 	= isset($value[$i]) ? $value[$i]['path'] : '';

					$ret .=  $this->mediapicker($k, $ival, $parms);
				}

				$ret .= '</div>';

				return $ret;
			break;
			
			case 'files':


				$label = varset($parms['label'], 'LAN_EDIT');
				if(!empty($attributes['data']) && ($attributes['data'] === 'array' || $attributes['data'] === 'json'))
				{
					$parms['data'] = 'array';	
				}

				$ret = '<ol>';
				for ($i=0; $i < 5; $i++) 
				{				
				//	$k 		= $key.'['.$i.'][path]';
				//	$ival 	= $value[$i]['path'];
					$k 		= $key.'['.$i.']';
					$ival 	= $value[$i];
					$ret .=  '<li>'.$this->filepicker($k, $ival, defset($label, $label), $parms).'</li>';		
				}
				$ret .= '</ol>';
			break;
			
			case 'file': //TODO - thumb, image list shortcode, js tooltip...
				$label = varset($parms['label'], 'LAN_EDIT');
				unset($parms['label']);
				$ret =  $this->filepicker($key, $value, defset($label, $label), $parms);
			break;

			case 'icon':
				$label = varset($parms['label'], 'LAN_EDIT');
				$ajax = varset($parms['ajax'], true) ? true : false;
				unset($parms['label'], $parms['ajax']);
				$ret =  $this->iconpicker($key, $value, defset($label, $label), $parms, $ajax);
			break;

			case 'date': // date will show the datepicker but won't convert the value to unix. ie. string value will be saved. (or may be processed manually with beforeCreate() etc. Format may be determined by $parm. 
			case 'datestamp':
				// If hidden, value is updated regardless. eg. a 'last updated' field.
				// If not hidden, and there is a value, it is retained. eg. during the update of an existing record.
				// otherwise it is added. eg. during the creation of a new record.
				if(!empty($parms['auto']) && (($value == null) || !empty($parms['hidden'])))
				{
					$value = time();
				}
				
				if(!empty($parms['readonly'])) // different to 'attribute-readonly' since the value may be auto-generated.
				{
					$ret =  $this->renderValue($key, $value, $attributes).$this->hidden($key, $value);
				}
				elseif(!empty($parms['hidden']))
				{
					$ret =  $this->hidden($key, $value);
				}
				else
				{
					$ret =  $this->datepicker($key, $value, $parms);	
				}				
			break;

			case 'layouts': //to do - exclude param (exact match)

				$location   = varset($parms['plugin']); // empty - core
				$ilocation  = vartrue($parms['id'], $location); // omit if same as plugin name
				$where      = vartrue($parms['area'], 'front'); //default is 'front'
				$filter     = varset($parms['filter']);
				$merge      = isset($parms['merge']) ? (bool) $parms['merge'] : true;

				$layouts = e107::getLayouts($location, $ilocation, $where, $filter, $merge, false);

				return vartrue($parms['pre']).$this->select($key, $layouts,$value,$parms).vartrue($parms['post']);

			/*	if($tmp = e107::getTemplateInfo($location,$ilocation, null,true,$merge)) // read xxxx_INFO array from template file.
				{
					$opt = array();
					foreach($tmp as $k=>$inf)
					{
						$opt[$k] = $inf['title'];
					}

					return vartrue($parms['pre'],'').$this->select($key,$opt,$value,$parms).vartrue($parms['post'],'');
				}*/



/*
				if(varset($parms['default']) && !isset($layouts[0]['default']))
				{
					$layouts[0] = array('default' => $parms['default']) + $layouts[0];
				}
				$info = array();
				if($layouts[1])
				{
					foreach ($layouts[1] as $k => $info_array)
					{
						if(isset($info_array['description']))
						$info[$k] = defset($info_array['description'], $info_array['description']);
					}
				}

				*/

				//$this->selectbox($key, $layouts, $value)
			//	$ret =  (vartrue($parms['raw']) ? $layouts[0] : $this->radio_multi($key, $layouts[0], $value,array('sep'=>"<br />"), $info));
			break;

			case 'templates': //to do - exclude param (exact match)
				$templates = array();
				if(varset($parms['default']))
				{
					$templates['default'] = defset($parms['default'], $parms['default']);
				}
				$location = vartrue($parms['plugin']) ? e_PLUGIN.$parms['plugin'].'/' : e_THEME;
				$ilocation = vartrue($parms['location']);
				$tmp = e107::getFile()->get_files($location.'templates/'.$ilocation, vartrue($parms['fmask'], '_template\.php$'), vartrue($parms['omit'], 'standard'), vartrue($parms['recurse_level'], 0));
				foreach(self::sort_get_files_output($tmp) as $files)
				{
					$k = str_replace('_template.php', '', $files['fname']);
					$templates[$k] = implode(' ', array_map('ucfirst', explode('_', $k))); //TODO add LANS?
				}

				// override
				$where = vartrue($parms['area'], 'front');
				$location = vartrue($parms['plugin']) ? $parms['plugin'].'/' : '';
				$tmp = e107::getFile()->get_files(e107::getThemeInfo($where, 'rel').'templates/'.$location.$ilocation, vartrue($parms['fmask']), vartrue($parms['omit'], 'standard'), vartrue($parms['recurse_level'], 0));
				foreach(self::sort_get_files_output($tmp) as $files)
				{
					$k = str_replace('_template.php', '', $files['fname']);
					$templates[$k] = implode(' ', array_map('ucfirst', explode('_', $k))); //TODO add LANS?
				}
				$ret =  (vartrue($parms['raw']) ? $templates : $this->select($key, $templates, $value));
			break;

			case 'checkboxes':

				if(is_array($parms))
				{
					$eloptions  = vartrue($parms['__options'], array());
					if(is_string($eloptions))
					{
						parse_str($eloptions, $eloptions);
					}
					if($attributes['type'] === 'comma')
					{
						$eloptions['multiple'] = true;
					}
					unset($parms['__options']);

					if(!is_array($value) && !empty($value))
					{
						$value = explode(',',$value);
					}


					$ret =  vartrue($eloptions['pre']).$this->checkboxes($key, (array) $parms, $value, $eloptions).vartrue($eloptions['post']);


				}
				return $ret;
			break;


			case 'dropdown':
			case 'comma':


				if(!empty($attributes['writeParms']['optArray']))
				{
					$eloptions = $attributes['writeParms'];
					unset($eloptions['optArray']);
				}
				else
				{
					$eloptions  = vartrue($parms['__options'], array());
				}

				$value = (isset($eloptions['empty']) && ($value === null)) ? $eloptions['empty'] : $value;

				if(is_string($eloptions))
				{
					parse_str($eloptions, $eloptions);
				}
				if($attributes['type'] === 'comma')
				{
					$eloptions['multiple'] = true;
				}
				unset($parms['__options']);
				if(!empty($eloptions['multiple']) && !is_array($value))
				{
					$value = explode(',', $value);
				}

				// Allow Ajax API.
				if(!empty($ajaxParms))
				{
					$eloptions = array_merge_recursive($eloptions, $ajaxParms);
					$eloptions['class'] = 'e-ajax ' . varset($eloptions['class']);
				}

				$ret =  vartrue($eloptions['pre']).$this->select($key, $parms, $value, $eloptions).vartrue($eloptions['post']);
			break;

			case 'radio':
				// TODO - more options (multi-line, help)
				$eloptions  = vartrue($parms['__options'], array());
				if(is_string($eloptions))
				{
					parse_str($eloptions, $eloptions);
				}
				unset($parms['__options']);
				$ret =  vartrue($eloptions['pre']).$this->radio_multi($key, $parms, $value, $eloptions, false).vartrue($eloptions['post']);
			break;

			case 'userclass':
			case 'userclasses':


				$uc_options = vartrue($parms['classlist'], 'public,guest,nobody,member,admin,main,classes'); // defaults to 'public,guest,nobody,member,classes' (userclass handler)
				unset($parms['classlist']);


			//	$method = ($attributes['type'] == 'userclass') ? 'uc_select' : 'uc_select';
				if(vartrue($attributes['type']) === 'userclasses'){ $parms['multiple'] = true; }

				$ret =   vartrue($parms['pre']).$this->uc_select($key, $value, $uc_options, vartrue($parms, array())). vartrue($parms['post']);
			break;

			/*case 'user_name':
			case 'user_loginname':
			case 'user_login':
			case 'user_customtitle':
			case 'user_email':*/
			case 'user':
				//user_id expected
				// Just temporary solution, could be changed soon


				if(!isset($parms['__options']))
				{
					$parms['__options'] = array();
				}
				if(!is_array($parms['__options']))
				{
					parse_str($parms['__options'], $parms['__options']);
				}

				if((empty($value) || (!empty($parms['currentInit']) && empty($parms['default']))) || !empty($parms['current']) || (vartrue($parms['default']) === 'USERID')) // include current user by default.
				{
					$value = array('user_id'=>USERID, 'user_name'=>USERNAME);
					if(!empty($parms['current']))
					{
						$parms['__options']['readonly'] = true;
					}

				}





		//		if(!is_array($value))
		//		{
			//		$value = $value ? e107::getSystemUser($value, true)->getUserData() : array();// e107::user($value);
		//		}

				$colname = vartrue($parms['nameType'], 'user_name');
				$parms['__options']['name'] = $colname;

		//		if(!$value) $value = array();
		//		$uname = varset($value[$colname]);
		//		$value = varset($value['user_id'], 0);

				if(!empty($parms['limit']))
				{
					$parms['__options']['limit'] = (int) $parms['limit'];
				}

				$ret =  $this->userpicker(vartrue($parms['nameField'], $key), $value, vartrue($parms['__options']));

			//	$ret =  $this->userpicker(vartrue($parms['nameField'], $key), $key, $uname, $value, vartrue($parms['__options']));
			break;


			/**
			 * $parms['true']  - label to use for true
			 * $parms['false'] - label to use for false
			 * $parms['enabled'] - alias of $parms['true']
			 * $parms['disabled'] - alias of $parms['false']
			 * $parms['label'] - when set to 'yesno' uses yes/no instead of enabled/disabled
			 */
			case 'bool':
			case 'boolean':

				if(varset($parms['label']) === 'yesno')
				{
					$lenabled = 'LAN_YES';
					$ldisabled = 'LAN_NO';
				}
				else
				{
					$lenabled = vartrue($parms['enabled'], 'LAN_ON');
					$ldisabled = (!empty($parms['disabled']) && is_string($parms['disabled'])) ?  $parms['disabled'] : 'LAN_OFF';
				}

				if(!empty($parms['true']))
				{
					$lenabled = $parms['true'];
				}

				if(!empty($parms['false']))
				{
					$ldisabled = $parms['false'];
				}


				unset($parms['enabled'], $parms['disabled'], $parms['label']);
				$ret =  vartrue($parms['pre']).$this->radio_switch($key, $value, defset($lenabled, $lenabled), defset($ldisabled, $ldisabled),$parms).vartrue($parms['post']);
			break;

			case 'checkbox':

				$value = (isset($parms['value'])) ? $parms['value'] : $value;
				$ret =  vartrue($parms['pre']).$this->checkbox($key, 1, $value,$parms).vartrue($parms['post']);
			break;

			case 'method': // Custom Function
				$meth = (!empty($attributes['method'])) ? $attributes['method'] : $key;
				$parms['field'] = $key;

				if(strpos($meth,'::')!==false)
				{
					list($className,$meth) = explode('::', $meth);
					$cls = new $className;
				}
				else
				{
					$cls = $this;
				}

				if(method_exists($cls, $meth))
				{
					$ret =  call_user_func_array(array($cls, $meth), array($value, 'write', $parms));
				}
				else
				{
					$ret = "<div class='alert alert-warning' style='display:inline'>Method <b>".$meth."</b> not found in <b>".get_class($cls)."</b></div>";
				}

			break;

			case 'upload': //TODO - from method
				// TODO uploadfile SC is now processing uploads as well (add it to admin UI), write/readParms have to be added (see uploadfile.php parms)
				$disbut = varset($parms['disable_button'], '0');
				$ret =  $tp->parseTemplate('{UPLOADFILE=' .(vartrue($parms['path']) ? $tp->replaceConstants($parms['path']) : e_UPLOAD)."|nowarn&trigger=etrigger_uploadfiles&disable_button={$disbut}}");
			break;

			case 'hidden':

				$value = (isset($parms['value'])) ? $parms['value'] : $value;
				if(!empty($parms['show']))
				{
					$ret = ($value ?: varset($parms['empty'], $value));
				}
				else
				{
					$ret = '';
				}

				if(is_array($value) && ($attributes['data'] === 'json'))
				{
					$value = e107::serialize($value, 'json');
				}

				$ret .=  $this->hidden($key, $value, $parms);
			break;

			case 'lanlist': // installed languages
			case 'language': // all languages
				
				$options = ($attributes['type'] === 'language') ? e107::getLanguage()->getList() : e107::getLanguage()->getLanSelectArray();

				$eloptions  = vartrue($parms['__options'], array());
				if(!is_array($eloptions))
				{
					parse_str($eloptions, $eloptions);
				}
				unset($parms['__options']);
				if(vartrue($eloptions['multiple']) && !is_array($value))
				{
					$value = explode(',', $value);
				}
				$ret =  vartrue($eloptions['pre']).$this->select($key, $options, $value, $eloptions).vartrue($eloptions['post']);
			break;

			case null:
			//	Possibly used in db but should not be submitted in form. @see news_extended.
			break;

			default:// No LAN necessary, debug only. 
				$ret =  (ADMIN) ? "<span class='alert alert-error alert-danger'>".LAN_ERROR." Unknown 'type' : ".$attributes['type'] . '</span>' : $value;
			break;
		}

		if(!empty($parms['expand']))
		{
			$k = 'exp-' .$this->name2id($key);
			$text = "<a class='e-expandit e-tip' href='#{$k}'>".$parms['expand']. '</a>';
			$text .= vartrue($parms['help']) ? '<div class="field-help">'.$parms['help'].'</div>' : '';
			$text .= "<div id='{$k}' class='e-hideme'>".$ret. '</div>';
			return $text;	
		}
		else
		{
			/** @deprecated usage @see renderCreateFieldset() should be attributes['help'] */
			$ret .= vartrue($parms['help']) ? '<div class="field-help">'.$tp->toHTML($parms['help'],false,'defs').'</div>' : '';	
		}

		return $ret;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $parms
	 * @return string
	 */
	public function radioImage($name,$value,$parms)
	{
		if(!empty($parms['path']))
		{
			$parms['legacy'] = $parms['path'];
		}

		$text = '<div class="clearfix">';
		$class = varset($parms['block-class'],'col-md-2');

		foreach($parms['optArray'] as $key=>$val)
		{

			$thumbnail = $this->tp->toImage($val['thumbnail'], $parms);
		//	$active = ($key === $value) ? ' active' : '';

			$text .= "<div class='e-image-radio " . $class . "' >
							<label" . $this->attributes([
					'class' => "theme-selection",
					'title' => varset($val['title']),
				]) . "><input" . $this->attributes([
					'type'     => 'radio',
					'name'     => $name,
					'value'    => $key,
					'required' => true,
					'checked'  => $key === $value,
				]) . " />
							<div>" . $thumbnail . "</div></label>
						";

			$text .= isset($val['label']) ? "<div class='e-image-radio-label'>" . $val['label'] . "</div>" : '';
			$text .= "
			</div>";

		}

		$text .= '</div>';

		return $text;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $parms
	 * @return string
	 */
	private function imageradio($name, $value, $parms)
	{

		if(!empty($parms['path']))
		{
			$parms['legacy'] = $parms['path'];
		}

		$text = '<div class="clearfix">';

		foreach($parms['optArray'] as $key=>$val)
		{

			$thumbnail    = $this->tp->toImage($val,$parms);
			$text .= "
									<div class='col-md-2 e-image-radio' >
										<label" . $this->attributes([
						'class' => 'theme-selection',
						'title' => varset($parms['titles'][$key], $key),
					]) . "><input" . $this->attributes([
						'type'     => 'radio',
						'name'     => $name,
						'value'    => $val,
						'required' => true,
						'checked'  => ($val === $value),
					]) . " />
										<div>".$thumbnail. "</div>
										</label>";

				$text .= isset($parms['labels'][$key]) ? "<div class='e-image-radio-label'>".$parms['labels'][$key]."</div>" : '';
				$text .= "
							</div>";

		}

		$text .= '</div>';

		return $text;

	}



	/**
	 * Generic List Form, used internally by admin UI
	 * Expected options array format:
	 * <code>
	 * <?php
	 * $form_options['myplugin'] = array(
	 * 		'id' => 'myplugin', // unique string used for building element ids, REQUIRED
	 * 		'pid' => 'primary_id', // primary field name, REQUIRED
	 * 		'url' => '{e_PLUGIN}myplug/admin_config.php', // if not set, e_SELF is used
	 * 		'query' => 'mode=main&amp;action=list', // or e_QUERY if not set
	 * 		'head_query' => 'mode=main&amp;action=list', // without field, asc and from vars, REQUIRED
	 * 		'np_query' => 'mode=main&amp;action=list', // without from var, REQUIRED for next/prev functionality
	 * 		'legend' => 'Fieldset Legend', // hidden by default
	 * 		'form_pre' => '', // markup to be added before opening form element (e.g. Filter form)
	 * 		'form_post' => '', // markup to be added after closing form element
	 * 		'fields' => array(...), // see e_admin_ui::$fields
	 * 		'fieldpref' => array(...), // see e_admin_ui::$fieldpref
	 * 		'table_pre' => '', // markup to be added before opening table element
	 * 		'table_post' => '', // markup to be added after closing table element (e.g. Batch actions)
	 * 		'fieldset_pre' => '', // markup to be added before opening fieldset element
	 * 		'fieldset_post' => '', // markup to be added after closing fieldset element
	 * 		'perPage' => 15, // if 0 - no next/prev navigation
	 * 		'from' => 0, // current page, default 0
	 * 		'field' => 'field_name', //current order field name, default - primary field
	 * 		'asc' => 'desc', //current 'order by' rule, default 'asc'
	 * );
	 * $tree_models['myplugin'] = new e_admin_tree_model($data);
	 * </code>
	 * TODO - move fieldset & table generation in separate methods, needed for ajax calls
	 * @todo {@see htmlspecialchars()} at the template, not in the client code
	 * @param array $form_options
	 * @param e_admin_tree_model $tree_models
	 * @param boolean $nocontainer don't enclose form in div container
	 * @return string
	 */
	public function renderListForm($form_options, $tree_models, $nocontainer = false)
	{
		$tp = $this->tp;
		$text = '';
		$formPre = '';
		$formPost = '';

		foreach ($form_options as $fid => $options)
		{
			list($type,$plugin) = explode('-',$fid,2);

			$plugin = str_replace('-','_',$plugin);

			e107::setRegistry('core/adminUI/currentPlugin', $plugin);

			/** @var e_tree_model $tree_model */
			$tree_model = $tree_models[$fid];
			$tree = $tree_model->getTree();
			$total = $tree_model->getTotal();

		
			$amount = $options['perPage'];
			$from = vartrue($options['from'], 0);
			$field = vartrue($options['field'], $options['pid']);
			$asc = strtoupper(vartrue($options['asc'], 'asc'));
			$elid = $fid;//$options['id'];
			$query = vartrue($options['query'],e_QUERY); //  ? $options['query'] :  ;
			if(vartrue($_GET['action']) === 'list')
			{
				$query = e_QUERY; //XXX Quick fix for loss of pagination after 'delete'. 	
			}
			$url = (isset($options['url']) ? $tp->replaceConstants($options['url'], 'abs') : e_SELF);
			$formurl = $url.($query ? '?'.$query : '');
			$fields = $options['fields'];
			$current_fields = varset($options['fieldpref']) ? $options['fieldpref'] : array_keys($options['fields']);
			$legend_class = vartrue($options['legend_class'], 'e-hideme');

			

	        $text .= "
				<form method='post' action='{$formurl}' id='{$elid}-list-form'>
				<div>".$this->token(). '
					' .vartrue($options['fieldset_pre'])."
					<fieldset id='{$elid}-list'>
						<legend class='{$legend_class}'>".$options['legend']. '</legend>
						' .vartrue($options['table_pre'])."
						<table class='table adminlist table-striped' id='{$elid}-list-table'>
							".$this->colGroup($fields, $current_fields). '
							' .$this->thead($fields, $current_fields, varset($options['head_query']), varset($options['query']))."
							<tbody id='e-sort'>
			";

			if($tree)
			{
				/** @var e_model $model */
				foreach($tree as $model)
				{
				//	$model->set('x_canonical_url', 'whatever');

					e107::setRegistry('core/adminUI/currentListModel', $model);
					$text .= $this->renderTableRow($fields, $current_fields, $model->getData(), $options['pid']);
				}


				e107::setRegistry('core/adminUI/currentListModel', null);

				$text .= '</tbody>
						</table>';
			}
			else
			{
				$text .= '
							</tbody>
						</table>';

				$text .= "<div id='admin-ui-list-no-records-found' class=' alert alert-block alert-info center middle'>".LAN_NO_RECORDS_FOUND. '</div>'; // not prone to column-count issues.
			}

			
			$text .= vartrue($options['table_post']); 


			if($tree && $amount)
			{
				// New nextprev SC parameters
				$parms = 'total='.$total;
				$parms .= '&amount='.$amount;
				$parms .= '&current='.$from;
				if(deftrue('e_ADMIN_UI'))
				{
					$parms .= '&tmpl_prefix=admin';
				}
				
				// NOTE - the whole url is double encoded - reason is to not break parms query string
				// 'np_query' should be proper (urlencode'd) url query string
				$url = rawurlencode($url.'?'.(varset($options['np_query']) ? str_replace(array('&amp;', '&'), array('&', '&amp;'),  $options['np_query']).'&amp;' : '').'from=[FROM]');
				$parms .= '&url='.$url;
				//$parms = $total.",".$amount.",".$from.",".$url.'?'.($options['np_query'] ? $options['np_query'].'&amp;' : '').'from=[FROM]';
		    	//$text .= $tp->parseTemplate("{NEXTPREV={$parms}}");
				$nextprev = $tp->parseTemplate("{NEXTPREV={$parms}}");
				if ($nextprev)
				{
					$text .= "<div class='nextprev-bar'>".$nextprev. '</div>';
				}
			}

			$text .= '
					</fieldset>
					' .vartrue($options['fieldset_post']). '
				</div>
				</form>
			';

			e107::setRegistry('core/adminUI/currentPlugin');

			$formPre = vartrue($options['form_pre']);
			$formPost = vartrue($options['form_post']);
		}

		if(!$nocontainer)
		{
			$class = deftrue('e_IFRAME') ? 'e-container e-container-modal' : 'e-container';
			$text = '<div class="'.$class.'">'.$text.'</div>';
		}


		return $formPre . $text . $formPost;
	}


	/**
	 * Used with 'carousel' generates slides with X number of cells/blocks per slide.
	 * @param $cells
	 * @param int $perPage
	 * @return array
	 */
	private function slides($cells, $perPage=12)
	{
		$tmp = '';
		$s = 0;
		$slides = array();
		foreach($cells as $cell)
		{
			$tmp .= $cell;

			$s++;
			if($s == $perPage)
			{
				$slides[] = array('text'=>$tmp);
				$tmp = '';
				$s = 0;
			}
		}

		if($s != $perPage && $s != 0)
		{
			$slides[] = array('text'=>$tmp);
		}

		return $slides;


	}


	/**
	 * Render Grid-list layout.  used internally by admin UI
	 * @param $form_options
	 * @param $tree_models
	 * @param bool|false $nocontainer
	 * @return string
	 */
	public function renderGridForm($form_options, $tree_models, $nocontainer = false)
	{
		$tp = $this->tp;
		$text = '';


		// print_a($form_options);

		foreach ($form_options as $fid => $options)
		{
			$tree_model = $tree_models[$fid];
			$tree = $tree_model->getTree();
			$total = $tree_model->getTotal();

			$amount = $options['perPage'];
			$from = vartrue($options['from'], 0);
			$field = vartrue($options['field'], $options['pid']);
			$asc = strtoupper(vartrue($options['asc'], 'asc'));
			$elid = $fid;//$options['id'];
			$query = vartrue($options['query'],e_QUERY); //  ? $options['query'] :  ;
			if(vartrue($_GET['action']) === 'list')
			{
				$query = e_QUERY; //XXX Quick fix for loss of pagination after 'delete'.
			}
			$url = (isset($options['url']) ? $tp->replaceConstants($options['url'], 'abs') : e_SELF);
			$formurl = $url.($query ? '?'.$query : '');
			$fields = $options['fields'];
			$current_fields = varset($options['fieldpref']) ? $options['fieldpref'] : array_keys($options['fields']);
			$legend_class = vartrue($options['legend_class'], 'e-hideme');



	        $text .= "
				<form method='post' action='{$formurl}' id='{$elid}-list-form'>
				<div>".$this->token(). '
					' .vartrue($options['fieldset_pre']);

					$text .= "

					<fieldset id='{$elid}-list'>
						<legend class='{$legend_class}'>".$options['legend']. '</legend>
						' .vartrue($options['table_pre'])."
						<div class='row admingrid ' id='{$elid}-list-grid'>
						";


			if($tree)
			{


				if(empty($options['grid']['template']))
				{
					$template = '<div class="panel panel-default">
					<div class="e-overlay" >{IMAGE}
						<div class="e-overlay-content">
						{OPTIONS}
						</div>
					</div>
					<div class="panel-footer">{TITLE}<span class="pull-right">{CHECKBOX}</span></div>
					</div>';
				}
				else
				{
					$template = $options['grid']['template'];
				}


				$cls        = !empty($options['grid']['class']) ? $options['grid']['class'] : 'col-sm-6 col-md-4 col-lg-2';
				$pid        = $options['pid'];
				$perPage    = $options['grid']['perPage'];



				$gridFields =  $options['grid'];
				$gridFields['options'] = 'options';
				$gridFields['checkbox'] = 'checkboxes';

				unset($gridFields['class'],$gridFields['perPage'], $gridFields['carousel']);

				$cells = array();
				foreach($tree as $model)
				{
					e107::setRegistry('core/adminUI/currentListModel', $model);

					$data = $model->getData();

					$id   = $data[$pid];
					$vars = array();

					foreach($gridFields as $k=>$v)
					{
						$key = strtoupper($k);
						$fields[$v]['grid'] = true;
						$vars[$key] = $this->renderValue($v, varset($data[$v]), $fields[$v], $id);
					}

					$cells[] = "<div class='".$cls." admin-ui-grid'>". $tp->simpleParse($template,$vars). '</div>';

				}


				if($options['grid']['carousel'] === true)
				{
					$slides         = $this->slides($cells, $perPage);
					$carouselData   = $this->carousel('admin-ui-carousel',$slides, array('wrap'=>false, 'interval'=>false, 'data'=>true));

					$text .= $carouselData['start'].$carouselData['inner'].$carouselData['end'];

				}
				else
				{
					$text .= implode("\n",$cells);
				}


				e107::setRegistry('core/adminUI/currentListModel', null);

				$text .= "</div>
				<div class='clearfix'></div>";
			}
			else
			{
				$text .= '</div>';
				$text .= "<div id='admin-ui-list-no-records-found' class=' alert alert-block alert-info center middle'>".LAN_NO_RECORDS_FOUND. '</div>'; // not prone to column-count issues.
			}


			$text .= vartrue($options['table_post']);


			if($tree && $amount)
			{
				// New nextprev SC parameters
				$parms = 'total='.$total;
				$parms .= '&amount='.$amount;
				$parms .= '&current='.$from;

				if(deftrue('e_ADMIN_AREA'))
				{
					$parms .= '&tmpl_prefix=admin';
				}

				// NOTE - the whole url is double encoded - reason is to not break parms query string
				// 'np_query' should be proper (urlencode'd) url query string
				$url = rawurlencode($url.'?'.(varset($options['np_query']) ? str_replace(array('&amp;', '&'), array('&', '&amp;'),  $options['np_query']).'&amp;' : '').'from=[FROM]');
				$parms .= '&url='.$url;
				//$parms = $total.",".$amount.",".$from.",".$url.'?'.($options['np_query'] ? $options['np_query'].'&amp;' : '').'from=[FROM]';
		    	//$text .= $tp->parseTemplate("{NEXTPREV={$parms}}");
				$nextprev = $tp->parseTemplate("{NEXTPREV={$parms}}");
				if ($nextprev)
				{
					$text .= "<div class='nextprev-bar'>".$nextprev. '</div>';
				}
			}

			$text .= '
					</fieldset>
					' .vartrue($options['fieldset_post']). '
				</div>
				</form>
			';
		}
		if(!$nocontainer)
		{
			$class = deftrue('e_IFRAME') ? 'e-container e-container-modal' : 'e-container';
			$text = '<div class="'.$class.'">'.$text.'</div>';
		}
		return (vartrue($options['form_pre']).$text.vartrue($options['form_post']));
	}

	/**
	 * Generic DB Record Management Form.
	 * TODO - lans
	 * TODO - move fieldset & table generation in separate methods, needed for ajax calls
	 * Expected arrays format:
	 * <code>
	 * <?php
	 * $forms[0] = array(
	 * 		'id'  => 'myplugin',
	 * 		'url' => '{e_PLUGIN}myplug/admin_config.php', //if not set, e_SELF is used
	 * 		'query' => 'mode=main&amp;action=edit&id=1', //or e_QUERY if not set
	 * 		'tabs' => true, 	 *
	 *      'fieldsets' => array(
	 * 			'general' => array(
	 * 				'legend' => 'Fieldset Legend',
	 * 				'fields' => array(...), //see e_admin_ui::$fields
	 * 				'after_submit_options' => array('action' => 'Label'[,...]), // or true for default redirect options
	 * 				'after_submit_default' => 'action_name',
	 * 				'triggers' => 'auto', // standard create/update-cancel triggers
	 * 				//or custom trigger array in format array('sibmit' => array('Title', 'create', '1'), 'cancel') - trigger name - title, action, optional hidden value (in this case named sibmit_value)
	 * 			),
	 *
	 * 			'advanced' => array(
	 * 				'legend' => 'Fieldset Legend',
	 * 				'fields' => array(...), //see e_admin_ui::$fields
	 * 				'after_submit_options' => array('__default' => 'action_name' 'action' => 'Label'[,...]), // or true for default redirect options
	 * 				'triggers' => 'auto', // standard create/update-cancel triggers
	 * 				//or custom trigger array in format array('submit' => array('Title', 'create', '1'), 'cancel' => array('cancel', 'cancel')) - trigger name - title, action, optional hidden value (in this case named sibmit_value)
	 * 			)
	 * 		)
	 * );
	 * $models[0] = new e_admin_model($data);
	 * $models[0]->setFieldIdName('primary_id'); // you need to do it if you don't use your own admin model extension
	 * </code>
	 * @param array $forms numerical array
	 * @param array $models numerical array with values instance of e_admin_model
	 * @param boolean $nocontainer don't enclose in div container
	 * @return string
	 */
	public function renderCreateForm($forms, $models, $nocontainer = false)
	{
		$text = '';
		foreach ($forms as $fid => $form)
		{
			$model = $models[$fid];

			e107::setRegistry('core/adminUI/currentModel', $model);

			if(!is_object($model))
			{
				e107::getDebug()->log('No model object found with key ' .$fid);
			}

			$query = isset($form['query']) ? $form['query'] : e_QUERY ;
			$url = (isset($form['url']) ? $this->tp->replaceConstants($form['url'], 'abs') : e_SELF).($query ? '?'.$query : '');
			$curTab = varset($_GET['tab'], false);

			$text .= "
				<form method='post' action='".$url."' id='{$form['id']}-form' enctype='multipart/form-data' autocomplete='off' >
				<div style='display:none'><input type='text' name='lastname_74758209201093747' autocomplete='off' id='_no_autocomplete_' /></div>
				<div id='admin-ui-edit'>
				".vartrue($form['header']). '
				' .$this->token(). '
			';

			foreach ($form['fieldsets'] as $elid => $data)
			{
				$elid = $form['id'].'-'.$elid;

				if(!empty($data['tabs'])) // Tabs Present
				{
					$tabs = [];
					foreach($data['tabs'] as $tabId => $label)
					{
						$tabs[$tabId] = array('caption'=> defset($label,$label), 'text'=>$this->renderCreateFieldset($elid, $data, $model, $tabId));
					}


					$text .= $this->tabs($tabs, ['active'=>$curTab]);
				}
				else   // No Tabs Present 
				{
					$text .= $this->renderCreateFieldset($elid, $data, $model, false);
				}

				$text .= $this->renderCreateButtonsBar( $data, $model->getId());	// Create/Update Buttons etc.
				
			}

			$text .= '
			' .vartrue($form['footer']). '
			</div>
			</form>
			';
			
			// e107::js('footer-inline',"Form.focusFirstElement('{$form['id']}-form');",'prototype');
			// e107::getJs()->footerInline("Form.focusFirstElement('{$form['id']}-form');");
		}
		if(!$nocontainer)
		{
			$class = deftrue('e_IFRAME') ? 'e-container e-container-modal' : 'e-container';
			$text = '<div class="'.$class.'">'.$text.'</div>';
		}
		return $text;
	}

	/**
	 * Create form fieldset, called internal by {@link renderCreateForm())
	 *
	 * @param string $id field id
	 * @param array $fdata fieldset data
	 * @param object $model
	 * @return string | false
	 */
	public function renderCreateFieldset($id, $fdata, $model, $tab=0)
	{


		$start = vartrue($fdata['fieldset_pre'])."
			<fieldset id='{$id}-".$tab."'>
				<legend>".vartrue($fdata['legend']). '</legend>
				' .vartrue($fdata['table_pre'])."
				<table class='table adminform'>
					<colgroup>
						<col class='col-label' />
						<col class='col-control' />
					</colgroup>
					<tbody>
		";

		$text = '';

		// required fields - model definition
		$model_required     = $model->getValidationRules();
		$required_help      = false;
		$hidden_fields      = array();
		$helpTipLocation    = $this->_helptip;

		foreach($fdata['fields'] as $key => $att)
		{
			if($tab !== false && varset($att['tab'], 0) !== $tab)
			{
				continue;
			}

			// convert aliases - not supported in edit mod
			if(vartrue($att['alias']) && !$model->hasData($key))
			{
				$key = $att['field'];
			}

			if($key === 'checkboxes' || $key === 'options' || (varset($att['type']) === null) || (varset($att['type']) === false))
			{
				continue;
			}

			$parms = vartrue($att['formparms'], array());
			if(!is_array($parms))
			{
				parse_str($parms, $parms);
			}
			$label = !empty($att['note']) ? '<div class="label-note">'.deftrue($att['note'], $att['note']).'</div>' : '';


			$valPath = trim(vartrue($att['dataPath'], $key), '/');
			$keyName = $key;
			if(strpos($valPath, '/')) //not TRUE, cause string doesn't start with /
			{
				$tmp = explode('/', $valPath);
				$keyName = array_shift($tmp);
				foreach ($tmp as $path)
				{
					$keyName .= '['.$path.']';
				}
			}
			
			if(!empty($att['writeParms']) && !is_array($att['writeParms']))
			{
				 parse_str(varset($att['writeParms']), $writeParms);
			}
			else
			{
				 $writeParms = varset($att['writeParms']);
			}

			if(!empty($writeParms['sef'])) // group sef generate button with input element.
			{
				if(empty($writeParms['tdClassRight']))
				{
					$writeParms['tdClassRight'] = 'input-group';

				}
				else
				{
					$writeParms['tdClassRight'] .= ' input-group';
				}

			}
			
			if($att['type'] === 'hidden')
			{
				
				if(empty($writeParms['show'])) // hidden field and not displayed. Render element after the field-set.
				{
					$hidden_fields[] = $this->renderElement($keyName, $model->getIfPosted($valPath), $att, varset($model_required[$key], array()));

					continue;
				}
				unset($tmp);
			}

			// type null - system (special) fields
			if(!empty($att['writeParms']['visible']) || ( vartrue($att['type']) !== null && !vartrue($att['noedit']) && $key != $model->getFieldIdName()))
			{
				$required = '';
				$required_class = '';
				if(isset($model_required[$key]) || vartrue($att['validate']) || !empty($att['writeParms']['required']))
				{

					$required = $this->getRequiredString();
					$required_class = ' class="required-label" title="'.LAN_REQUIRED.'"';
					$required_help = true;

					if(!empty($att['validate']))
					{
						// override
						$model_required[$key] = array();
						$model_required[$key][] = $att['validate'] === true ? 'required' : $att['validate'];
						$model_required[$key][] = varset($att['rule']);
						$model_required[$key][] = $att['title'];
						$model_required[$key][] = varset($att['error']);
					}
				}


				if(in_array($key,$this->_field_warnings))
				{
					if(is_string($writeParms))
					{
						parse_str($writeParms,$writeParms);
					}

					$writeParms['tdClassRight'] .=   ' has-warning';

				}

				$leftCell = "<span{$required_class}>".defset(vartrue($att['title']), vartrue($att['title'])). '</span>' .$required.$label;

				$leftCell .= $this->help(varset($att['help']));
				$rightCell = $this->renderElement($keyName, $model->getIfPosted($valPath), $att, varset($model_required[$key], array()), $model->getId());


				$att['writeParms'] = $writeParms;

				$text .= $this->renderCreateFieldRow($leftCell, $rightCell, $att);
				
				
				
			}


		}
		

		if(!empty($text) || !empty($hidden_fields))
		{
			$text = $start.$text;

			$text .= '
					</tbody>
				</table>';

			$text .= vartrue($fdata['table_post']);

			$text .= implode("\n", $hidden_fields);

			$text .= '</fieldset>';

			$text .= vartrue($fdata['fieldset_post']);

			return $text;
		}


		
		return false;
		

	}



	/**
	 * Render Create/Edit Fieldset Row.
	 * @param string $label
	 * @param string $control
	 * @param array $att
	 * @return string
	 */
	public function renderCreateFieldRow($label, $control, $att = array())
	{

		$writeParms = $att['writeParms'];

		if(vartrue($att['type']) === 'bbarea' || !empty($writeParms['nolabel']))
		{
			$text = "
			<tr>
			<td colspan='2'>";

			$text .= (isset($writeParms['nolabel']) && $writeParms['nolabel'] == 2) ? '' : "<div style='padding-bottom:8px'>".$label. '</div>';
			$text .= $control. '
			</td>			
			</tr>
			';

			return $text;

		}

		$leftCellClass  = (!empty($writeParms['tdClassLeft'])) ? " class='".$writeParms['tdClassLeft']."'" : '';
		$rightCellClass = (!empty($writeParms['tdClassRight'])) ? " class='".$writeParms['tdClassRight']."'" : '';
		$trClass        = (!empty($writeParms['trClass'])) ? " class='".$writeParms['trClass']."'" : '';

		$text = "
				<tr{$trClass}>
					<td{$leftCellClass}>
						".$label."
					</td>
					<td{$rightCellClass}>
						".$control. '
					</td>
				</tr>
				';

		return $text;

	}




	/**
	 * Render the submit buttons in the Create/Edit Form.
	 * @param array $fdata - admin-ui data such as $fields, $tabs, $after_submit_options etc.
	 * @param int $id Primary ID of the record being edited (only in edit-mode)
	 * @return string
	 */
	public function renderCreateButtonsBar($fdata, $id=null) // XXX Note model and $tab removed as of v2.3
	{
		$text = "
				<div class='buttons-bar center'>
		";
					// After submit options
					$defsubmitopt = array('list' => LAN_EFORM_013, 'create' => LAN_EFORM_014, 'edit' => LAN_EFORM_015);
					$submitopt = isset($fdata['after_submit_options']) ? $fdata['after_submit_options'] : true;

					if($submitopt === true)
					{
						$submitopt = $defsubmitopt;
					}

					if($submitopt)
					{
						$selected = isset($fdata['after_submit_default']) && array_key_exists($fdata['after_submit_default'], $submitopt) ? $fdata['after_submit_default'] : 'list';
					}

					$triggers = (empty($fdata['triggers']) && $fdata['triggers'] !== false) ? 'auto' : $fdata['triggers']; // vartrue($fdata['triggers'], 'auto');

					if(is_string($triggers) && $triggers === 'auto')
					{
						$triggers = array();
						if(!empty($id))
						{
							$triggers['submit'] = array(LAN_UPDATE, 'update', $id);
						}
						else
						{
							$triggers['submit'] = array(LAN_CREATE, 'create', 0);
						}

						$triggers['cancel'] = array(LAN_CANCEL, 'cancel');
					}

					if(!empty($triggers))
					{
						foreach ($triggers as $trigger => $tdata)
						{
							$text .= ($trigger === 'submit') ? "<div class='etrigger-submit-group btn-group'>" : '';
							$text .= $this->admin_button('etrigger_'.$trigger, $tdata[1], $tdata[1], $tdata[0]);

							if($trigger === 'submit' && $submitopt)
							{

								$text .=
								'<button class="btn btn-success dropdown-toggle left" data-toggle="dropdown" data-bs-toggle="dropdown">
										<span class="caret"></span>
										</button>
										<ul class="dropdown-menu col-selection">
										<li class="dropdown-header nav-header">'.LAN_EFORM_016.'</li>
								';

								foreach($submitopt as $k=>$v)
								{
									$text .= "<li class='after-submit'>".$this->radio('__after_submit_action', $k, $selected == $k, 'label=' .$v). '</li>';
								}

								$text .= '</ul>';
							}

							$text .= ($trigger === 'submit') ? '</div>' : '';

							if(isset($tdata[2]))
							{
								$text .= $this->hidden($trigger.'_value', $tdata[2]);
							}
						}
					}

		$text .= '
				</div>
	
		';

		return $text;
	}


	/**
	 * Generic renderForm solution
	 * @param @forms
	 * @param @nocontainer
	 * @return string
	 */
	public function renderForm($forms, $nocontainer = false)
	{
		$text = '';
		foreach ($forms as $fid => $form)
		{
			$query = isset($form['query']) ? $form['query'] : e_QUERY ;
			$url = (isset($form['url']) ? $this->tp->replaceConstants($form['url'], 'abs') : e_SELF).($query ? '?'.$query : '');

			$text .= '
				' .vartrue($form['form_pre'])."
				<form method='post' action='".$url."' id='{$form['id']}-form' enctype='multipart/form-data'>
				<div>
				".vartrue($form['header']). '
				' .$this->token(). '
			';

			foreach ($form['fieldsets'] as $elid => $fieldset_data)
			{
				$elid = $form['id'].'-'.$elid;
				$text .= $this->renderFieldset($elid, $fieldset_data);
			}

			$text .= '
			' .vartrue($form['footer']). '
			</div>
			</form>
			' .vartrue($form['form_post']). '
			';
		}
		if(!$nocontainer)
		{
			$class = deftrue('e_IFRAME') ? 'e-container e-container-modal' : 'e-container';
			$text = '<div class="'.$class.'">'.$text.'</div>';
		}
		return $text;
	}
  
    /**
     * Generic renderFieldset solution, will be split to renderTable, renderCol/Row/Box etc - Still in use. 
     */
	public function renderFieldset($id, $fdata)
	{
		$colgroup = '';
		if(vartrue($fdata['table_colgroup']))
		{
			$colgroup = "
				<colgroup span='".count($fdata['table_colgroup'])."'>
			";
			foreach ($fdata['table_colgroup'] as $i => $colgr)
			{
				$colgroup .= '<col ';
				foreach ($colgr as $attr => $v)
				{
					$colgroup .= "{$attr}='{$v}'";
				}
				$colgroup .= ' />
				';
			}

			$colgroup = '</colgroup>
			';
		}
		$text = vartrue($fdata['fieldset_pre'])."
			<fieldset id='{$id}'>
				<legend>".vartrue($fdata['legend']). '</legend>
				' .vartrue($fdata['table_pre']). '

		';

		if(!empty($fdata['table_rows']) || !empty($fdata['table_body']))
		{
			$text .= "
				<table class='table adminform'>
					{$colgroup}
					<thead>
						".vartrue($fdata['table_head']). '
					</thead>
					<tbody>
			';

			if(!empty($fdata['table_rows']))
			{
				foreach($fdata['table_rows'] as $index => $row)
				{
					$text .= "
						<tr id='{$id}-{$index}'>
							$row
						</tr>
					";
				}
			}
			elseif(!empty($fdata['table_body']))
			{
				$text .= $fdata['table_body'];
			}

			if(!empty($fdata['table_note']))
			{
				$note = '<div class="form-note">'.$fdata['table_note'].'</div>';
			}

			$text .= '
						</tbody>
					</table>
					' .$note. '
					' .vartrue($fdata['table_post']). '
			';
		}

		$triggers = vartrue($fdata['triggers'], array());
		if($triggers)
		{
			$text .= "<div class='buttons-bar center'>
				".vartrue($fdata['pre_triggers']). '
			';
			foreach ($triggers as $trigger => $tdata)
			{
				if(is_string($tdata))
				{
					$text .= $tdata;
					continue;
				}
				$text .= $this->admin_button('etrigger_'.$trigger, $tdata[0], $tdata[1]);
				if(isset($tdata[2]))
				{
					$text .= $this->hidden($trigger.'_value', $tdata[2]);
				}
			}
			$text .= '</div>';
		}

		$text .= '
			</fieldset>
			' .vartrue($fdata['fieldset_post']). '
		';
		return $text;
	}
	
	/**
	 * Render Value Trigger - override to modify field/value/parameters
	 * @param string $field field name
	 * @param mixed $value field value
	 * @param array $params 'writeParams' key (see $controller->fields array)
	 * @param int $id record ID
	 */
	public function renderValueTrigger(&$field, &$value, &$params, $id)
	{
		
	}
	
	/**
	 * Render Element Trigger - override to modify field/value/parameters/validation data
	 * @param string $field field name
	 * @param mixed $value field value
	 * @param array $params 'writeParams' key (see $controller->fields array)
	 * @param array $required_data validation data
	 * @param int $id record ID
	 */
	public function renderElementTrigger(&$field, &$value, &$params, &$required_data, $id)
	{
		
	}
}

/**
 * @deprecated 2.0-beta1 Use {@see e_form} instead.
 */
class form 
{

	/**
	 * @param $form_method
	 * @param $form_action
	 * @param $form_name
	 * @param $form_target
	 * @param $form_enctype
	 * @param $form_js
	 * @return string
	 */
	public function form_open($form_method, $form_action, $form_name = '', $form_target = '', $form_enctype = '', $form_js = '')
	{
		$method = ($form_method ? "method='".$form_method."'" : '');
		$target = ($form_target ? " target='".$form_target."'" : '');
		$name = ($form_name ? " id='".$form_name."' " : " id='myform'");
		return "\n<form action='".$form_action."' ".$method.$target.$name.$form_enctype.$form_js. '><div>' .e107::getForm()->token(). '</div>';
	}

	/**
	 * @param $form_name
	 * @param $form_size
	 * @param $form_value
	 * @param $form_maxlength
	 * @param $form_class
	 * @param $form_readonly
	 * @param $form_tooltip
	 * @param $form_js
	 * @return string
	 */
	public function form_text($form_name, $form_size, $form_value, $form_maxlength = FALSE, $form_class = 'tbox form-control', $form_readonly = '', $form_tooltip = '', $form_js = '') {
		$name = ($form_name ? " id='".$form_name."' name='".$form_name."'" : '');
		$value = (isset($form_value) ? " value='".$form_value."'" : '');
		$size = ($form_size ? " size='".$form_size."'" : '');
		$maxlength = ($form_maxlength ? " maxlength='".$form_maxlength."'" : '');
		$readonly = ($form_readonly ? " readonly='readonly'" : '');
		$tooltip = ($form_tooltip ? " title='".$form_tooltip."'" : '');
		return "\n<input class='".$form_class."' type='text' ".$name.$value.$size.$maxlength.$readonly.$tooltip.$form_js. ' />';
	}

	/**
	 * @param $form_name
	 * @param $form_size
	 * @param $form_value
	 * @param $form_maxlength
	 * @param $form_class
	 * @param $form_readonly
	 * @param $form_tooltip
	 * @param $form_js
	 * @return string
	 */
	public function form_password($form_name, $form_size, $form_value, $form_maxlength = FALSE, $form_class = 'tbox form-control', $form_readonly = '', $form_tooltip = '', $form_js = '') {
		$name = ($form_name ? " id='".$form_name."' name='".$form_name."'" : '');
		$value = (isset($form_value) ? " value='".$form_value."'" : '');
		$size = ($form_size ? " size='".$form_size."'" : '');
		$maxlength = ($form_maxlength ? " maxlength='".$form_maxlength."'" : '');
		$readonly = ($form_readonly ? " readonly='readonly'" : '');
		$tooltip = ($form_tooltip ? " title='".$form_tooltip."'" : '');
		return "\n<input class='".$form_class."' type='password' ".$name.$value.$size.$maxlength.$readonly.$tooltip.$form_js. ' />';
	}

	/**
	 * @param $form_type
	 * @param $form_name
	 * @param $form_value
	 * @param $form_js
	 * @param $form_image
	 * @param $form_tooltip
	 * @return string
	 */
	public function form_button($form_type, $form_name, $form_value, $form_js = '', $form_image = '', $form_tooltip = '') {
		$name = ($form_name ? " id='".$form_name."' name='".$form_name."'" : '');
		$image = ($form_image ? " src='".$form_image."' " : '');
		$tooltip = ($form_tooltip ? " title='".$form_tooltip."' " : '');
		return "\n<input class='btn btn-default btn-secondary button' type='".$form_type."' ".$form_js." value='".$form_value."'".$name.$image.$tooltip. ' />';
	}

	/**
	 * @param $form_name
	 * @param $form_columns
	 * @param $form_rows
	 * @param $form_value
	 * @param $form_js
	 * @param $form_style
	 * @param $form_wrap
	 * @param $form_readonly
	 * @param $form_tooltip
	 * @return string
	 */
	public function form_textarea($form_name, $form_columns, $form_rows, $form_value, $form_js = '', $form_style = '', $form_wrap = '', $form_readonly = '', $form_tooltip = '') {
		$name = ($form_name ? " id='".$form_name."' name='".$form_name."'" : '');
		$readonly = ($form_readonly ? " readonly='readonly'" : '');
		$tooltip = ($form_tooltip ? " title='".$form_tooltip."'" : '');
		$wrap = ($form_wrap ? " wrap='".$form_wrap."'" : '');
		$style = ($form_style ? " style='".$form_style."'" : '');
		return "\n<textarea class='tbox form-control' cols='".$form_columns."' rows='".$form_rows."' ".$name.$form_js.$style.$wrap.$readonly.$tooltip. '>' .$form_value. '</textarea>';
	}

	/**
	 * @param $form_name
	 * @param $form_value
	 * @param $form_checked
	 * @param $form_tooltip
	 * @param $form_js
	 * @return string
	 */
	public function form_checkbox($form_name, $form_value, $form_checked = 0, $form_tooltip = '', $form_js = '') {
		$name = ($form_name ? " id='".$form_name.$form_value."' name='".$form_name."'" : '');
		$checked = ($form_checked ? " checked='checked'" : '');
		$tooltip = ($form_tooltip ? " title='".$form_tooltip."'" : '');
		return "\n<input type='checkbox' value='".$form_value."'".$name.$checked.$tooltip.$form_js. ' />';

	}

	/**
	 * @param $form_name
	 * @param $form_value
	 * @param $form_checked
	 * @param $form_tooltip
	 * @param $form_js
	 * @return string
	 */
	public function form_radio($form_name, $form_value, $form_checked = 0, $form_tooltip = '', $form_js = '') {
		$name = ($form_name ? " id='".$form_name.$form_value."' name='".$form_name."'" : '');
		$checked = ($form_checked ? " checked='checked'" : '');
		$tooltip = ($form_tooltip ? " title='".$form_tooltip."'" : '');
		return "\n<input type='radio' value='".$form_value."'".$name.$checked.$tooltip.$form_js. ' />';

	}

	/**
	 * @param $form_name
	 * @param $form_size
	 * @param $form_tooltip
	 * @param $form_js
	 * @return string
	 */
	public function form_file($form_name, $form_size, $form_tooltip = '', $form_js = '') {
		$name = ($form_name ? " id='".$form_name."' name='".$form_name."'" : '');
		$tooltip = ($form_tooltip ? " title='".$form_tooltip."'" : '');
		return "<input type='file' class='tbox' size='".$form_size."'".$name.$tooltip.$form_js. ' />';
	}

	/**
	 * @param $form_name
	 * @param $form_js
	 * @return string
	 */
	public function form_select_open($form_name, $form_js = '') {
		return "\n<select id='".$form_name."' name='".$form_name."' class='tbox form-control' ".$form_js. ' >';
	}

	/**
	 * @return string
	 */
	public function form_select_close() {
		return "\n</select>";
	}

	/**
	 * @param $form_option
	 * @param $form_selected
	 * @param $form_value
	 * @param $form_js
	 * @return string
	 */
	public function form_option($form_option, $form_selected = '', $form_value = '', $form_js = '') {
		$value = ($form_value !== FALSE ? " value='".$form_value."'" : '');
		$selected = ($form_selected ? " selected='selected'" : '');
		return "\n<option".$value.$selected. ' ' .$form_js. '>' .$form_option. '</option>';
	}

	/**
	 * @param $form_name
	 * @param $form_value
	 * @return string
	 */
	public function form_hidden($form_name, $form_value) {
		return "\n<input type='hidden' id='".$form_name."' name='".$form_name."' value='".$form_value."' />";
	}

	/**
	 * @return string
	 */
	public function form_close() {
		return "\n</form>";
	}
}

/*
Usage
echo $rs->form_open("post", e_SELF, "_blank");
echo $rs->form_text("testname", 100, "this is the value", 100, 0, "tooltip");
echo $rs->form_button("submit", "testsubmit", "SUBMIT!", "", "Click to submit");
echo $rs->form_button("reset", "testreset", "RESET!", "", "Click to reset");
echo $rs->form_textarea("textareaname", 10, 10, "Value", "overflow:hidden");
echo $rs->form_checkbox("testcheckbox", 1, 1);
echo $rs->form_checkbox("testcheckbox2", 2);
echo $rs->form_hidden("hiddenname", "hiddenvalue");
echo $rs->form_radio("testcheckbox", 1, 1);
echo $rs->form_radio("testcheckbox", 1);
echo $rs->form_file("testfile", "20");
echo $rs->form_select_open("testselect");
echo $rs->form_option("Option 1");
echo $rs->form_option("Option 2");
echo $rs->form_option("Option 3", 1, "defaultvalue");
echo $rs->form_option("Option 4");
echo $rs->form_select_close();
echo $rs->form_close();
*/



