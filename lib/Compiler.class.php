<?php
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Environment.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Combinator.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Comment.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Quoted.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Import.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Ruleset.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Color.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Expression.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Dimension.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Unit.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Call.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Value.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Rule.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Variable.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Keyword.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Operation.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Element.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Selector.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Anonymous.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Mixin/Definition.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Mixin/Call.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Url.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Paren.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Media.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Attribute.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Condition.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Directive.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/Negative.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Node/UnitConversions.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Colors.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Visitor/visitor.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Visitor/extend-visitor.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Visitor/process-extends-visitor.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Visitor/join-selector-visitor.php';
require dirname(__FILE__).'/vendor/less.php-master/lib/Less/Parser.php';

/**
 * LESS compiler
 *
 * @author oncletom
 * @extends lessc
 * @package wp-less
 * @subpackage lib
 * @since 1.2
 * @version 1.3
 */
class WPLessCompiler extends Less_Parser
{
	/**
	 * Instantiate a compiler
	 *
   * @api
	 * @see	lessc::__construct
	 * @param $file	string [optional]	Additional file to parse
	 */
	public function __construct($file = null)
	{
  	do_action('wp-less_compiler_construct_pre', $this, $file);
		parent::__construct(apply_filters('wp-less_compiler_construct', $file));
	}

  /**
   * Registers a set of functions
   *
   * @param array $functions
   */
  public function registerFunctions(array $functions = array())
  {
    foreach ($functions as $name => $args)
    {
      $this->registerFunction($name, $args['callback']);
    }
  }

	/**
	 * Returns available variables
	 *
	 * @since 1.5
	 * @return array Already defined variables
	 */
	public function getVariables()
	{
		return $this->registeredVars;
	}

	public function setVariable($name, $value)
	{
		$this->registeredVars[ $name ] = $value;
	}

	public function getImportDir()
	{
		return (array)$this->importDir;
	}

	/**
	 * Smart caching and retrieval of a tree of @import LESS stylesheets
	 *
	 * @since 1.5
	 * @param WPLessStylesheet $stylesheet
	 * @param bool $force
	 */
	public function cacheStylesheet(WPLessStylesheet $stylesheet, $force = false)
	{
		 $upload_dir = wp_upload_dir(); 
		$to_cache = array( $stylesheet->getSourcePath() => '/wp-less/' );
        Less_Cache::$cache_dir = $upload_dir['basedir'].'/wp-less/';
        $cache_name = Less_Cache::Get( $to_cache );
        
        $compiled = file_get_contents($upload_dir['basedir'].'/wp-less/'.$cache_name );
		
	
		
		$compiled_cache = get_transient($cache_name);
		
		if( !$force && !file_exists( $stylesheet->getTargetPath() ) ) $force = true;

		//$compiled_cache = $this->cachedCompile($compiled_cache ? $compiled_cache : $stylesheet->getSourcePath(), $force);

		// saving compiled stuff
		$stylesheet->setSourceTimestamp($compiled_cache['updated']);
		$this->saveStylesheet($stylesheet, $compiled);
		
		
		/*if (isset($compiled_cache['compiled']) && $compiled_cache['compiled'])
		{
            $stylesheet->setSourceTimestamp($compiled_cache['updated']);
			$this->saveStylesheet($stylesheet, $compiled);

			$compiled_cache['compiled'] = NULL;
			set_transient($cache_name, $compiled_cache);
		}*/
	}

	/**
	 * Process a WPLessStylesheet
	 *
	 * This logic was previously held in WPLessStylesheet::save()
	 *
	 * @since 1.4.2
	 * @param WPLessStylesheet $stylesheet
	 * @param null $css
	 */
	public function saveStylesheet(WPLessStylesheet $stylesheet, $css = null)
	{
		
		wp_mkdir_p(dirname($stylesheet->getTargetPath()));

		try
		{
			do_action('wp-less_stylesheet_save_pre', $stylesheet, $this->getVariables());

			if ($css === null)
			{
				$css = $this->compileFile($stylesheet->getSourcePath());
			}

			file_put_contents($stylesheet->getTargetPath(), apply_filters('wp-less_stylesheet_save', $css, $stylesheet));
			chmod($stylesheet->getTargetPath(), 0666);

			$stylesheet->save();
			do_action('wp-less_stylesheet_save_post', $stylesheet);
		}
		catch(Exception $e)
		{
			wp_die($e->getMessage());
		}
	}
}
