<?php
	/**
	 * e107 website system
	 *
	 * Copyright (C) 2008-2019 e107 Inc (e107.org)
	 * Released under the terms and conditions of the
	 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
	 *
	 */


	class e_jsmanagerTest extends \Codeception\Test\Unit
	{

		/** @var e_jsmanager */
		protected $js;

		protected function _before()
		{

			try
			{
				$this->js = $this->make('e_jsmanager');
			}
			catch(Exception $e)
			{
				$this->assertTrue(false, "Couldn't load e_jsmanager object");
			}

		}

/*
		public function testHeaderPlugin()
		{

		}

		public function testTryHeaderInline()
		{

		}
*/
		public function testIsInAdmin()
		{
			$result = $this->js->isInAdmin();
			$this->assertFalse($result);

		}
/*
		public function testRequireCoreLib()
		{

		}

		public function testSetInAdmin()
		{

		}

		public function testCoreCSS()
		{

		}

		public function testResetDependency()
		{

		}

		public function testJsSettings()
		{

		}

		public function testGetInstance()
		{

		}

		public function testFooterFile()
		{

		}

		public function testSetData()
		{

		}

		public function testLibraryCSS()
		{

		}

		public function testTryHeaderFile()
		{

		}

		public function testThemeCSS()
		{

		}

		public function testOtherCSS()
		{

		}

		public function testSetLastModfied()
		{

		}

		public function testRenderLinks()
		{

		}

		public function testThemeLib()
		{

		}

		public function testRenderFile()
		{

		}

		public function testHeaderCore()
		{

		}

		public function testRenderInline()
		{

		}

		public function testFooterTheme()
		{

		}

		public function testGetData()
		{

		}

		public function testRequirePluginLib()
		{

		}

		public function testGetCacheId()
		{

		}

		public function testHeaderTheme()
		{

		}

		public function testInlineCSS()
		{

		}

		public function testHeaderFile()
		{

		}

		public function testSetDependency()
		{

		}

		public function testHeaderInline()
		{

		}

		public function testGetLastModfied()
		{

		}

		public function testSetCacheId()
		{

		}

		public function testGetCurrentTheme()
		{

		}

		public function testPluginCSS()
		{

		}

		public function testCheckLibDependence()
		{

		}

		public function testRenderCached()
		{

		}

		public function testGetCurrentLocation()
		{

		}

		public function testFooterInline()
		{

		}

		public function testAddLibPref()
		{

		}
*/
		public function testAddLink()
		{
				$tests = array(
					0   => array(
						'expected'  => '<link rel="preload" href="https://fonts.googleapis.com/css?family=Nunito&display=swap" as="style" onload="this.onload=null;" />',
						'input'     => array('rel'=>'preload', 'href'=>'https://fonts.googleapis.com/css?family=Nunito&display=swap', 'as'=>'style', 'onload' => "this.onload=null;"),
						'cacheid'   => false,
						),
					1   => array(
						'expected'  => '<link rel="preload" href="'.e_THEME_ABS.'bootstrap3/assets/fonts/fontawesome-webfont.woff2?v=4.7.0" as="font" type="font/woff2" crossorigin />', // partial
						'input'     => 'rel="preload" href="{THEME}assets/fonts/fontawesome-webfont.woff2?v=4.7.0" as="font" type="font/woff2" crossorigin',
						'cacheid'   => false,
						),
					2   => array(
						'expected'  => '<link rel="preload" href="'.e_WEB_ABS.'script.js?0" as="script" />',
						'input'     => array('rel'=>'preload', 'href'=>'{e_WEB}script.js', 'as'=>'script'),
						'cacheid'   => true,
						),

					/* Static URLs enabled from this point. */

					3   => array(
						'expected'  => '<link rel="preload" href="https://static.mydomain.com/e107_web/script.js?0" as="script" />',
						'input'     => array('rel'=>'preload', 'href'=>'{e_WEB}script.js', 'as'=>'script'),
						'cacheid'   => true,
						'static'    => true,
						),
				);

				$tp = e107::getParser();

				foreach($tests as $var)
				{
					$static = !empty($var['static']) ? 'https://static.mydomain.com/' : null;
					$tp->setStaticUrl($static);

					$this->js->addLink($var['input'], $var['cacheid']);
				//	$this->assertSame($var['expected'],$actual);
				}

				$actual = $this->js->renderLinks(true);

				foreach($tests as $var)
				{
					$result = (strpos($actual, $var['expected']) !== false);
					$this->assertTrue($result, $var['expected']." was not found in the rendered links. Render links result:".$actual."\n\n");
				}

				$tp->setStaticUrl(null);


		}
/*
		public function testLibDisabled()
		{

		}

		public function testArrayMergeDeepArray()
		{

		}

		public function testRenderJs()
		{

		}

		public function testRemoveLibPref()
		{

		}
*/


	}
