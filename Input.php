<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */

/** 
 * 输入类
 * 功能：对用户输入数据进行验证、处理后，得到一个符合要求的Input数据数组(关联数组)。
 *      如果用户没有输入的字段则返回空串''或默认值，不会返回NULL值。
 *      Input数据数组取值都是字符串，有些取值需要进行trim intval等类型转换。
 * 
 * 用户输入数据来源： 
 *  $_GET: 用于Get、POST请求。
 *  $_POST： 用于Post请求。
 *  $_REQUEST： 用于没有方法限制的请求。
 *  $_COOKIE： 用于cookie里的数据。
 *
 * 使用方式：
 * (1)模板变量定义：
 *      $NAME_echo：用于显示用户输入值。
 *      $NAME_error: 用于显示错误信息。
 *      $NAME_$VALUE_echo: 用于显示select、checkbox、radio字段用户输入值。
 *      比如： <input type="checkbox" name="mycheck[]" value="1" <?=$mycheck_1_echo?> />
 * (2)定义验证/处理规则：
 *      $rules = array(
 *                  array('field' => '',             // 只有select、checkbox字段可以是数组：name[]
 *                        'label' => '', 
 *                        'rules' => 'trim|required|min_length[5]|md5', //可以为空，注意：每一条规则最多一个参数。
 *                        'type'  => ''              // 字段类型：select radio checkbox
 *                         );
 * (3)创建一个输入对象：
 *      $input = new Gek_Input(array(), 'GET'/'POST'/'REQUEST'/'COOKIE');
 * (4)设置验证规则：
 *      set_rules($rules);
 * (5)进行验证和处理：
 *      if(($input_data = $input->run()) === false) { //验证有错误，进行错误处理...
 *        $output_data = $input->output_error();
 *      } else {  //验证成功，则返回用户输入数据
 *      }
 * (6)验证规则
 *      defvalue                默认值，用户没有输入则返回默认值。
 *      required                不能为空，包括空白字符。
 *      regex_match[pattern]    正则匹配
 *      matches[field]          是否和字段field取值一致
 *      min_length[len]         最少字符数，支持UTF-8字符
 *      max_length[len]         最多字符数，支持UTF-8字符
 *      exact_length[len]       字符数，支持UTF-8字符
 *      valid_email             是不是有效的邮件地址
 *      valid_emails            邮件地址逗号分隔
 *      valid_url               支持http https协议
 *      valid_ip                支持IPv4
 *      valid_base64
 *      valid_id                身份证号
 *      valid_zipcode           邮政编码
 *      valid_option[options]   chekckbox radio select有效选项，options是逗号分隔的选项列表。
 *      valid_money[length]     检查是否为合法金额 xxx.yy 小数点后最多两位，$length 整数部分的最大位数
 *      valid_date              日期格式(CCYY-MM-DD)
 *      valid_time              时间格式(CCYY-MM-DD HH:MM:SS)
 *      valid_daterange[field]  检查日期范围是否有效，参数field指明结束日期字段名。
 *      alpha                   字母
 *      alpha_numeric           字母、数字
 *      alpha_dash              字母、数字、-、_
 *      numeric                 正负整数、小数，包括 +.0 -.0
 *      is_numeric              数值的范围更广，包括科学计数法
 *      integer                 正负整数
 *      decimal                 正负整数、小数，不包括+.0 -.0
 *      is_natural              自然数，包括0
 *      is_natural_no_zero      自然数，不包括0
 *      greater_than[min]       大于min
 *      less_than[max]          小于max
 *      is_chinese              是否为中文
 * 
 * (7)处理规则
 *    任何接收一个参数的PHP函数都可以被用作一个处理规则，比如 htmlspecialchars, trim, MD5等。
      注意: 一般会在验证规则之后使用这些处理规则，这样如果发生错误，原数据将会被显示在表单。
 *    支持的处理规则：
 *      htmlclean               用于对富文本编辑器字段进行安全过滤 *****
 *      prep_url                当URL丢失"http://" 时，添加"http://"
 *      strip_image_tags        去掉img标签，只保留图片URL
 *      encode_php_tags         将PHP脚本标签强制转成实体对象
 *      tonewline               把"\r\n", "\r", "\r\n\n"都转为"\n"
 *      tab2space               把所有tabs都转为空格
 *      rminvchar               删除不可见字符，但\r \n \t是保留的。
 *      substr_utf8[length]     对超过length的utf8字符串进行截取。
 *              
 * 数组形式的变量名name[]：
 * 为什么要用数组形式的变量名name[]？
 *  默认情况下，请求中有多个名字相同的变量，则只保留最后一个，如果想要获得一个变量的多个取值，
 *  需要在把变量名设置为数组形式，比如：name[]。这样$_GET/$_POST/$_REQUEST中变量的取值也是数组。
 *  比如：select表单元素支持多选，提交时格式为：select_name=option1&select_name=option2...
 *  如果select_name不是数组，则PHP只能得到最后出现的option2.
 * 表单验证支持表单字段的名称为数组，比如：
 *  <input type="text" name="options[]" value="" size="50" />
 *  如果你将表单字段名称定义为数组，那么凡是需要用到表单字段名的地方，也都需要采用数组形式。
 * 另外还可以使用多维数组，比如：
 * <input type="checkbox" name="options[color][]" value="red" />
 * <input type="checkbox" name="options[color][]" value="blue" />
 * 
 */

class Gek_Input {

	protected $_field_data			= array(); // 存放字段的规则、数据等内部使用的信息
	protected $_config_rules		= array(); // 构造函数设置的验证规则
	protected $_error_array			= array(); // 存放某个字段的错误信息
	protected $_error_prefix		= '<p>';
	protected $_error_suffix		= '</p>';    
    // 设置每一条验证规则的错误信息
    protected $_error_messages = array(
        'required'			=> "必填项",
        'regex_match'		=> "格式不正确",
        'matches'			=> "%s和%s取值不一致",
        'min_length'        => "%s最少%s个字符",
        'max_length'		=> "%s最多%s个字符",
        'exact_length'		=> "%s只能有%s个字符",
        'valid_email'		=> "请输入一个正确的Email地址",
        'valid_emails'		=> "请确保所有的Email地址都是正确的",
        'valid_url'			=> "请输入一个正确的链接地址",
        'valid_ip'			=> "请输入一个正确的IP地址",
        'valid_base64'      => "请输入一个正确的Base64编码",
        'valid_id'          => "请输入正确的身份证号",
        'valid_zipcode'     => "请输入正确的邮政编码",
        'valid_option'      => "请输入正确的选项",
        'valid_money'       => "请输入正确的金额",
        'valid_date'        => "请输入正确的日期(CCYY-MM-DD)",
        'valid_time'        => "请输入正确的时间(CCYY-MM-DD HH:MM:SS)",
        'valid_daterange'   => "请输入正确的日期范围",
        'alpha'				=> "只能输入字母",
        'alpha_numeric'		=> "只能输入字母、数字",
        'alpha_dash'		=> "只能输入字母、数字、-、_",
        'numeric'			=> "只能输入数值",
        'is_numeric'		=> "只能输入数值",
        'integer'			=> "只能输入整数",
        'decimal'			=> "只能输入数值",
        'is_natural'		=> "只能输入大于等于0的整数",
        'is_natural_no_zero'=> "只能输入大于0的整数",
        'greater_than'		=> "%s必须大于%s",
        'less_than'			=> "%s必须小于%s",
        'is_chinese'        => "请输入中文汉字"
    );
    
    protected $source;  //数据源：$_GET $_POST $_REQUEST $_COOKIE
    protected $caller;  //使用这个类的对象，用于在对象中寻找需要的回调函数
    
    /**
     * 输入类构造函数。
     * @param type $rules   验证/处理规则，数组格式。
     * @param type $source  输入数据源: post get request cookie
     * 
     * $rules可以是：
     * array(
               array(
                     'field'   => 'username', 
                     'label'   => 'Username', 
                     'rules'   => 'required'
                  ), ... )
     * 还可以分组，此时run()方法需要在参数中指定分组名：run(group);
     *  array(
                 'signup' => array(
                                    array(
                                            'field' => 'username',
                                            'label' => 'Username',
                                            'rules' => 'required'
                                         ),...),
     *           'email' => array(
                                    array(
                                            'field' => 'emailaddress',
                                            'label' => 'EmailAddress',
                                            'rules' => 'required|valid_email'
                                         ),...)
     *  ）
     */
    public function __construct($rules = array(), $source = 'post', &$caller=NULL)
	{
		$this->_config_rules = $rules;
        $this->caller = $caller;
        
        switch(strtoupper($source)) {
            case 'GET':
                $this->source = &$_GET;
                break;
            case 'POST':
                $this->source = &$_POST;
                break;
            case 'REQUEST':
                $this->source = &$_REQUEST;
                break;
            case 'COOKIE':
                $this->source = &$_COOKIE;
                break;
            default:
                $this->source = &$$_POST;
        }
        
		// Set the character encoding in MB.
		if (function_exists('mb_internal_encoding'))
		{
			mb_internal_encoding('UTF-8');
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Set Rules 设置验证规则
	 *
	 * This function takes an array of field names and validation
	 * rules as input, validates the info, and stores it
	 *
	 * @param	mixed  $field 表单字段名(string)，验证规则数组(array)
	 * @param  string  $label 表单字段说明
     * @param  mixed   $rules 验证规则，可以为空，则不进行验证，只获取。
	 * @return	void
     * 
     * 验证规则： 
     * eg. 'required|min_length[5]|max_length[12]|is_unique[users.username]
     * 注意：每一条规则最多一个参数。
     * 
     * 带处理的规则：
     * 'trim|required|matches[passconf]|md5'
     * 说明：任何接收一个参数的PHP函数都可以被用作一个规则，比如 htmlspecialchars, trim, MD5等。
     * 注意: 一般会在验证规则之后使用这些处理功能，这样如果发生错误，原数据将会被显示在表单。
     * 
     * 带回调函数的规则：
     * (1)回到函数必须位于使用input类的对象里。
     * (2)在验证规则里，回调函数需要在加一个"callback_"前缀。
     * (3)回调函数的参数：第一个参数是输入字段的取值，第二参数可以是验证规则里传递的参数，例如: "callback_foo[bar]"。
     * (4)回调函数返回值：验证通过返回true,验证失败返回false,如果处理则返回处理之后的字段值（当然也可以原字段值）
     * 
     * 验证规则数组：
     * $config = array( 
     *          array(
                     'field'   => 'username', 
                     'label'   => 'Username', 
                     'rules'   => 'required'
                  ),...);
     * set_rules($config);
	 */
	public function set_rules($field, $label = '', $rules = '')
	{
		// No reason to set rules if we have no POST data
//		if (count($this->source) == 0)
//		{
//			return $this;
//		}

		// If an array was passed via the first parameter instead of indidual string
		// values we cycle through it and recursively call this function.
		if (is_array($field))
		{
			foreach ($field as $row)
			{
				// Houston, we have a problem...
				if ( ! isset($row['field']) OR ! isset($row['rules']))
				{
					continue;
				}

				// If the field label wasn't passed we use the field name
				$label = ( ! isset($row['label'])) ? $row['field'] : $row['label'];

				// Here we go!
				$this->set_rules($row['field'], $label, $row['rules']);
			}
			return $this;
		}

		// No fields? Nothing to do...
		if ( ! is_string($field) OR  ! is_string($rules) OR $field == '')
		{
			return $this;
		}

		// If the field label wasn't passed we use the field name
		$label = ($label == '') ? $field : $label;

		// Is the field name an array?  We test for the existence of a bracket "[" in
		// the field name to determine this.  If it is an array, we break it apart
		// into its components so that we can fetch the corresponding POST data later
		if (strpos($field, '[') !== FALSE AND preg_match_all('/\[(.*?)\]/', $field, $matches))
		{
			// Note: Due to a bug in current() that affects some versions
			// of PHP we can not pass function call directly into it
			$x = explode('[', $field);
			$indexes[] = current($x);

			for ($i = 0; $i < count($matches['0']); $i++)
			{
				if ($matches['1'][$i] != '')
				{
					$indexes[] = $matches['1'][$i];
				}
			}

			$is_array = TRUE;
		}
		else
		{
			$indexes	= array();
			$is_array	= FALSE;
		}

		// Build our master array
		$this->_field_data[$field] = array(
			'field'				=> $field,
			'label'				=> $label,
			'rules'				=> $rules,
			'is_array'			=> $is_array,
			'keys'				=> $indexes,
			'postdata'			=> NULL,
			'error'				=> ''
		);

		return $this;
	}

	// --------------------------------------------------------------------
	/**
	 * Set Error Message 设置验证规则的错误信息
	 *
	 * Lets users set their own error messages on the fly.  Note:  The key
	 * name has to match the  function name that it corresponds to.
	 *
	 * @access	public
	 * @param	mixed $rule  规则名或错误信息数组。
	 * @param	string $val  错误信息，在错误信息中包含了 %s，将显示表单域的label。
	 * @return	string
     * 
	 */
	public function set_error_message($rule, $val = '')
	{
		if ( ! is_array($rule))
		{
			$rule = array($rule => $val);
		}

		$this->_error_messages = array_merge($this->_error_messages, $rule);

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Set The Error Delimiter
	 * 默认是使用 (<p>) 标签来分隔每条错误信息，以达到分段效果
	 * Permits a prefix/suffix to be added to each error message
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @return	void
	 */
	public function set_error_delimiters($prefix = '<p>', $suffix = '</p>')
	{
		$this->_error_prefix = $prefix;
		$this->_error_suffix = $suffix;

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Error Message
	 *
	 * Gets the error message associated with a particular field
     * 返回指定字段的验证错误信息。
	 *
	 * @access public
	 * @param  string	the field name
     * @param  string $prefix 错误信息的前缀。
     * @param  string $suffix 错误信息的后缀。
	 * @return	string
	 */
	public function error($field = '', $prefix = '', $suffix = '')
	{
		if ( ! isset($this->_field_data[$field]['error']) OR $this->_field_data[$field]['error'] == '')
		{
			return '';
		}

		if ($prefix == '')
		{
			$prefix = $this->_error_prefix;
		}

		if ($suffix == '')
		{
			$suffix = $this->_error_suffix;
		}

		return $prefix.$this->_field_data[$field]['error'].$suffix;
	}

	// --------------------------------------------------------------------

	/**
	 * Error String
	 *
	 * Returns the error messages as a string, wrapped in the error delimiters
	 * 返回验证器送回的所有错误信息。如果没有错误信息，它将返回空字符串。
     * 
	 * @access	public
	 * @param	string
	 * @param	string
	 * @return	str
	 */
	public function error_string($prefix = '', $suffix = '')
	{
		// No errrors, validation passes!
		if (count($this->_error_array) === 0)
		{
			return '';
		}

		if ($prefix == '')
		{
			$prefix = $this->_error_prefix;
		}

		if ($suffix == '')
		{
			$suffix = $this->_error_suffix;
		}

		// Generate the error string
		$str = '';
		foreach ($this->_error_array as $val)
		{
			if ($val != '')
			{
				$str .= $prefix.$val.$suffix."\n";
			}
		}

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Run the Validator
	 *
	 * This function does all the work.
	 *
     * 参数$group指明执行构造函数定义的验证规则数组里哪一组规则。
	 * @return	bool 全部验证成功返回输入数据数组，有一个验证错误则返回false。
	 */
	public function run($group = '')
	{
		 //Do we even have any data to process?  Mm?
//		 if (count($this->source) == 0)
//		 {
//			return FALSE;
//		 }

		// Does the _field_data array containing the validation rules exist?
		// If not, we look to see if they were assigned via a config file
		if (count($this->_field_data) == 0)
		{
			// No validation rules?  We're done...
			if (count($this->_config_rules) == 0)
			{
				return FALSE;
			}

			if ($group != '' AND isset($this->_config_rules[$group]))
			{
				$this->set_rules($this->_config_rules[$group]);
			}
			else
			{
				$this->set_rules($this->_config_rules);
			}

			// We're we able to set the rules correctly?
			if (count($this->_field_data) == 0)
			{
				return FALSE;
			}
		}

		// Cycle through the rules for each field, match the
		// corresponding $_POST item and test for errors
		foreach ($this->_field_data as $field => $row)
		{
			// Fetch the data from the corresponding $_POST array and cache it in the _field_data array.
			// Depending on whether the field name is an array or a string will determine where we get it from.

			if ($row['is_array'] == TRUE)
			{
				$this->_field_data[$field]['postdata'] = $this->_reduce_array($this->source, $row['keys']);
			}
			else
			{
				if (isset($this->source[$field]) AND $this->source[$field] != "")
				{   
					$this->_field_data[$field]['postdata'] = $this->source[$field];
				}
			}
            
			$this->_execute($row, explode('|', $row['rules']), $this->_field_data[$field]['postdata']);
		}

		// Did we end up with any errors?
		$total_errors = count($this->_error_array);

		// No errors, validation passes!
		if ($total_errors == 0)
		{
            $input_data = array();
            foreach($this->_field_data as $name => $value) {
                $input_data[$name] = $value['postdata'];
            }
            
            // 用户没有输入的字段取值为NULL，都转为空串''
            foreach($input_data as $key => $value) { 
                if(is_null($value)) {
                    $input_data[$key] = '';
                    //unset($input_data[$key]);
                }
            }
            
            // 返回数组包含了所有验证字段的取值。
			return $input_data;
		}

		// Validation fails
		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Traverse a multidimensional $_POST array index until the data is found
	 *
	 * @access	private
	 * @param	array
	 * @param	array
	 * @param	integer
	 * @return	mixed
	 */
	protected function _reduce_array($array, $keys, $i = 0)
	{
		if (is_array($array))
		{
			if (isset($keys[$i]))
			{
				if (isset($array[$keys[$i]]))
				{
					$array = $this->_reduce_array($array[$keys[$i]], $keys, ($i+1));
				}
				else
				{
					return NULL;
				}
			}
			else
			{
				return $array;
			}
		}

		return $array;
	}

	// --------------------------------------------------------------------

	/**
	 * Executes the Validation routines
	 *
	 * @access	private
	 * @param	array
	 * @param	array
	 * @param	mixed
	 * @param	integer
	 * @return	mixed
	 */
	protected function _execute($row, $rules, $postdata = NULL, $cycles = 0)
	{
		// If the $_POST data is an array we will run a recursive call
		if (is_array($postdata))
		{
			foreach ($postdata as $key => $val)
			{
				$this->_execute($row, $rules, $val, $cycles);
				$cycles++;
			}

			return;
		}

		// --------------------------------------------------------------------
	
        // If the field is blank, but NOT required, no further tests are necessary
		$callback = FALSE;
        $defvalue = FALSE;
		if ( ! in_array('required', $rules) AND is_null($postdata))
		{
            // does the rule contain a defvalue[n]?
            if(preg_match("/(defvalue(\[.*?\])?)/", implode(' ', $rules), $match)) { 
                $defvalue = TRUE;
            } 
            
			// Before we bail out, does the rule contain a callback?
			if (preg_match("/(callback_\w+(\[.*?\])?)/", implode(' ', $rules), $match))
			{
				$callback = TRUE;
				$rules = (array('1' => $match[1]));
			} else if(!$defvalue) {
				return;
			}
		}

		// --------------------------------------------------------------------

		// Isset Test. Typically this rule will only apply to checkboxes.
		if (is_null($postdata) AND $callback == FALSE AND $defvalue == FALSE)
		{
			if (in_array('isset', $rules, TRUE) OR in_array('required', $rules))
			{
				// Set the message type
				$type = (in_array('required', $rules)) ? 'required' : 'isset';

				if ( ! isset($this->_error_messages[$type]))
				{
					$line = 'The field was not set';				
				}
				else
				{
					$line = $this->_error_messages[$type];
				}

				// Build the error message
				$message = sprintf($line, $row['label']);

				// Save the error message
				$this->_field_data[$row['field']]['error'] = $message;

				if ( ! isset($this->_error_array[$row['field']]))
				{
					$this->_error_array[$row['field']] = $message;
				}
			}
            
            return;
		}

		// --------------------------------------------------------------------

		// Cycle through each rule and run it
		foreach ($rules As $rule)
		{
			$_in_array = FALSE;

			// We set the $postdata variable with the current data in our master array so that
			// each cycle of the loop is dealing with the processed data from the last cycle
			if ($row['is_array'] == TRUE AND is_array($this->_field_data[$row['field']]['postdata']))
			{
				// We shouldn't need this safety, but just in case there isn't an array index
				// associated with this cycle we'll bail out
				if ( ! isset($this->_field_data[$row['field']]['postdata'][$cycles]))
				{
					continue;
				}

				$postdata = $this->_field_data[$row['field']]['postdata'][$cycles];
				$_in_array = TRUE;
			}
			else
			{
				$postdata = $this->_field_data[$row['field']]['postdata'];
			}

			// --------------------------------------------------------------------

			// Is the rule a callback?
			$callback = FALSE;
			if (substr($rule, 0, 9) == 'callback_')
			{
				$rule = substr($rule, 9);
				$callback = TRUE;
			}

			// Strip the parameter (if exists) from the rule
			// Rules can contain a parameter: max_length[5]
			$param = FALSE;
			if (preg_match("/(.*?)\[(.*)\]/", $rule, $match))
			{
				$rule	= $match[1];
				$param	= $match[2];
			}

			// Call the function that corresponds to the rule
			if ($callback === TRUE)
			{
				if ( ! method_exists($this->caller, $rule))
				{
					continue;
				}

				// Run the function and grab the result
				$result = $this->caller->$rule($postdata, $param);

				// Re-assign the result to the master data array
				if ($_in_array == TRUE)
				{
					$this->_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
				}
				else
				{
					$this->_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
				}

				// If the field isn't required and we just processed a callback we'll move on...
				if ( ! in_array('required', $rules, TRUE) AND $result !== FALSE)
				{
					continue;
				}
			}
			else
			{
				if ( ! method_exists($this, $rule))
				{
					// If our own wrapper function doesn't exist we see if a native PHP function does.
					// Users can use any native PHP function call that has one param.
					if (function_exists($rule))
					{
						$result = $rule($postdata);
                        
						if ($_in_array == TRUE)
						{
							$this->_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
						}
						else
						{
							$this->_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
						}
					}
					else
					{
						//log_message('debug', "Unable to find validation rule: ".$rule);
					}

					continue;
				}
                
				$result = $this->$rule($postdata, $param);
                
				if ($_in_array == TRUE)
				{
					$this->_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
				}
				else
				{
					$this->_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
				}
                
			}

			// Did the rule test negatively?  If so, grab the error.
			if ($result === FALSE)
			{
				if ( ! isset($this->_error_messages[$rule]))
				{
                    $line = 'Unable to access an error message corresponding to your field name.';
				}
				else
				{
					$line = $this->_error_messages[$rule];
				}

				// Is the parameter we are inserting into the error message the name
				// of another field?  If so we need to grab its "field label"
				if (isset($this->_field_data[$param]) AND isset($this->_field_data[$param]['label']))
				{
					$param = $this->_field_data[$param]['label'];
				}

				// Build the error message
				$message = sprintf($line, $row['label'], $param);

				// Save the error message
				$this->_field_data[$row['field']]['error'] = $message;

				if ( ! isset($this->_error_array[$row['field']]))
				{
					$this->_error_array[$row['field']] = $message;
				}

				return;
			}
		}
	}

	// --------------------------------------------------------------------
    
     /**
     * 验证失败时，输出错误信息并回显用户输入值。
     * 错误处理：
     *  如果用户输入数据有错误，则需要：
     * （1）回显用户输入数据：在表单元素的value属性值设置一个模板变量，用来显示用户输入值。
     * （2）在表单元素旁提示错误信息：设置一个模板变量，用户显示错误信息。
     *     可以调用error()方法来获得每一个表单元素的错误信息，如果为空，则表示没有错误。
     */
    public function output_error($rules)
    {
        $output = new Gek_Output();
        
        foreach ($rules as $rule) { 
            //获得用户输入的数据，用于回显。
            if(isset($rule['type']) && ($rule['type'] == 'select' ||
                                        $rule['type'] == 'checkbox' ||
                                        $rule['type'] == 'radio')) {
                //检查字段名是否为数组，比如select checkbox的字段名就可能是数组。
                if(strpos($rule['field'],'[') !== false) {
                    $field = rtrim($rule['field'],'[]');
                    //字段取值为数组，则一次get_value()返回一个值。
                    while(($value = $this->get_value($rule['field'])) !== NULL) {
                        if($rule['type'] == 'select') {
                            $output->set($field.'_'.$value.'_echo', 'selected',false);
                        } else if($rule['type'] == 'checkbox') {
                            $output->set($field.'_'.$value.'_echo', 'checked', false);
                        } else if($rule['type'] == 'radio') {
                            $output->set($field.'_'.$value.'_echo', 'checked', false);
                        }
                    } 
                } else if($rule['type'] == 'select') {
                    $value = $this->get_value($rule['field']);
                    $output->set($rule['field'].'_'.$value.'_echo', 'selected');
                } else if($rule['type'] == 'checkbox' || $rule['type'] == 'radio') {
                    $value = $this->get_value($rule['field']);
                    $output->set($rule['field'].'_'.$value.'_echo', 'checked');
                }
            } else {
                $value = $this->get_value($rule['field']);
                $output->set($rule['field'].'_echo', $value);
            }
            
            if(($error = $this->error($rule['field'])) !== '') { //获得验证错误信息
                $output->set(rtrim($rule['field'],'[]').'_error', $error, false);
            } else {
                $output->set(rtrim($rule['field'],'[]').'_error', '', false);
            }
        }
        
        return $output->data();
    }
    
	/**
	 * Get the value from a form 
     * 获得指定字段用户输入值（如果配置了处理规则，则是处理之后的值），包括select、checkbox、radio字段。
	 * 
	 * Permits you to repopulate a form field with the value it was submitted
	 * with, or, if that value doesn't exist, with the default
	 *
     * If the data is an array output them one at a time. 字段取值为数组，则一次返回一个，为空了则返回NULL。
     * E.g: form_input('name[]', get_value('name[]');
     * 
	 * @access	public
	 * @param	string	the field name
	 * @param	string
	 * @return	void
	 */
	public function get_value($field = '', $default = '')
	{
		if ( ! isset($this->_field_data[$field]))
		{
			return $default;
		}

		// If the data is an array output them one at a time.
		//     E.g: form_input('name[]', get_value('name[]');
		if (is_array($this->_field_data[$field]['postdata']))
		{
			return array_shift($this->_field_data[$field]['postdata']);
		}

		return $this->_field_data[$field]['postdata'];
	}

	// --------------------------------------------------------------------

	/**
	 * Set Select  检查$value是不是字段$field的用户输入值。
     * 如果value是用户输入的选项则返回selected="selected"，否则返回''。
     * 如果default=true且用户没有输入选项，则value是默认值，返回selected="selected"
	 *
	 * Enables pull-down lists to be set to the value the user selected in the event of an error
	 * 在表单验证错误时需要返回表单页面让用户重填，回显下拉列表的用户选中的项。
     * 
	 * @access	public
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public function is_selected($field = '', $value = '', $default = FALSE)
	{
		if ( ! isset($this->_field_data[$field]) OR ! isset($this->_field_data[$field]['postdata']))
		{
			if ($default === TRUE AND count($this->_field_data) === 0)
			{
				return ' selected="selected"';
			}
			return '';
		}

		$field = $this->_field_data[$field]['postdata'];

		if (is_array($field))
		{
			if ( ! in_array($value, $field))
			{
				return '';
			}
		}
		else
		{
			if (($field == '' OR $value == '') OR ($field != $value))
			{
				return '';
			}
		}

		return ' selected="selected"';
	}

	// --------------------------------------------------------------------

	/**
	 *
     * 检查$value是不是用户选择的值，如果是则返回 checked="checked，否则返回''
     * 如果default=true且用户没有输入选项，则value是默认值，返回checked="checked
     * 
	 * @access	public
	 * @param	string
	 * @param	string 
	 * @return	string 
	 */
	public function is_checked($field = '', $value = '', $default = FALSE)
	{
		if ( ! isset($this->_field_data[$field]) OR ! isset($this->_field_data[$field]['postdata']))
		{
			if ($default === TRUE AND count($this->_field_data) === 0)
			{
				return ' checked="checked"';
			}
			return '';
		}

		$field = $this->_field_data[$field]['postdata'];

		if (is_array($field))
		{
			if ( ! in_array($value, $field))
			{
				return '';
			}
		}
		else
		{
			if (($field == '' OR $value == '') OR ($field != $value))
			{
				return '';
			}
		}

		return ' checked="checked"';
	}

	// --------------------------------------------------------------------

	/**
	 * Required
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function required($str)
	{
		if ( ! is_array($str))
		{
			return (trim($str) === '') ? FALSE : TRUE;
		}
		else
		{
			return ( ! empty($str));
		}
	}
    
    public function defvalue($str, $default='')
    {
        if ( ! is_array($str))
		{
			$r = (trim($str) === '') ? $default : $str;
		}
		else
		{
			$r = (empty($str)) ? $default : $str;
		}
        return $r;
    }

	// --------------------------------------------------------------------

	/**
	 * Performs a Regular Expression match test.
	 *
	 * @access	public
	 * @param	string
	 * @param	regex
	 * @return	bool
	 */
	public function regex_match($str, $regex)
	{
		if ( ! preg_match($regex, $str))
		{
			return FALSE;
		}

		return  TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Match one field to another
	 * 检测两个表单元素的取值是否相同。
	 * @access	public
	 * @param	string
	 * @param	field
	 * @return	bool
	 */
	public function matches($str, $field)
	{
		if ( ! isset($this->source[$field])) 
		{
			return FALSE;
		}

		$field = $this->source[$field];

		return ($str !== $field) ? FALSE : TRUE;
	}
	

	// --------------------------------------------------------------------

	/**
	 * Minimum Length
	 *
	 * @access	public
	 * @param	string
	 * @param	value
	 * @return	bool
	 */
	public function min_length($str, $val)
	{
		if (preg_match("/[^0-9]/", $val))
		{
			return FALSE;
		}

		if (function_exists('mb_strlen'))
		{
			return (mb_strlen($str) < $val) ? FALSE : TRUE;
		}

		return (strlen($str) < $val) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Max Length
	 *
	 * @access	public
	 * @param	string
	 * @param	value
	 * @return	bool
	 */
	public function max_length($str, $val)
	{
		if (preg_match("/[^0-9]/", $val))
		{
			return FALSE;
		}

		if (function_exists('mb_strlen'))
		{
			return (mb_strlen($str) > $val) ? FALSE : TRUE;
		}

		return (strlen($str) > $val) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Exact Length
	 *
	 * @access	public
	 * @param	string
	 * @param	value
	 * @return	bool
	 */
	public function exact_length($str, $val)
	{
		if (preg_match("/[^0-9]/", $val))
		{
			return FALSE;
		}

		if (function_exists('mb_strlen'))
		{
			return (mb_strlen($str) != $val) ? FALSE : TRUE;
		}

		return (strlen($str) != $val) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Email
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function valid_email($str)
	{
		return ( ! preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Emails
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function valid_emails($str)
	{
		if (strpos($str, ',') === FALSE)
		{
			return $this->valid_email(trim($str));
		}

		foreach (explode(',', $str) as $email)
		{
			if (trim($email) != '' && $this->valid_email(trim($email)) === FALSE)
			{
				return FALSE;
			}
		}

		return TRUE;
	}
    
    public function valid_url($str)
	{
		return ( ! preg_match('/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/', $str)) ? FALSE : TRUE;
	}
    
	// --------------------------------------------------------------------

	/**
	 * Validate IPv4 Address
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function valid_ip($ip)
	{
		$ip_segments = explode('.', $ip);

		// Always 4 segments needed
		if (count($ip_segments) !== 4)
		{
			return FALSE;
		}
		// IP can not start with 0
		if ($ip_segments[0][0] == '0')
		{
			return FALSE;
		}

		// Check each segment
		foreach ($ip_segments as $segment)
		{
			// IP segments must be digits and can not be
			// longer than 3 digits or greater then 255
			if ($segment == '' OR preg_match("/[^0-9]/", $segment) OR $segment > 255 OR strlen($segment) > 3)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

    // --------------------------------------------------------------------

	/**
	 * Valid Base64
	 *
	 * Tests a string for characters outside of the Base64 alphabet
	 * as defined by RFC 2045 http://www.faqs.org/rfcs/rfc2045
	 *
     * 如果表单元素的值包含除了base64 编码字符之外的其他字符，则返回FALSE。
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function valid_base64($str)
	{
		return (bool) ! preg_match('/[^a-zA-Z0-9\/\+=]/', $str);
	}
    
    /**
     * 检查身份证号码
     */
    public function valid_id($str)
    {
        $str = trim($str);
        if ($str != '') {
            $pattern = '/(^([\d]{15}|[\d]{18}|[\d]{17}[xX]{1})$)/';
            return preg_match($pattern, $str) ? TRUE : FALSE;
        }

        return FALSE;
    }
   
    public function valid_zipcode($str)
	{
		return ( ! preg_match('/^\d{6}$/', $str)) ? FALSE : TRUE;
	}
    
    /**
     * chekckbox radio select时是不是输入指定的选项值,不区分大小写。
     * @param type $str  
     * @param type $options 采用逗号分隔的选项列表。
     */       
    public function valid_option($str, $options) 
    {
        $options = explode(',', $options);
        foreach($options as &$option) {
            $option = strtolower(trim($option));
        }
        unset($option);
        
        return in_array(strtolower(trim($str)), $options, false);
    }
    
    /**
     * 检查是否为合法金额 xxx.yy 小数点后最多两位
     * $length 整数部分的最大位数
    */
    public function valid_money($str, $length = 8)
    {
        $str = trim($str);
        if($str != '') {
            $pattern = '/^[0-9]{1,' . $length . '}[.]{0,1}[0-9]{0,2}$/';
            return preg_match($pattern, $str) ? TRUE : FALSE;
        }

        return FALSE;
    }
    
    /**
     * 检查日期格式(年-月-日)
     */
    public function valid_date($str)
    {
        $str = trim($str);
        if (preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $str)) {
            $dateArr = explode('-', $str);
            return (checkdate($dateArr[1], $dateArr[2], $dateArr[0])) ? TRUE : FALSE;
        } else {
            return FALSE;
        }
    }
    
    /**     
     * 检查是否为一个合法的时间格式 ccyy-mm-dd hh:mm:ss
     */
    public function valid_time($time)
    {
        $pattern = '/[\d]{4}-[\d]{1,2}-[\d]{1,2}\s[\d]{1,2}:[\d]{1,2}:[\d]{1,2}/';

        return (bool)preg_match($pattern, $time);
    }
    
    /**
     * 验证开始日期字段值是不是小于结束日期字段值
     * 参数$str   开始日期
     *    $field 结束日期字段名。
     */
    public function valid_daterange($str, $field)
    {
        if ( ! isset($this->source[$field])) 
		{
			return FALSE;
		}

		$field = $this->source[$field];
        
        if ($this->valid_date($str) && $this->valid_date($field)) {
            return ((strtotime($field) - strtotime($str)) >= 0) ? TRUE : FALSE;
        } else {
            return FALSE;
        }
    }
    
	// --------------------------------------------------------------------

	/**
	 * Alpha
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function alpha($str)
	{
		return ( ! preg_match("/^([a-z])+$/i", $str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function alpha_numeric($str)
	{
		return ( ! preg_match("/^([a-z0-9])+$/i", $str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric with underscores and dashes 字母数字-_
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function alpha_dash($str)
	{
		return ( ! preg_match("/^([-a-z0-9_-])+$/i", $str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Numeric
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function numeric($str)
	{
		return (bool)preg_match( '/^[\-+]?[0-9]*\.?[0-9]+$/', $str);

	}

	// --------------------------------------------------------------------

	/**
	 * Is Numeric
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function is_numeric($str)
	{
		return ( ! is_numeric($str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Integer
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function integer($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Decimal number
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function decimal($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $str);
	}

    // --------------------------------------------------------------------

	/**
	 * Is a Natural number  (0,1,2,3, etc.)
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function is_natural($str)
	{
		return (bool) preg_match( '/^[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Is a Natural number, but not a zero  (1,2,3, etc.)
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function is_natural_no_zero($str)
	{
		if ( ! preg_match( '/^[0-9]+$/', $str))
		{
			return FALSE;
		}

		if ($str == 0)
		{
			return FALSE;
		}

		return TRUE;
	}
    
	// --------------------------------------------------------------------

	/**
	 * Greather than
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function greater_than($str, $min)
	{
		if ( ! is_numeric($str))
		{
			return FALSE;
		}
		return $str > $min;
	}

	// --------------------------------------------------------------------

	/**
	 * Less than
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function less_than($str, $max)
	{
		if ( ! is_numeric($str))
		{
			return FALSE;
		}
		return $str < $max;
	}
    
    public function is_chinese($str) {
		return (bool) preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z_]+$/u", $str);
	}
    
	// --------------------------------------------------------------------

	/**
	 * Prep URL: 当URL丢失"http://" 时，添加"http://".
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	public function prep_url($str = '')
	{
		if ($str == 'http://' OR $str == '')
		{
			return '';
		}

		if (substr($str, 0, 7) != 'http://' && substr($str, 0, 8) != 'https://')
		{
			$str = 'http://'.$str;
		}

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Strip Image Tags: 去掉img标签，只保留图片URL
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	public function strip_image_tags($str)
	{
		$str = preg_replace("#<img\s+.*?src\s*=\s*[\"'](.+?)[\"'].*?\>#", "\\1", $str);
		$str = preg_replace("#<img\s+.*?src\s*=\s*(.+?).*?\>#", "\\1", $str);
        return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Convert PHP tags to entities
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	public function encode_php_tags($str)
	{
		return str_replace(array('<?php', '<?PHP', '<?', '?>'),  array('&lt;?php', '&lt;?PHP', '&lt;?', '?&gt;'), $str);
	}
    
    
    public function htmlclean($str)
    {
        return Gek_Security::html_clean($str);
    }
    
    public function tonewline($str) 
    {
        return Gek_Utils::standardize_newlines($str);
    }
    
    public function tab2space($str) 
    {
        return Gek_Utils::tabs_to_spaces($str);
    }
    
    public function rminvchar($str)
    {
        return Gek_Utils::remove_invisible_characters($str, false);
    }
    
    public function substr_utf8($str, $val) 
    {
        if($str == '') {
            return $str;
        }
        
        if($this->max_length($str, $val)) {
            return $str;
        } else {
            return mb_substr($str, 0, (int)$val);
        }
    }
}
