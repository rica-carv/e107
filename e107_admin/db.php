<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2013 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * Administration - Database Utilities
 *
*/

require_once (__DIR__."/../class2.php");
$theme = e107::getPref('sitetheme');
define("EXPORT_PATH","{e_THEME}".$theme."/install/");

if(!getperms('0'))
{
	e107::redirect('admin');
	exit();
}

if(isset($_POST['back']))
{
	e107::redirect(e_SELF);
	exit();
}

e107::coreLan('db', true);

$e_sub_cat = 'database';

$frm = e107::getForm();
$mes = e107::getMessage();

if(isset($_GET['mode']))
{
    $_GET['mode'] = preg_replace('/[^\w\-]/', '', $_GET['mode']);
}

if(isset($_GET['type']))
{
    $_GET['type'] = preg_replace('/[^\w\-]/', '', $_GET['type']);
}

/*
 * Execute trigger
 */
if(isset($_POST['db_execute']))
{
	$type = key($_POST['db_execute']);
	
	if(!varset($_POST['db_execute']))
	{
		$mes->add(DBLAN_53, E_MESSAGE_WARNING);
	}
	else
	{
		$_POST[$type] = true;
	}
}





if(isset($_POST['exportXmlFile']))
{


	if(exportXmlFile($_POST['xml_prefs'],$_POST['xml_tables'],$_POST['xml_plugprefs'],$_POST['xml_themeprefs'], $_POST['package_images'], false))
	{
		$mes = e107::getMessage();
		$mes->add(LAN_CREATED, E_MESSAGE_SUCCESS);
	}

}

if(e_AJAX_REQUEST )
{

	session_write_close();
	while (ob_get_length() !== false)  // destroy all ouput buffering
	{
        ob_end_clean();
	}

	if(varset($_GET['mode']) == 'backup') //FIXME - not displaying progress until complete. Use e-progress?
	{
		echo "".DBLAN_120."<br />";
		
		$data = array();
		$data[] = e_MEDIA;
		$data[] = e_LOG;
		$data[] = e_IMPORT;
		$data[] = e_TEMP;
		$data[] = e_SYSTEM."filetypes.xml";
		$data[] = e_THEME.e107::getPref('sitetheme');
		
		$plugins = e107::getPlugin()->getOtherPlugins();
		foreach($plugins as $dir)
		{
			$data[] = e_PLUGIN.$dir;
		}
		
		$newFile = eHelper::title2sef(SITENAME)."_".date("Y-m-d-H-i-s");
			
		$zip = e107::getFile()->zip($data, e_BACKUP.$newFile.".zip");	
			
		echo DBLAN_60." <small>(".$zip.")</small><br />";
		
		echo DBLAN_61."<br />";

		$dbfile = e107::getDb()->backup('*', $newFile.".sql", array('nologs'=>1, 'droptable'=>1));
		
		echo DBLAN_62." <small>(".$dbfile.")</small>";

		e107::getLog()->addSuccess($zip." ".$dbfile, false)->save(DBLAN_63);
		
	}
	
	exit;
	
}

require_once ("auth.php");

$st = new system_tools;


/* No longer needed after XML feature added.

if(isset($_POST['backup_core']) || $_GET['mode']=='backup_core')
{
	backup_core();
	//message_handler("MESSAGE", DBLAN_1);
	$mes->add(DBLAN_1, E_MESSAGE_SUCCESS);
}

*/











require_once ("footer.php");

class system_tools
{

	public $_options = array();
	
	private $_utf8_exclude = array();


	function __construct()
	{
		global $mySQLdefaultdb;
		
		$this->_utf8_exclude = array(MPREFIX."core");

		$this->_options = array(
			"db_update"				=> array('diz'=>DBLAN_15, 'label'=>DBLAN_16, 'icon'=>'fas-angle-double-up.glyph'),
			"verify_sql"			=> array('diz'=>DBLAN_4, 'label'=>DBLAN_5, 'icon'=>'fas-database.glyph'),
			'optimize_sql'			=> array('diz'=>DBLAN_6, 'label'=> DBLAN_7, 'icon'=>'fas-wrench.glyph'),
			'plugin_scan'			=> array('diz'=>DBLAN_28, 'label'=> DBLAN_29, 'icon'=>'fas-plug.glyph'),
			'pref_editor'			=> array('diz'=>DBLAN_19, 'label'=> DBLAN_20, 'icon'=>'fas-edit.glyph'),
		//	'backup_core'			=> array('diz'=>DBLAN_8, 'label'=> DBLAN_9),
		//	'verify_sql_record'		=> array('diz'=>DBLAN_35, 'label'=> DBLAN_36),
			'importForm'			=> array('diz'=>DBLAN_59, 'label'=> DBLAN_59, 'icon'=>'fas-file-import.glyph'),
			'exportForm'			=> array('diz'=>DBLAN_58, 'label'=> DBLAN_58, 'icon'=>'fas-file-export.glyph'),
			'sc_override_scan'		=> array('diz'=>DBLAN_55, 'label'=> DBLAN_56, 'icon'=>'fas-search.glyph'),
			'convert_to_utf8'		=> array('diz'=>DBLAN_64,'label'=>DBLAN_65, 'icon'=>'fas-language.glyph'),
			'correct_perms'			=> array('diz'=>DBLAN_66,'label'=>DBLAN_67, 'icon'=>'fas-folder.glyph'),
			'backup'				=> array('diz'=>DBLAN_68,'label'=>DBLAN_69, 'icon'=>'fas-archive.glyph')
		);
		
		if(deftrue('e_DEVELOPER'))
		{
		//	$this->_options['multisite'] = array('diz'=>"<span class='label label-warning'>".DBLAN_114."</span>", 'label'=> 'Multi-Site' , 'icon'=>'fas-clone.glyph');
			$this->_options['github'] = array('diz'=>"<span class='label label-warning'>".DBLAN_114."</span> ".DBLAN_115."", 'label'=> DBLAN_112, 'icon'=>'fab-github.glyph' );
		}



		$this->_options = multiarray_sort($this->_options, 'label');
				
		if(isset($_POST['delplug']))
		{
			$this->delete_plugin_entry(); // $_POST['pref_type']
		}

		if(isset($_POST['upload']))
		{
			$this->importXmlFile();
		}

		if(isset($_POST['delpref']) || (isset($_POST['delpref_checked']) && isset($_POST['delpref2'])))
		{
			$this->del_pref_val($_POST['pref_type']);
		}
		
		if(isset($_POST['verify_sql']) || !empty($_POST['verify_table']) || varset($_GET['mode']) =='verify_sql')
		{
			e107::css('inline', "
				td.darker { background-color: rgba(0,0,0,0.5) } 
				label.form-check { cursor: pointer }
			");
			e107::js('footer-inline', "
			
				$('#core-db-verify-sql-tables input[type=\"checkbox\"]').click(function(evt){
					
				if(this.checked)
				{
					$(this).closest('td').addClass('darker',  50 );	
	            }
				else
				{
					$(this).closest('td').removeClass('darker', 300 );	
				}	
			
		});
			
			
			");


			e107::getCache()->clear('Dbverify',true);
			require_once(e_HANDLER."db_verify_class.php");
			$dbv = new db_verify;
			$dbv->backUrl = e_SELF."?mode=verify_sql";
			$dbv->verify();

			//echo e107::getMessage()->render();
			return;
		}
		
		// ----------------- Processes ------------------
		
	//	if(isset($_POST['verify_sql_record']) || varset($_GET['mode'])=='verify_sql_record' || isset($_POST['check_verify_sql_record']) || isset($_POST['delete_verify_sql_record']))
	//	{
		
			 //$this->verify_sql_record(); // - currently performed in db_verify_class.php
	//	}

		if(isset($_POST['importForm']) ||  $_GET['mode']=='importForm')
		{
			$this->importForm();
		}
		
		if(isset($_POST['db_update']) || varset($_GET['mode'])=='db_update') // Requires further testing. 
		{
		//	header("location: ".e_ADMIN."e107_update.php");
			$dbupdate = null;
			require_once(e_ADMIN."update_routines.php");
			new e107Update($dbupdate);
			return;
		}
		
		if(isset($_POST['convert_to_utf8']) ||  $_GET['mode'] =='convert_to_utf8')
		{
			$this->convertUTF8Form();
		}

		if(isset($_POST['exportForm']) ||  $_GET['mode']=='exportForm')
		{
			$this->exportXmlForm();
		}

		if(isset($_POST['optimize_sql']) || $_GET['mode']=='optimize_sql')
		{
			$this->optimizesql($mySQLdefaultdb);
		}

		if(isset($_POST['pref_editor']) || $_GET['mode']=='pref_editor' || isset($_POST['delpref']) || isset($_POST['delpref_checked']))
		{
			$type = isset($_GET['type']) ? $_GET['type'] : "core";
			$this->pref_editor($type);
		}

		if(isset($_POST['sc_override_scan']) || $_GET['mode']=='sc_override_scan')
		{
			$this->scan_override();
		}

		if(isset($_POST['plugin_scan']) || e_QUERY == "plugin" || isset($_POST['delplug']) || $_GET['mode']=='plugin_scan')
		{
			$this->plugin_viewscan('refresh');
		}
		
		if(!empty($_POST['create_multisite']))
		{
			$this->multiSiteProcess();	
		}	

		if(!empty($_POST['perform_utf8_convert']))
		{
			$this->perform_utf8_convert();
			return;
		}

		if(!empty($_POST['githubSyncProcess']))
		{
			$this->githubSyncProcess();
			return;
		}



		// --------------------- Modes --------------------------------.


		if(varset($_GET['mode'])=='correct_perms')
		{
			$this->correct_perms();	
			return;
		}
		
		if(varset($_GET['mode'])=='multisite')
		{
			$this->multiSite();	
			return;
		}

		if(varset($_GET['mode']) == 'github')
		{
			$this->githubSync();
		}
		
		if(varset($_GET['mode']) == 'backup')
		{
			$this->backup();
			return;
		}

		if(!vartrue($_GET['mode']) && !isset($_POST['db_execute']))
		{
			$this->render_options();
		}



	}


	// Developer Mode ONly.. No LANS required. 
	private function githubSync()
	{
		$frm = e107::getForm();
		$mes = e107::getMessage();
		$pref = e107::pref();

		if(empty($pref['developer']))
		{
			e107::getMessage()->addError("Developer mode has to be enabled in order to use this functionality!");
			e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_112, $mes->render());
			return;
		}

		// Check for minimum required PHP version, and display warning instead of sync button to avoid broken functionality after syncing
		// MIN_PHP_VERSION constant only defined in install.php, thus hardcoded here
		$min_php_version = '5.6'; 
		
		if(version_compare(PHP_VERSION, $min_php_version, "<"))
		{
			$mes->addWarning("The minimum required PHP version is <strong>".$min_php_version."</strong>. You are using PHP version <strong>".PHP_VERSION."</strong>. <br /> Syncing with Github has been disabled to avoid broken fuctionality."); // No need to translate, developer mode only
		}
		else 
		{
			$message = $frm->open('githubSync');
			$message .= "<p>".DBLAN_116." <b>".e_SYSTEM."temp</b> ".DBLAN_117." </p>";
			$message .= $frm->button('githubSyncProcess',1,'delete', DBLAN_113);
			$message .= $frm->close();
			
			$mes->addInfo($message);
		} 
		

		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_112, $mes->render());
	}



	// Developer Mode ONly.. No LANS.
	private function githubSyncProcess()
	{
		$result = e107::getFile()->unzipGithubArchive('core');

		if($result === false)
		{
			e107::getMessage()->addError( DBLAN_118 );
			return null;
		}

		$success = $result['success'];
		$error = $result['error'];

	//		$message = e107::getParser()->lanVars(DBLAN_121, array('x'=>$oldPath, 'y'=>$newPath));

		if(!empty($success))
		{
			e107::getMessage()->addSuccess(print_a($success,true));
		}

		if(!empty($skipped))
		{
			e107::getMessage()->setTitle("Skipped",E_MESSAGE_INFO)->addInfo(print_a($skipped,true));
		}

		if(!empty($error))
		{
			//e107::getMessage()->addError(print_a($error,true));
			e107::getMessage()->setTitle("Ignored",E_MESSAGE_WARNING)->addWarning(print_a($error,true));
		}

		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_112, e107::getMessage()->render());

		e107::getCache()->clearAll('system');
		e107::getSession()->clear('core-update-status'); // true when the update alert should be displayed.
		e107::getSession()->clear('core-update-checked'); // if the update check has been performed already or not.

	}





	private function backup()
	{
			
		$mes = e107::getMessage();
		
		$message = DBLAN_70;
		$message .= "<br /><a class='e-ajax btn btn-success' data-loading-text='".DBLAN_71."' href='#backupstatus' data-src='".e_SELF."?mode=backup' >".LAN_CREATE."</a>";
		
		
		$mes->addInfo($message);
		
		$text = "<div id='backupstatus' style='margin-top:20px'></div>";
		
		
		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_119, $mes->render().$text);		
	}



	/**
	 * Correct Folder and File permissions. 
	 */
	function correct_perms()
	{
		$mes = e107::getMessage();
		$fl = e107::getFile();
		ob_start();
		$fl->chmod(e_BASE);
		$fl->chmod(e_BASE."cron.php",0755);
		$errors = ob_get_clean();
		
		if($errors !='')
		{
			$mes->addError($errors);		
		}
		else
		{
			$mes->addSuccess(DBLAN_72);
		}
		
		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_73, $mes->render());
		
	}
	
	private function multiSiteProcess()
	{	
		$sql 		= e107::getDb('new');
		$mes 		= e107::getMessage();
		
		$user 		= $_POST['name'];
		$pass 		= $_POST['password'];
		$server 	= e107::getMySQLConfig('server'); // $_POST['server'];
		$database 	= $_POST['db'];
		$prefix		= $_POST['prefix'];
			
		if($connect = $sql->connect($server,$user, $pass, true))
		{
			$mes->addSuccess(DBLAN_74);
			
			if(vartrue($_POST['createdb']))
			{
			
				if($sql->gen("CREATE DATABASE ".$database." CHARACTER SET `utf8mb4`"))
				{
					$mes->addSuccess(DBLAN_75);
					
				//	$sql->gen("CREATE USER ".$user."@'".$server."' IDENTIFIED BY '".$pass."';");
					$sql->gen("GRANT ALL ON `".$database."`.* TO ".$user."@'".$server."';");
					$sql->gen("FLUSH PRIVILEGES;");		
				}
				else
				{
					$mes->addError(DBLAN_75);
					return;
				}
			}
			
			if(!$sql->database($database))
			{
				$mes->addError(DBLAN_76);
			}
					
			$mes->addSuccess(DBLAN_76);
					
			if($this->multiSiteCreateTables($sql, $prefix))
			{
				$coreConfig = e_CORE. "xml/default_install.xml";		
				$ret = e107::getXml()->e107Import($coreConfig, 'add', true, false, $sql); // Add core pref values
				$mes->addInfo(print_a($ret,true));
			}	
				
		}
		else
		{
			$mes->addSuccess(DBLAN_74);
		}
		
		if($error = $sql->getLastErrorText())
		{
			$mes->addError($error);
		}
			
		//	print_a($_POST);

		
	}

	/**
	 * @param object $sql
	 * @param $prefix
	 * @return bool
	 */
	private function multiSiteCreateTables($sql, $prefix)
	{
		$mes = e107::getMessage();
		
		$sql_data = file_get_contents(e_CORE."sql/core_sql.php");
		$sql_data = preg_replace("#\/\*.*?\*\/#mis", '', $sql_data);		// Strip comments

		if (!$sql_data)
		{
			$mes->addError(DBLAN_77);
		}

		preg_match_all("/create(.*?)(?:myisam|innodb);/si", $sql_data, $result );


		$sql->gen('SET NAMES `utf8mb4`');

		foreach ($result[0] as $sql_table)
		{
			$sql_table = preg_replace("/create table\s/si", "CREATE TABLE ".$prefix, $sql_table);

			if (!$sql->gen($sql_table))
			{
				$mes->addError($sql->getLastErrorText());
				return false;
			}
/*			else
			{
				// $mes->addDebug($sql_table);
			}
*/
		}	
		
		return true;
	}


	/**
	 * @deprecated
	 * @return false
	 */
	private function multiSite()
	{

	//	if(!deftrue('e_DEVELOPER'))
		{
			return false;
		}

		$mes = e107::getMessage();
		$frm = e107::getForm();
		
		e107::lan('core','installer');

		// Leave here until no longer experimental. - Should be placed inside lan_db.php and LANS renamed.
		define('LANINS_130', "Parked Domain");
		define('LANINS_131', "The parked domain which will become a new e107 website.");
		define('LANINS_132', "mydomain.com");
		define('LANINS_133', "This will create a fresh installation of e107 at the domain you specify. Using your server administration software (e.g. cPanel) - park your other domain on top of [x]");


		$config = e107::getMySQLConfig(); // prefix|server|user|password|defaultdb
		
		if(!isset($POST['create_multisite']))
		{
			$info = str_replace('[x]', e_DOMAIN, LANINS_133);
			$mes->addInfo($info);
		}
		
		$text = $frm->open('multisite')."
			<table class='table table-striped' >
			<tr>
					<td><label for='server'>".LANINS_130."</label></td>
					<td>
						<input class='tbox' type='text' placeholder='".LANINS_132."' id='domain' name='domain' autofocus size='40' value='' maxlength='100' required='required' />
						<span class='field-help'>".LANINS_131."</span>
					</td>
				</tr>
				";
			/*		
				$text .= "
				<tr>
					<td><label for='server'>".LANINS_024."</label></td>
					<td>
						<input class='tbox' type='text' id='server' name='server' autofocus size='40' value='localhost' maxlength='100' required='required' />
						<span class='field-help'>".LANINS_030."</span>
					</td>
				</tr>";
			*/
				$text .= "
				
				<tr>
					<td><label for='name'>".LANINS_025."</label></td>
					<td>
						<input class='tbox' type='text' name='name' id='name' size='40' value='".e107::getMySQLConfig('user')."' maxlength='100' required='required' />
						<span class='field-help'>".LANINS_031."</span>
					</td>
				</tr>
				
				<tr>
					<td><label for='password'>".LANINS_026."</label></td>
					<td>
						<input class='tbox' type='password' name='password' size='40' id='password' value='".e107::getMySQLConfig('password')."' maxlength='100'  />
						<span class='field-help'>".LANINS_032."</span>
					</td>
				</tr>
				";
			
				$text .= "
				<tr>
					<td><label for='db'>".LANINS_027."</label></td>
					<td class='input-inline'>
						<input type='text' name='db' size='20' id='db' value='' maxlength='100' required='required' />
						<label class='checkbox inline'><input type='checkbox' name='createdb' value='1' />".LANINS_028."</label>
						<span class='field-help'>".LANINS_033."</span>
					</td>
				</tr>";

			
				
				$text .= "
				
				<tr>
					<td><label for='prefix'>".LANINS_029."</label></td>
					<td>
						<input type='text' name='prefix' size='20' id='prefix' value='e107_'  pattern='[a-z0-9]*_$' maxlength='100' required='required' />
						<span class='field-help'>".LANINS_034."</span>
					</td>
				</tr>
	
	
			\n";	
		
		$text .= "
			
				<tr>
					<td><label for='u_name'>".LANINS_072."</label></td>
					<td>
						<input class='tbox' type='text' autofocus name='u_name' id='u_name' placeholder='admin' size='30' required='required' value='".USERNAME."' maxlength='60' />
						<span class='field-help'>".LANINS_073."</span>
					</td>
				</tr>
				
				<tr>
					<td><label for='d_name'>".LANINS_074."</label></td>
					<td>
						<input class='tbox' type='text' name='d_name' id='d_name' size='30' placeholder='Administrator'  value='".USERNAME."' maxlength='60' />
						<span class='field-help'>".LANINS_123."</span>
					</td>
				</tr>
				
				<tr>
					<td><label for='pass1'>".LANINS_076."</label></td>
					<td>
						<input type='password' name='pass1' size='30' id='pass1' value='' maxlength='60' required='required' />
						<span class='field-help'>".LANINS_124."</span>
					</td>
				</tr>
				
				<tr>
					<td><label for='pass2'>".LANINS_078."</label></td>
					<td>
						<input type='password' name='pass2' size='30' id='pass2' value='' maxlength='60' required='required' />
						<span class='field-help'>".LANINS_079."</span>
					</td>
				</tr>
				
				<tr>
					<td><label for='email'>".LANINS_080."</label></td>
					<td>
						<input type='text' name='email' size='30' id='email' required='required' placeholder='admin@mysite.com' value='".USEREMAIL."' maxlength='100' />
					<span class='field-help'>".LANINS_081."</span>
					</td>
				</tr>
			</table>
			<div class='buttons-bar text-center'>
			".$frm->admin_button('create_multisite',1,'submit','Create New Site')."
			</div>
			\n";
		
		$text .= $frm->close();
		
			
		e107::getRender()->tablerender(DBLAN_10.SEP."Multi-Site".SEP.$config['mySQLdefaultdb'], $mes->render().$text);
		
	}


	private function convertUTF8Form()
	{
		$mes 	= e107::getMessage();
		$frm 	= e107::getForm();
		$config = e107::getMySQLConfig();
		$sql 	= e107::getDb();
		$tp = e107::getParser();
		
		$sql->gen('SHOW TABLE STATUS WHERE Name LIKE "'.$config['mySQLprefix'].'%" ');
		
		
		$text = "<table class='table adminlist'>
							<colgroup>
								<col style='width: auto' />
								<col style='width: auto' />
								<col style='width: auto' />
								<col style='width: auto' />
							</colgroup>
							<thead>
								<tr>
									
									<th>".DBLAN_78."</th>
									<th>".DBLAN_79."</th>
									<th>".DBLAN_80."</th>
									<th>".DBLAN_81."</th>
								</tr>
							</thead>
							<tbody>";
		
		
		
		$invalidCollations = false;	
		while($row = $sql->fetch())
		{
				if(in_array($row['Name'],$this->_utf8_exclude))
				{
					continue;
				}
					
			
				$text .= "<tr>
					<td>".$row['Name']."</td>
					<td>".$row['Engine']."</td>
					<td>".$row['Collation']."</td>
					<td>".(($row['Collation'] == 'utf8mb4_general_ci') ? defset('ADMIN_TRUE_ICON') : defset('ADMIN_FALSE_ICON'))."</td>
					</tr>";
			//	 print_a($row);
				
				if($row['Collation'] != 'utf8mb4_general_ci')
				{
					$invalidCollations = true;	
				}

		}
		
		$text .= "</tbody></table>";


		if($invalidCollations == true)
		{
			$message = str_replace('[database]', $config['mySQLdefaultdb'], DBLAN_82);
			$message .= '<br/>';
			$message .= DBLAN_83;
			$message .= '<br/>';
			$message .= '<br/>';
			$message .= DBLAN_84;
			$message .= '<ul>';
			$message .= '<li>'.DBLAN_85.'</li>';
			$message .= '<li>'.DBLAN_86.'</li>';
			$message .= '<li>'.DBLAN_87.'</li>';
			$message .= '<li>'.DBLAN_88.'</li>';
			$message .= '</ul>';

			$mes->add($tp->toHTML($message,true), E_MESSAGE_WARNING);
	
			$text .= "
				<form method='post' action='".e_SELF."' id='linkform'>
					<fieldset id='core-db-utf8-convert'>
						<legend class='e-hideme'>".DBLAN_89."</legend>
						<div class='buttons-bar center'>
							".$frm->admin_button('perform_utf8_convert', DBLAN_90,false,DBLAN_90,'class=btn-success&data-loading-text='.DBLAN_91)."
						</div>
					</fieldset>
				</form>";
			
		}
		else 
		{
			$mes->addSuccess(DBLAN_92);
		}


		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_65.SEP.$config['mySQLdefaultdb'], $mes->render().$text);

	}

	private function perform_utf8_convert()
	{
		$config = e107::getMySQLConfig();
		$dbtable = $config['mySQLdefaultdb'];

		//TODO Add a check to be sure the database is not already utf-8.
		// yep, needs more methods - possibly a class in e107_handler

		$sql = e107::getDb('utf8-convert');
		$mes = e107::getMessage();

		$ERROR = FALSE;

	//	if(!$sql->gen("USE information_schema;"))
	//	{
	//		$mes->add("Couldn't read information_schema", E_MESSAGE_ERROR);
	//		return;
	//	}
		
	
		$queries = array();
		$queries[] = $this->getQueries("SELECT CONCAT('ALTER TABLE `', table_name, '` MODIFY ', column_name, ' ', REPLACE(column_type, 'char', 'binary'), ';') FROM information_schema.columns WHERE TABLE_SCHEMA = '".$dbtable."' AND TABLE_NAME LIKE '".$config['mySQLprefix']."%' AND  COLLATION_NAME != 'utf8mb4_general_ci'  and data_type LIKE '%char%';");
		$queries[] = $this->getQueries("SELECT CONCAT('ALTER TABLE `', table_name, '` MODIFY ', column_name, ' ', REPLACE(column_type, 'text', 'blob'), ';') FROM information_schema.columns WHERE TABLE_SCHEMA = '".$dbtable."' AND TABLE_NAME LIKE '".$config['mySQLprefix']."%' AND  COLLATION_NAME != 'utf8mb4_general_ci' and data_type LIKE '%text%';");

		$queries2 = array();
		$queries2[] = $this->getQueries("SELECT CONCAT('ALTER TABLE `', table_name, '` MODIFY ', column_name, ' ', column_type, ' CHARACTER SET utf8mb4;') FROM information_schema.columns WHERE TABLE_SCHEMA ='".$dbtable."' AND TABLE_NAME LIKE '".$config['mySQLprefix']."%'  AND COLLATION_NAME != 'utf8mb4_general_ci' and data_type LIKE '%char%';");
		$queries2[] = $this->getQueries("SELECT CONCAT('ALTER TABLE `', table_name, '` MODIFY ', column_name, ' ', column_type, ' CHARACTER SET utf8mb4;') FROM information_schema.columns WHERE TABLE_SCHEMA = '".$dbtable."' AND TABLE_NAME LIKE '".$config['mySQLprefix']."%' AND  COLLATION_NAME != 'utf8mb4_general_ci' and data_type LIKE '%text%';");


	//	$sql->gen("USE ".$dbtable);
		
		
	//	print_a($queries2);
	//	echo $mes->render();
	//	return;

	
		// Convert Text tables to Binary. 
		foreach($queries as $qry)
		{
					
			foreach($qry as $q)
			{
				if(!$sql->db_Query($q))
				{
					$mes->addError($q);
					$ERROR = TRUE;
				}
				else
				{
					$mes->addDebug($q);	
				}
			}
		}

		//------------

		// Convert Table Fields to utf8
		$sql2 = e107::getDb('sql2');
		
		$sql->gen('SHOW TABLE STATUS WHERE Collation != "utf8mb4_general_ci" ');
		while ($row = $sql->fetch())
		{
   			$table = $row['Name'];
   			
			if(in_array($row['Name'], $this->_utf8_exclude))
			{
				continue;
			}
			
			
			$tab_query = "ALTER TABLE ".$table."  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; ";

			//echo "TABQRT= ".$tab_query;

			if(!$sql2->db_Query($tab_query))
			{
				$mes->addError($tab_query);
				$ERROR = TRUE;
			}
			else
			{
				$mes->addDebug($tab_query);	
			}
		}

		// ---------------
		// Convert Table Fields back to Text/varchar etc. 
		foreach($queries2 as $qry)
		{
			foreach($qry as $q)
			{
				if(!$sql->db_Query($q))
				{
					$mes->addError($q);
					$ERROR = TRUE;
				}
				else
				{
					$mes->addDebug($q);	
				}
			}
		}

		//------------

		$lastQry = "ALTER DATABASE `".$dbtable."` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";

		if(!$sql->db_Query($lastQry))
		{
			$mes->add($lastQry, E_MESSAGE_ERROR);
		}
		elseif($ERROR != TRUE)
		{
			$message = DBLAN_93;

			$mes->add($message, E_MESSAGE_SUCCESS);
			$mes->addSuccess(DBLAN_94);
			$mes->addSuccess('$mySQLcharset   = "utf8mb4";');
			
		}

		echo $mes->render();
	}

	function getQueries($query)
	{
		
		$mes = e107::getMessage();
		$sql = e107::getDb('utf8-convert');

		$qry = [];
		
		if($sql->gen($query))
		{
			while ($row = $sql->fetch('num'))
			{
	   			 $qry[] = $row[0];
			}
		}
		else 
		{
			$mes->addError($query);	
		}

		return $qry;
		
		
		/*
		if(!$result = mysql_query($query))
		{
			$mes->addError("Query Failed: ".$query);
			return;
		}
		while ($row = mysql_fetch_array($result, 'num'))
		{
   			 $qry[] = $row[0];
		}

		return $qry;
		 * */
	}


	/**
	 * Delete selected preferences.
	 * @return null
	 */
	private function del_pref_val($mode='core')
	{
		$mes = e107::getMessage();

		$deleted_list = "";

		$config = $this->getPrefConfig($mode);

		// Single Pref Deletion	using button
		if(varset($_POST['delpref']))
		{
			$delpref = key($_POST['delpref']);
			if($config->remove($delpref))
			{
				$deleted_list .= "<li>".$delpref."</li>";
			}
		}

		// Multiple Pref deletion using checkboxes
		if(varset($_POST['delpref2']))
		{
			foreach($_POST['delpref2'] as $k => $v)
			{
				if($config->remove($k))
				{
					$deleted_list .= "<li>".$k."</li>";
				}
			}
		}

		if($deleted_list && $config->save())
		{
			$mes->add(LAN_DELETED."<ul>".$deleted_list."</ul>");
			e107::getCache()->clear();
		}

		return null;

	}

	private function delete_plugin_entry()
	{

		$mes = e107::getMessage();
		$sql = e107::getDb();

		$del = array_keys($_POST['delplug']);
		if($sql->delete("plugin", "plugin_id='".intval($del[0])."'"))
		{
			$mes->add(LAN_DELETED, E_MESSAGE_SUCCESS);
		}
		else
		{
			$mes->add(LAN_DELETED_FAILED, E_MESSAGE_WARNING);
		}

	}


	/**
	 * Render Options
	 * @return null
	 */
	private function render_options()
	{

		$mes = e107::getMessage();
		$tp = e107::getParser();
		
		$text = "
		<form method='post' action='".e_SELF."' id='core-db-main-form'>
			<fieldset id='core-db-plugin-scan'>
			<legend class='e-hideme'>".DBLAN_10."</legend>
				<table class='table table-striped adminlist'>
				<colgroup>
					<col style='width: 60%' />
					<col style='width: 40%' />
				</colgroup>
				<tbody>";
				
		$text = "<div class='row'>";


		foreach($this->_options as $key=>$val)
		{
			
			$text .= "<div class='col-md-6 col-lg-4' style='height:80px; padding-bottom:10px'>
			<a class='btn btn-default btn-secondary btn-lg btn-large pull-left' style='margin-right:10px' href='".e_SELF."?mode=".$key."' title=\"".$val['label']."\">".$tp->toGlyph($val['icon'], ['fw'=>true, 'size'=>'2x'])."</a>
			<h4 style='margin-bottom:3px'><a href='".e_SELF."?mode=".$key."' title=\"".$val['label']."\">".$val['label']."</a></h4><small>".$val['diz']."</small>
			</div>";
		
		}
/*
		$text .= "

				</tbody>
				</table>";
		// $text .= "<div class='buttons-bar center'>
					// ".$frm->admin_button('trigger_db_execute', DBLAN_51, 'execute')."
				// </div>";
		$text .= "
			</fieldset>
		</form>
		";
*/
		e107::getRender()->tablerender(DBLAN_10, $mes->render().$text);

		return null;
	}


	/**
	 * Import XML Form
	 * @return null
	 */
	private function importForm()
	{
		 // Get largest allowable file upload

		$frm = e107::getSingleton('e_form');
		$mes = e107::getMessage();

				require_once(e_HANDLER.'upload_handler.php');
				  $max_file_size = get_user_max_upload();

				  $text = "
					<form enctype='multipart/form-data' method='post' action='".e_SELF."?mode=".$_GET['mode']."'>
	                <table class='table adminform'>
	                	<colgroup>
	                		<col class='col-label' />
	                		<col class='col-control' />
	                	</colgroup>


					<tbody>
					<tr>
					<td>".LAN_UPLOAD."</td>
					<td>
						<input type='hidden' name='MAX_FILE_SIZE' value='{$max_file_size}' />
						<input type='hidden' name='ac' value='".md5(ADMINPWCHANGE)."' />
						<input class='tbox' type='file' name='file_userfile[]' accept='text/xml' size='50' />
					</td>
					</tr>
					</tbody>
					</table>

					<div class='center buttons-bar'>";
	                $text .= $frm->admin_button('upload', LAN_UPLOAD, 'submit', LAN_UPLOAD);

					$text .= "
					</div>

					</form>\n";


		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_59, $mes->render().$text);

		return null;
	}

	/**
	 * Export XML Dump
	 * @return null
	 */
	private function exportXmlForm()
	{
		$mes = e107::getMessage();
		$frm = e107::getSingleton('e_form');

		$text = "<form method='post' action='".e_SELF."?".e_QUERY."' id='core-db-export-form'>
			<fieldset id='core-db-export'>
			<legend class='e-hideme'>".DBLAN_95."</legend>
				<table class='table adminlist'>
				<colgroup>
					<col style='width: 80%' />
					<col style='width: 20%' />
				</colgroup>
				<thead>
				<tr>
					<th class='form-inline'>".$frm->checkbox_toggle('check-all-verify', 'xml_prefs')." &nbsp;".LAN_PREFS."</th>
					<th class='right'>".DBLAN_98."</th>

				</tr>
				</thead>
				<tbody>

				";

					$pref_types  = e107::getConfig()->aliases;
					unset($pref_types['core_old'], $pref_types['core_backup']);
			//		$exclusions = array('core_old'=>1,'core_backup'=>1);
				//	$filteredprefs = array_diff($pref_types,$exclusions);

					foreach($pref_types as $key=>$description)
					{
						$data = e107::getConfig($key)->getPref();

						$rows = count($data);

						$checked = (vartrue($_POST['xml_prefs'][$key]) == $key) ? 1: 0;

						$text .= "<tr>
							<td>
								".$frm->checkbox("xml_prefs[".$key."]", $key, $checked, array('label'=>LAN_PREFS.": ".$key))."
							</td>
							<td class='text-right'>".intval($rows)."</td>

							</tr>";

					}


					// Plugin Preferences ----------------------------
					$pluglist = e107::pref('core','plug_installed');

					$text .= "</tbody><thead><tr>
					<th class='form-inline'>".$frm->checkbox_toggle('check-all-verify', 'xml_plugprefs')." &nbsp;Plugin ".LAN_PREFS."</th>
					<th class='right'>".DBLAN_98."</th>

					</tr></thead><tbody>";

					ksort($pluglist);

					foreach($pluglist as $plug=>$ver)
					{
						$data = e107::getPlugConfig($plug)->getPref();

						$key = $plug;

						$checked = false;

						if(!empty($data))
						{
							$rows = count($data);

							$text .= "<tr>
							<td>
								".$frm->checkbox("xml_plugprefs[".$key."]",$key, $checked, array('label'=>LAN_PREFS.": ".$key))."
							</td>
							<td class='text-right'>".$rows."</td>

							</tr>";
						}
					}


				// theme preferences
					$sitetheme = e107::pref('core','sitetheme'); // currently just sitetheme, but could easily be expanded.
					$themelist = array($sitetheme);

					$text .= "</tbody><thead><tr>
					<th class='form-inline'>".$frm->checkbox_toggle('check-all-verify', 'xml_plugprefs')." &nbsp;Theme ".LAN_PREFS."</th>
					<th class='right'>".DBLAN_98."</th>

					</tr></thead><tbody>";

				//	ksort($themelist);

					foreach($themelist as $plug)
					{
						$data = e107::getThemeConfig($plug)->getPref();

						$key = $plug;

						$checked = false;

						if(!empty($data))
						{
							$rows = count($data);

							$text .= "<tr>
							<td>
								".$frm->checkbox("xml_themeprefs[".$key."]",$key, $checked, array('label'=>LAN_PREFS.": ".$key))."
							</td>
							<td class='text-right'>".$rows."</td>

							</tr>";
						}
					}




					$text .= "</tbody>
				</table>
				<table class='table adminlist'>
				<colgroup>
					<col style='width: 80%' />
					<col style='width: 20%' />
				</colgroup>
				<thead>
				<tr>
					<th class='form-inline'>".$frm->checkbox_toggle('check-all-verify', 'xml_tables')." &nbsp;".DBLAN_97."</th>
					<th class='right'>".DBLAN_98."</th>

				</tr>
				</thead>
				<tbody>\n";

					$tables = table_list();

					foreach($tables as $name=>$count)
					{
						$checked = (vartrue($_POST['xml_tables'][$name]) == $name) ? 1: 0;
						$text .= "<tr>
							<td>
								".$frm->checkbox("xml_tables[".$name."]", $name, $checked, array('label'=>DBLAN_99." ".$name)).
							"</td>
							<td class='right'>".$count."</td>
						</tr>";
					}

					$text .="

					</tbody>
				</table>

				<table class='table adminlist'>
				<colgroup>
					<col style='width: 80%' />
					<col style='width: 20%' />
				</colgroup>
				<thead>
				<tr>
					<th colspan='2'>".LAN_OPTIONS."</th>
				</tr>
				</thead>
				<tbody>
				<tr>
						<td colspan='2'>";
						$checked = (vartrue($_POST['package_images'])) ? 1: 0;
						$text .= $frm->checkbox("package_images",'package_images', $checked)." ".DBLAN_100." <i>".e107::getParser()->replaceConstants(EXPORT_PATH)."</i>

						</td>
					</tr>
				</tbody>
				</table>

				<div class='buttons-bar center'>
					".$frm->admin_button('exportXmlFile', DBLAN_101, 'other')."
				</div>
			</fieldset>
		</form>	";


		// display differences between default and core prefs.
/*
		$corePrefs = e107::pref('core');

		$defaultArray = e107::getXml()->loadXMLfile(e_CORE."xml/default_install.xml", 'advanced');
		$defaultPrefs = e107::getXml()->e107ImportPrefs($defaultArray);

		$text .= "<table class='table'>";
		foreach($defaultPrefs as $k=> $val)
		{
			if($val ==  $corePrefs[$k] || substr($k,-5) === '_list' || substr($k,0,9) == 'sitetheme')
			{
				continue;
			}


			$text .= "<tr>
				<td>".$k."</td>
				<td>".print_a($val,true)."<td><td>".print_a($corePrefs[$k],true)."</td>
				</tr>";

		}
		$text .= "</table>";
*/


		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_102,$mes->render(). $text);

		return null;
	}

	/**
	 * Import XML Dump
	 * @return null
	 */
	private function importXmlFile()
	{
		$ret = e107::getSingleton('xmlClass')->e107Import($_FILES['file_userfile']['tmp_name'][0]);

		foreach($ret['success'] as $table)
		{
			e107::getMessage()->addSuccess(DBLAN_103." $table");
		}

		foreach($ret['failed'] as $table)
		{
			e107::getMessage()->addError(DBLAN_104." $table");
		}

		return null;
	}

	/**
	 * Optimize SQL
	 * @param $mySQLdefaultdb
	 * @return null
	 */
	private function optimizesql($mySQLdefaultdb) 
	{
		$mes = e107::getMessage();
		$tables = e107::getDb()->tables();
		
		foreach($tables as $table)
		{
			e107::getDb()->gen("OPTIMIZE TABLE ".$table);
		}

		$mes->addSuccess(e107::getParser()->lanVars(DBLAN_11, $mySQLdefaultdb));
		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_7, $mes->render());

		return null;
	}

	private function getPrefConfig($type)
	{
		if(strpos($type,'plugin_') === 0)
		{
			$config = e107::getPlugConfig(substr($type,7));
		}
		elseif(strpos($type,'theme_') === 0)
		{
			$config = e107::getThemeConfig(substr($type,6));
		}
		else
		{
			$config = e107::getConfig($type);
		}

		return $config;

	}

	/**
	 * Preferences Editor
	 * @param string $type
	 * @return string text for display
	 */
	private function pref_editor($type='core')
	{

		global $e107;
		$frm = e107::getForm();
		$mes = e107::getMessage();
		$tp = e107::getParser();
		$pref = e107::getPref();


		$config = $this->getPrefConfig($type);

		$spref = $config->getPref();

		ksort($spref);

		$text = "
				<form method='post' action='".e_ADMIN."db.php?mode=".$_GET['mode']."&amp;type=".$type."' id='pref_edit'>
					<fieldset id='core-db-pref-edit'>
						<legend class='e-hideme'>".DBLAN_20."</legend>";

		$text .= "<select class='tbox form-control input-large' name='type_select' onchange='urljump(this.options[selectedIndex].value)' >
		<option value='".e_ADMIN."db.php?mode=".$_GET['mode']."&amp;type=core'>Core</option>\n
		<option value='".e_ADMIN."db.php?mode=".$_GET['mode']."&amp;type=search'>Search</option>
		<option value='".e_ADMIN."db.php?mode=".$_GET['mode']."&amp;type=notify'>Notify</option>\n";

	//	e107::getConfig($type)->aliases
		$text .= '<optgroup label="'.LAN_PLUGIN.'">';
		e107::getDb()->gen("SELECT e107_name FROM #core WHERE e107_name LIKE ('plugin_%') ORDER BY e107_name");
		while ($row = e107::getDb()->fetch())
		{
			$label = str_replace("plugin_","",$row['e107_name']);
			$key = $row['e107_name'];
			$selected = (varset($_GET['type'])==$key) ? "selected='selected'" : "";
			$text .= "<option value='".e_ADMIN."db.php?mode=".$_GET['mode']."&amp;type=".$key."' {$selected}>".ucwords($label)."</option>\n";
		}
		$text .= '</optgroup>';

		$text .= '<optgroup label="'.LAN_THEME.'">';
		e107::getDb()->gen("SELECT e107_name FROM #core WHERE e107_name LIKE ('theme_%') ORDER BY e107_name");
		while ($row = e107::getDb()->fetch())
		{
			$label = str_replace("theme_","",$row['e107_name']);
			$key = $row['e107_name'];
			$selected = (varset($_GET['type'])==$key) ? "selected='selected'" : "";
			$text .= "<option value='".e_ADMIN."db.php?mode=".$_GET['mode']."&amp;type=".$key."' {$selected}>".ucwords($label)."</option>\n";
		}
		$text .= '</optgroup>';

		$text .= "</select></div>
						<table class='table adminlist'>
							<colgroup>
								<col style='width: 5%' />
								<col style='width: 20%' />
								<col style='width: 70%' />
								<col style='width: 5%' />
							</colgroup>
							<thead>
								<tr>
									<th class='center'>".LAN_DELETE."</th>
									<th>".DBLAN_17."</th>
									<th>".DBLAN_18."</th>
									<th class='center last'>".LAN_OPTIONS."</th>
								</tr>
							</thead>
							<tbody>
			";

		foreach($spref as $key => $val)
		{
			if(is_array($val))
			{
				$varView = deftrue('e_DEBUG') ? var_export($val, true): print_r($val, true);
				$ptext = "<pre>".htmlentities($varView)."</pre>" ;
			}
			else
			{
				$ptext = htmlspecialchars($val, ENT_QUOTES, 'utf-8');
			}

			$ptext = $tp->textclean($ptext, 80);

			$text .= "
				<tr>
					<td class='center autocheck e-pointer'>".$frm->checkbox("delpref2[$key]", 1)."</td>
					<td>{$key}</td>
					<td>{$ptext}</td>
					<td class='center'>".$frm->submit_image("delpref[$key]", LAN_DELETE, 'delete', LAN_CONFIRMDEL." [$key]")."</td>
				</tr>
				";
		}

		$text .= "
							</tbody>
						</table>
						<div class='buttons-bar center'>
							".$frm->admin_button('delpref_checked', LAN_DELCHECKED, 'delete')."
							".$frm->admin_button('back', LAN_BACK, 'back')."
							<input type='hidden' name='pref_type' value='".$type."' />
						</div>
					</fieldset>
				</form>\n\n";

		if(strpos($type,'plugin_') === 0)
		{
			$caption = LAN_PLUGIN . SEP . ucfirst(substr($type,7));
		}
		elseif(strpos($type,'theme_') === 0)
		{
			$caption = LAN_THEME . SEP . ucfirst(substr($type,6));
		}
		else
		{
			$caption = ucwords($type);
		}


		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_20.SEP.$caption, $mes->render().$text);

		return $text;
	}

	/**
	 * Preferences Editor
	 * @return null
	 */
	private function scan_override()
	{
		$pref = e107::getPref();		
		$mes = e107::getMessage();
		$f = e107::getFile();
		$config = e107::getConfig();


		$fList = $f->get_files(e_CORE.'override/shortcodes/single', '\.php$');
		$scList = array();
		if(count($fList))
		{
			foreach($fList as $file)
			{
				$scList[] = strtoupper(substr($file['fname'], 0, -4));
			}
			$scList = implode(',', $scList);
		}
		$config->set('sc_override', $scList)->save(false);
		
		// core batch overrides
		$fList = $f->get_files(e_CORE.'override/shortcodes/batch', '\.php$');
		$scList = array();
		if(count($fList))
		{
			foreach($fList as $file)
			{
				$scList[] = substr($file['fname'], 0, -4);
			}
			$scList = implode(',', $scList);
		}
		
		$config->set('sc_batch_override', $scList)->save(false);
		//$pref['sc_override'] = $scList;
		//save_prefs();
	//	$mes->add(DBLAN_57.':<br />'.$pref['sc_override'], E_MESSAGE_SUCCESS);
		e107::getRender()->tablerender(
			'<strong>'.DBLAN_56, DBLAN_57.':</strong> '
			.($config->get('sc_override') ? '<br />'.$config->get('sc_override') : DBLAN_106)
			.'<br /><br /><strong>'.DBLAN_105.'</strong>'
			.($config->get('sc_batch_override') ? '<br />'.$config->get('sc_batch_override') : DBLAN_106)
		);
	}



	/**
	 * Plugin Folder Scanner
	 * @return null
	 */
	private function plugin_viewscan($mode = 'update')
	{
		$error_messages = array(0 => DBLAN_31, 1 => LAN_ERROR, 2 => DBLAN_33, 3 => DBLAN_34);
	//	$error_image = array("integrity_pass.png", "integrity_fail.png", "warning.png", "blank.png");
		$error_glyph = array(ADMIN_TRUE_ICON, ADMIN_FALSE_ICON, ADMIN_WARNING_ICON,"<i style='display:inline-block;width:17px;height:16px;'> </i>");
		$error_type = array('warning'=>2, 'error'=>1);


		global $e107;
		$sql = e107::getDb();
		$tp = e107::getParser();
		$frm = e107::getForm();
		$mes = e107::getMessage();

	//	require_once (e_HANDLER."plugin_class.php");
	//	$ep = new e107plugin();
	//	$ep->update_plugins_table($mode); // scan for e_xxx changes and save to plugin table.
	//	$ep->save_addon_prefs($mode); // generate global e_xxx_list prefs from plugin table.

		$mes->add(DBLAN_23, E_MESSAGE_SUCCESS);
		$mes->add("<a href='".e_SELF."'>".LAN_BACK."</a>", E_MESSAGE_SUCCESS);
		$mes->add(DBLAN_30);

		$text = "
				<form method='post' action='".e_ADMIN."db.php?mode=".$_GET['mode']."' id='plug_edit'>
					<fieldset id='core-db-plugin-scan'>
						<legend class='e-hideme'>".ADLAN_CL_7."</legend>
						<table class='table adminlist'>
							<colgroup>
								<col style='width: 20%' />
								<col style='width: 20%' />
								<col style='width: 35%' />
								<col style='width: 25%' />
							</colgroup>
							<thead>
								<tr>
									<th>".LAN_NAME."</th>
									<th>".DBLAN_25."</th>
									<th>".DBLAN_26."</th>
									<th class='center last'>".DBLAN_27."</th>
								</tr>
							</thead>
							<tbody>
			";


		$plg = e107::getPlug()->clearCache();

		$plg->buildAddonPrefLists();

		$plgClass = e107::getPlugin();


		foreach($plg->getDetected() as $folder)
		{
			$plg->load($folder);
			$plgClass->plugFolder = $folder;
			$plgClass->XmlLanguageFiles('refresh');

			$name   = $plg->getName();
			$addons = $plg->getAddons();

				$text .= "
								<tr>
									<td>".$name."</td>
	               					<td>".$folder."</td>
									<td>";

				if(!empty($addons))
				{

					foreach(explode(',', $addons) as $this_addon)
					{
						$ret_code = 3; // Default to 'not checked
						if((strpos($this_addon, 'e_') === 0) || (substr($this_addon, - 4, 4) == '_sql'))
						{
							$ret_code = $plg->getAddonErrors($this_addon); // See whether spaces before opening tag or after closing tag
						}
						elseif(strpos($this_addon, 'sc_') === 0)
						{
							$this_addon = substr($this_addon, 3). ' (sc)';
						}

						if(!is_numeric($ret_code))
						{
							$errorMessage = $ret_code['msg'];
							$ret_code = $error_type[$ret_code['type']];
						}
						else
						{
							$errorMessage  = $error_messages[$ret_code];
						}

						$text .= "<span class='clear e-tip' style='cursor:pointer' title='".$errorMessage."'>";
						$text .= $error_glyph[$ret_code]."&nbsp;";

						$text .= trim($this_addon); // $ret_code - 0=OK, 1=content error, 2=access error
						$text .= "</span><br />";
					}
				}


				$text .= "	</td>
								<td class='center'>";

				$text .= ($plg->isInstalled() === true) ? "<span class='label label-warning'>".DBLAN_27."</span>" : " ";


				$text .= " </td>
							</tr>
				";


		}



		$text .= "
							</tbody>
						</table>
					</fieldset>
				</form>
			";

		e107::getRender()->tablerender(DBLAN_10.SEP.DBLAN_22, $mes->render().$text);

		return null; 
	}




}

//XXX - what is this for (backup core)? <input type='hidden' name='sqltext' value='{$sqltext}' />

function db_adminmenu() //FIXME - has problems when navigation is on the LEFT instead of the right. 
{
	global $st;

	$var = array();

	foreach($st->_options as $key=>$val)
	{
		$var[$key]['text'] = $val['label'];
		$var[$key]['link'] = e_SELF."?mode=".$key;
		$var[$key]['image_src'] = $val['icon'];
	}

	$caption = "<span>".DBLAN_10."</span>";

	$var['_extras_']['icon'] = e107::getParser()->toIcon('e-database-24');

	e107::getNav()->admin($caption, $_GET['mode'], $var);
}


/**
 * Export XML File and Copy Images.
 * @param object $prefs
 * @param object $tables
 * @param object $debug [optional]
 * @return bool|null
 */
function exportXmlFile($prefs,$tables=array(),$plugPrefs=array(), $themePrefs=array(), $package=FALSE,$debug=FALSE)
{
	$xml = e107::getXml();
	$tp = e107::getParser();
	$mes = e107::getMessage();

	$desinationFolder = null;

	if(vartrue($package))
	{

		$xml->convertFilePaths = TRUE;
		$xml->modifiedPrefsOnly = true;
		$xml->filePathDestination = EXPORT_PATH;
		$xml->filePathPrepend = array(
			'news_thumbnail'	=> "{e_IMAGE}newspost_images/"
		);


		$desinationFolder = $tp->replaceConstants($xml->filePathDestination);

		if(!is_writable($desinationFolder))
		{
			$message = str_replace('[folder]', $desinationFolder, DBLAN_107);
			$mes->add($message, E_MESSAGE_ERROR);
			return false ;
		}
	}

	$mode = ($debug === true) ? array( "debug" =>1) : null;

	if($xml->e107Export($prefs,$tables,$plugPrefs, $themePrefs, $mode))
	{
		$mes->add(DBLAN_108." ".$desinationFolder."install.xml", E_MESSAGE_SUCCESS);
		if(varset($xml->fileConvertLog))
		{
			foreach($xml->fileConvertLog as $oldfile)
			{
				$file = basename($oldfile);
				$newfile = $desinationFolder.$file;
				if($oldfile == $newfile || (copy($oldfile,$newfile)))
				{
					$mes->add(DBLAN_109." ".$newfile, E_MESSAGE_SUCCESS);
				}
				elseif(!file_exists($newfile))
				{
					$mes->add(DBLAN_110." ".$newfile, E_MESSAGE_ERROR);
				}
			}
		}

	}

	return null;
}



function table_list()
{
	// grab default language lists.
	//TODO - a similar function is in db_verify.php. Should probably all be moved to mysql_class.php.

	$exclude = array();
	$exclude[] = "core";
	$exclude[] = "rbinary";
	$exclude[] = "parser";
	$exclude[] = "tmp";
	$exclude[] = "online";
	$exclude[] = "upload";
	$exclude[] = "user_extended_country";
//	$exclude[] = "plugin";

	$coreTables = e107::getDb()->tables('nolan');

	$tables = array_diff($coreTables,$exclude);

	$tabs = array();

	foreach($tables as $e107tab)
	{
		$count = (int) e107::getDb()->count($e107tab);

		if(!empty($count))
		{
			$tabs[$e107tab] = $count;
		}
	}

	return $tabs;
}




?>
