<?php
/**
 * @package html
 */
namespace bbn\html;
use bbn;
/**
 * Creates DOM elements
 * 
 * Depends on JsonSchema\Validator
 *
 *
 * This class will create a DOM element.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 2, 2013, 23:23:55 +0000
 * @category  HTML
 * @package html
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.3
 * @todo Tooltips
 */
class element
{
	protected
          /** @var array The element's configuration */
          $cfg,
          
          /** @var string The element's textNode */
          $text = '',

          /** @var string|array The element's content (can be an array of elements) */
          $content;
  
  public
          /** @var string The element's tag */
          $tag = false,
          
          /** @var string The element's tag */
          $attr = [],
          
          /** @var array The element's attributes */
          $css = [],
          
          /** @var array Styles */
          $script = '',
          
          /** @var string JavaScript code which should be executed */
          $events,

          /** @var array The element's data */
          $data,
          
          /** @var array Widget's configuration */
          $widget,
          
          /** @var string Help for tooltip */
          $help,
          
          /** @var bool XHTML tag ending, false by default */
          $xhtml = false;

  public static 
          /** @var array List of known HTML self-closing tags */
          $self_closing_tags = ["area", "base", "hr", "col", "command", "embed", "hr", "img", "input", "keygen", "link", "meta", "param", "source", "track", "wbr"],
          
          /** @var array List of known inputs */
          $input_fields = ["input", "textarea", "select"];

  protected static
          /** @var bool|object The JSON validator object. Will be generated only once */
          $validator = false,
          
          /** @var string last error */
          $error,

          /** @var string JSON schema of the configuration */
          $schema = '{
	"type":"object",
	"$schema": "http:\/\/json-schema.org\/draft-03\/schema",
	"id": "#",
	"required":false,
	"properties":{
		"attr": {
			"type":"object",
			"id": "attr",
      "description": "Attributes",
			"required":false,
			"properties":{}
		},
    "content": {
			"type":["array","string","null"],
			"id": "content",
			"required":false,
      "description": "Css properties"
		},
    "css": {
			"type":["object","null"],
			"id": "css",
			"required":false,
      "description": "Css properties"
		},
		"events": {
			"type":"object",
			"id": "events",
      "description": "Events",
			"required":false,
			"properties":{
				"change": {
					"type":"string",
					"required":false
				}
			}
		},
		"script": {
			"type":"string",
      "description": "Script",
			"id": "script",
			"required":false
		},
		"tag": {
			"type":"string",
			"id": "tag",
      "description": "Tag",
			"required":true
		},
		"text": {
			"type":"string",
			"id": "text",
      "description": "Text",
			"required":false
		},
		"widget": {
			"type":["object","array"],
			"id": "widget",
      "description": "Widget",
			"required":false,
			"properties":{
				"name": {
					"type":"string",
					"required":true
				},
				"options": {
					"type":["object","array"],
					"required":false
				}
			}
		},
		"xhtml": {
			"type":"boolean",
			"id": "xhtml",
      "description": "XHTML conformity",
			"required":false
		}
	}
}';
  
	/**
	 * This creates a unique JSON schema validator object
   * And for each type of class generates the according JSON schema
   * By combining this root classs element schema
   * And the child class' schema
   * The schema has then to be called static::$schema and not self::$schema
	 *
	 * @return void 
	 */
  
  private static function _init(){
    if ( !self::$validator ){
      self::$validator = new \JsonSchema\Validator();
      if ( is_string(self::$schema) ){
        self::$schema = json_decode(self::$schema);
      }
    }
    if ( !empty(static::$schema) ){
      static::$schema = bbn\x::merge_objects(self::$schema, bbn\x::to_object(static::$schema));
    }
    else{
      static::$schema = self::$schema;
    }
  }
  
 	/**
	 * Returns a config more adequate for the schema:
   * converts the types according to the schema
   * 
   * @param array $cfg Configuration
   * @param object $schema JSON Schema
	 * @return array
	 */
  private static function cast(array $cfg, $schema=null){
    if ( is_null($schema) && is_object(static::$schema) ){
      $schema = static::$schema;
    }
    if ( is_object($schema) && is_array($cfg) && isset($schema->properties) ){
      foreach ( $schema->properties as $k => $p ){
        if ( empty($cfg[$k]) ){
          unset($cfg[$k]);
        }
        else if ( is_object($p) ){
          if ( is_string($cfg[$k]) && $p->type === 'integer' ){
            $cfg[$k] = (int)$cfg[$k];
          }
          else if ( is_int($cfg[$k]) && $p->type === 'boolean' ){
            $cfg[$k] = (bool)$cfg[$k];
          }
          else if ( is_array($cfg[$k]) ){
            $cfg[$k] = self::cast($cfg[$k], $p);
          }
        }
      }
    }
    return $cfg;
  }

	/**
	 * Returns the current schema
	 *
	 * @return object
	 */
  protected static function get_schema(){
    return static::$schema;
  }
  
	/**
	 * Confront the JSON schema object with the current configuration
	 *
   * @param array $cfg Configuration
	 * @return bool
	 */
  public static function check_config($cfg){
    if ( !is_array($cfg) ){
      self::$error = "The configuration is not a valid array";
      return false;
    }
    self::$validator->check(bbn\x::to_object($cfg), static::$schema);
    self::$error = '';
    if ( self::$validator->isValid() ){
      return 1;
    }
    foreach ( self::$validator->getErrors() as $error ) {
      self::$error .= sprintf("[%s] %s",$error['property'], $error['message']);
      var_dump($cfg);
    }
    return false;
  }
  
	/**
	 * Returns the current error(s)
	 *
	 * @return string
	 */
  public static function get_error()
  {
    return self::$error;
  }

  
	/**
	 * Generates style string for a HTML tag
	 *
   * @param array|string $css CSS properties/values array
	 * @return string
	 */
  public static function css_to_string($css){
    if ( is_string($css) ){
      return ' style="'.bbn\str::escape_dquotes($css).'"';
    }
    else if ( is_array($css) && count($css) > 0 ){
      $st = '';
      foreach ( $css as $prop => $val ){
        $st .= $prop.':'.$val.';';
      }
      return ' style="'.bbn\str::escape_dquotes($st).'"';
    }
  }
  
  public function css(array $cfg){
    foreach ( $cfg as $i => $k ){
      if ( !bbn\str::is_number($i) ){
        $this->css[$i] = $k;
      }
    }
    $this->update();
    return $this;
  }
  
  public function add_class($class){
    if ( !isset($this->attr['class']) ){
      $this->attr['class'] = $class;
    }
    else{
      $cls = explode(" ", $this->attr['class']);
      if ( !in_array($class, $cls) ){
        $this->attr['class'] .= ' '.$class;
      }
    }
    $this->update();
    return $this;
  }

	/**
	 * @return bbn\html\element
	 */
  public function __construct($cfg)
	{
    self::_init();
    if ( is_string($cfg) && !empty($cfg) ){
      // Looking for classes, IDs, or name (|) in the string
      preg_match_all("/([\\.|\\||#]{1})([A-z0-9-]+)/", $cfg, $m);
      $classes = [];
      $id = false;
      $name = false;
      if ( isset($m[0], $m[1], $m[2]) && count($m[0]) > 0 ){
        foreach ( $m[1] as $k => $v ){
          if ( $v === '.' ){
            array_push($classes, $m[2][$k]);
          }
          else if ( $v === '#' ){
            $id = $m[2][$k];
          }
          else if ( $v === '|' ){
            $name = $m[2][$k];
          }
        }
      }
      // Looking for the tag (mandatory)
      preg_match_all("/^([A-z0-9-]+)/", $cfg, $n);
      if ( isset($n[0]) && count($n[0]) > 0 ){
        $cfg = ['tag' => $n[0][0]];
        if ( (count($classes) > 0) || $id || $name ){
          $cfg['attr'] = [];
          if ( count($classes) > 0 ){
            $cfg['attr']['class'] = implode(" ", $classes);
          }
          if ( $id ){
            $cfg['attr']['id'] = $id;
          }
          if ( $name ){
            $cfg['attr']['name'] = $name;
          }
        }
      }
    }
    $cfg = self::cast($cfg);
		if ( self::check_config($cfg) ){
      foreach ( $cfg as $key => $val ){
        if ( $key === 'tag' ){
          $this->tag = strtolower($val);
        }
        else if ( property_exists(get_called_class(), $key) ){
          $this->$key = $val;
        }
      }
      $this->update();
    }
    else{
      $err[] = self::get_error();
      if ( isset($cfg['tag']) ){
        $err[] = 'Tag: '.$cfg['tag'];
      }
      if ( isset($cfg['name']) ){
        $err[] = 'Name: '.$cfg['name'];
      }
      var_dump($err);
    }
	}
	
	/**
   * Sets the configuration property according to the current configuration
   * 
	 * @return bbn\html\element
	 */
  protected function update()
  {
    $this->cfg = [];
    foreach ( $this as $key => $var ){
      if ( $key !== 'cfg' && !is_null($var) ){
        if ( is_array($var) ){
          foreach ( $var as $k => $v ){
            if ( !isset($this->cfg[$key]) ){
              $this->cfg[$key] = [];
            }
            if ( !is_null($v) ){
              $this->cfg[$key][$k] = $v;
            }
          }
        }
        else{
          $this->cfg[$key] = $var;
        }
      }
    }
    return $this;
  }
  
  /**
   * Add an element to the content, or a string if it's one
   * 
   * @param string|bbn\html\element $ele
   */
  public function append($ele)
  {
    $args = func_get_args();
    foreach ( $args as $ele ){
      if ( !isset($this->content) ){
        if ( is_array($ele) && isset($ele[0]) ){
          $this->content = $ele;
        }
        else{
          $this->content = is_object($ele) ? [$ele] : $ele;
        }
      }
      else if ( is_array($this->content) ){
        if ( is_array($ele) ){
          array_merge($this->content, $ele);
        }
        else{
          array_push($this->content, $ele);
        }
      }
      else if ( is_string($this->content) ){
        if ( is_array($ele) ){
          foreach ( $ele as $e ){
            $this->content .= $e->html();
          }
        }
        else{
          $this->content .= is_object($ele) ? $ele->html() : $ele;
        }
      }
    }
    return $this;
  }
	/**
	 * Returns the current configuration.
   * 
   * @return array Current configuration
	 */
	public function get_config()
	{
    $this->update();
		$tmp = bbn\x::remove_empty($this->cfg);
    if ( isset($tmp['content']) && is_array($tmp['content']) ){
      foreach ( $tmp['content'] as $i => $c ){
        if ( is_object($c) ){
          if (method_exists($c, 'get_config') ){
            $tmp['content'][$i] = $c->get_config();
          }
        }
      }
    }
    return $tmp;
	}
  
	/**
	 * Returns the current configuration  HOW???
   * 
   * @return array Current configuration
	 */
  public function get_param()
  {
    return bbn\str::make_readable($this->get_config());
  }
  
	/**
	 * Returns the current configuration for PHP
   * 
   * @return array Current configuration
	 */
  public function show_config()
  {
    return bbn\str::export(bbn\str::make_readable($this->get_config()), 1);
  }
	
	/**
	 * Returns the javascript coming with the object
   * 
   * @return string javascript string
	 */
	public function script($with_ele=1)
	{
    $this->update();
		$r = '';
		if ( isset($this->attr['id']) ){
      if ( isset($this->cfg['events']) ){
        foreach ( $this->cfg['events'] as $event => $fn ){
          $r .= '.'.$event.'('.
                  ( strpos($fn, 'function') === 0 ? $fn : 'function(e){'.$fn.'}' ).
                  ')';
        }
      }
      if ( isset($this->cfg['widget'], $this->cfg['widget']['name']) ){
        $r .= '.'.$this->cfg['widget']['name'].'(';
        if ( isset($this->cfg['widget']['options']) ){
          $r .= '{';
          foreach ( $this->cfg['widget']['options'] as $n => $o ){
            $r .= '"'.$n.'":';
            if ( is_string($o) ){
              $o = trim($o);
              if ( (strpos($o, 'function(') === 0) ){
                $r .= $o;
              }
              else{
                $r .= '"'.bbn\str::escape_dquotes($o).'"';
              }
            }
            else if ( is_bool($o) ){
              $r .= $o ? 'true' : 'false';
            }
            else{
              $r .= json_encode($o);
            }
            $r .= ',';
          }
          $r .= '}';
        }
        $r .= ')';
      }
      if ( !empty($this->help) ){
        // tooltip
      }
      if ( !empty($r) ){
        if ( $with_ele ){
          $r = '$("#'.$this->attr['id'].'")'.$r.';'.PHP_EOL;
        }
        else{
          $r = $r.';'.PHP_EOL;
        }
      }
		}
    if ( !empty($this->script) ){
      $r .= $this->script.PHP_EOL;
    }
    if ( is_array($this->content) ){
      foreach ( $this->content as $c ){
        if ( is_array($c) ){
          $c = new bbn\html\element($c);
        }
        if (is_object($c) && method_exists($c, 'script') ){
          $r .= $c->script();
        }
      }
    }
		return $r;
	}
	
  public function attr($arr)
  {
    $args = func_get_args();
    if ( is_array($arr) ){
      foreach ( $arr as $k => $v ){
        if ( $k === 'class' ){
          $this->add_class($v);
        }
        else{
          $this->attr[$k] = $v;
        }
      }
    }
    else if ( (count($args) === 2) && is_string($args[0]) && is_string($args[1]) ){
      if ( $args[0] === 'class' ){
        $this->add_class($args[1]);
      }
      else{
        $this->attr[$args[0]] = $args[1];
      }
    }
    else if ( is_string($arr) && isset($this->attr[$arr]) ){
      return $this->attr[$arr];
    }
    return $this;
  }
  
  public function text($txt=null)
  {
    if ( !is_null($txt) ){
      $this->text = strip_tags($txt);
      return $this;
    }
    return $this->text;
  }
  
  public function content($c=null)
  {
    if ( is_null($c) ){
      return $this->content;
    }
    else if ( is_array($c) || is_string($c) ){
      $this->content = $c;
      return $this;
    }
  }
	/**
	 * Returns the corresponding HTML string
   * 
   * @param bool $with_js Includes the javascript
   * @return string HTML string
	 */
	public function html($with_js = 1)
	{
    $html = '';
		if ( $this->tag ){
			$this->update();
      // TAG
			$html .= '<'.$this->tag;

      foreach ( $this->attr as $key => $val ){
        if ( is_string($key) ){
          $html .= ' '.htmlspecialchars($key).'="';
          if ( is_numeric($val) ){
            $html .= $val;
          }
          else if (is_string($val) ){
            $html .= htmlspecialchars($val);
          }
          $html .= '"';
        }
      }
			
      if ( count($this->css) > 0 ){
				$html .= self::css_to_string($this->css);
			}
      if ( $this->xhtml ){
        $html .= ' /';
      }
      $html .= '>';

			
			if ( !in_array($this->tag, self::$self_closing_tags) ){

        if ( isset($this->text) ){
          $html .= $this->text;
        }
        
        if ( isset($this->content) ){
          // @todo: Add the ability to imbricate elements
          if ( is_string($this->content) ){
            $html .= $this->content;
          }
          else if ( is_array($this->content) ){
            foreach ( $this->content as $c ){
              if ( is_array($c) ){
                $c = new bbn\html\element($c);
              }
              $html .= $c->html($with_js);
            }
          }
        }
				$html .= '</'.$this->tag.'>';
			}
			
			if ( isset($this->placeholder) && strpos($this->placeholder,'%s') !== false ){
				$html = sprintf($this->placeholder, $html);
			}
      
		}
		return $html;
	}
  
  public function ele_and_script()
  {
    return ['$(\''.bbn\str::escape_squotes($this->html()).'\')',$this->script(false)];
  }
	
  public function make_empty()
  {
    $this->content = null;
    $this->html = '';
    $this->script = '';
  }
}
?>