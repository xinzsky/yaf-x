<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


// 分页类：用于生成分页。



class Gek_Pagination 
{
    const PN = ':pn:';
    
	private $base_url			= ''; // 分页链接的URL，page number/offset部分用 :pn: 表示。
	private $pn                 = self::PN; // 分页链接URL中页码或偏移的变量,会用实际值来替换。
    private $prefix				= ''; // $prefix $pageno $suffix
	private $suffix				= ''; // 
    private $first_url			= ''; // Alternative URL for the First Page. (第一页链接的URL，第一页URL可以和其他页的不同)

	private $total_rows			=  0; // 结果总数
	private $per_page			= 10; // 每页结果数
	private $num_links			=  5; // 当前页码的前面和后面的“数字”链接的数量。
	private $cur_page			=  0; // 当前页码或offset
	private $use_page_numbers	= FALSE; // Use page number for segment instead of offset
	private $first_link			= '第一页'; // 如果不想显示，可以设置为false
	private $next_link			= '下一页';
	private $prev_link			= '上一页';
	private $last_link			= '最后一页';
	
	private $full_tag_open		= ''; //用来包含分页的标签。
	private $full_tag_close		= ''; 
	private $first_tag_open		= ''; //用来包含第一页的标签。
	private $first_tag_close	= '&nbsp;';
	private $last_tag_open		= '&nbsp;'; //用来包含最后一页的标签。
	private $last_tag_close		= '';
	private $cur_tag_open		= '&nbsp;<strong>';//用来包含“当前页”链接的标签
	private $cur_tag_close		= '</strong>';
	private $next_tag_open		= '&nbsp;'; //用来包含下一页的标签。
	private $next_tag_close		= '&nbsp;';
	private $prev_tag_open		= '&nbsp;'; //用来包含上一页的标签。
	private $prev_tag_close		= '';
	private $num_tag_open		= '&nbsp;'; // 用来包含'数字'链接的标签
	private $num_tag_close		= '';

	private $display_pages		= TRUE; // 如果你不想显示“数字”链接（比如只显示 “上一页” 和 “下一页”链接）可以设为false
	private $anchor_class		= '';   //给链接添加 CSS 类

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	initialization parameters
	 */
	public function __construct($params = array())
	{
		if (count($params) > 0)
		{
			$this->init($params);
		}

		if ($this->anchor_class != '')
		{
			$this->anchor_class = 'class="'.$this->anchor_class.'" ';
		}

	}

	// --------------------------------------------------------------------

	/**
	 * Initialize Preferences
	 *
	 * @access	public
	 * @param	array	initialization parameters
	 * @return	void
	 */
	public function init($params = array())
	{
		if (count($params) > 0)
		{
			foreach ($params as $key => $val)
			{
				if (isset($this->$key))
				{
					$this->$key = $val;
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Generate the pagination links
	 *
	 * @access	public
	 * @return	string
	 */
	public function make()
	{
		// If our item count or per-page total is zero there is no need to continue.
		if ($this->total_rows == 0 OR $this->per_page == 0)
		{
			return '';
		}

		// Calculate the total number of pages
		$num_pages = ceil($this->total_rows / $this->per_page);

		// Is there only one page? Hm... nothing more to do here then.
		if ($num_pages == 1)
		{
			return '';
		}

		// Set the base page index for starting page number
		if ($this->use_page_numbers)
		{
			$base_page = 1;
		}
		else
		{
			$base_page = 0;
		}
		
		// Set current page to 1 if using page numbers instead of offset
		if ($this->use_page_numbers AND $this->cur_page == 0)
		{
			$this->cur_page = $base_page;
		}

		$this->num_links = (int)$this->num_links;
		if ($this->num_links < 1)
		{
			$this->num_links = 5;
		}

		if ( ! is_numeric($this->cur_page))
		{
			$this->cur_page = $base_page;
		}

		// Is the page number beyond the result range?
		// If so we show the last page
		if ($this->use_page_numbers)
		{
			if ($this->cur_page > $num_pages)
			{
				$this->cur_page = $num_pages;
			}
		}
		else
		{
			if ($this->cur_page > $this->total_rows)
			{
				$this->cur_page = ($num_pages - 1) * $this->per_page;
			}
		}

		$uri_page_number = $this->cur_page;
		
		if ( ! $this->use_page_numbers)
		{
			$this->cur_page = floor(($this->cur_page/$this->per_page) + 1);
		}

		// Calculate the start and end numbers. These determine
		// which number to start and end the digit links with
		$start = (($this->cur_page - $this->num_links) > 0) ? $this->cur_page - ($this->num_links - 1) : 1;
		$end   = (($this->cur_page + $this->num_links) < $num_pages) ? $this->cur_page + $this->num_links : $num_pages;

		// And here we go...
		$output = '';

		// Render the "First" link
		if  ($this->first_link !== FALSE AND $this->cur_page > ($this->num_links + 1))
		{
            $link_url = str_replace($this->pn, $base_page, $this->base_url);
			$first_url = ($this->first_url == '') ? $link_url : $this->first_url;
			$output .= $this->first_tag_open.'<a '.$this->anchor_class.'href="'.$first_url.'">'.$this->first_link.'</a>'.$this->first_tag_close;
		}

		// Render the "previous" link
		if  ($this->prev_link !== FALSE AND $this->cur_page != 1)
		{
			if ($this->use_page_numbers)
			{
				$i = $uri_page_number - 1;
			}
			else
			{
				$i = $uri_page_number - $this->per_page;
			}

			if ($i == 0 && $this->first_url != '')
			{
				$output .= $this->prev_tag_open.'<a '.$this->anchor_class.'href="'.$this->first_url.'">'.$this->prev_link.'</a>'.$this->prev_tag_close;
			}
			else
			{
				$i = ($i == 0) ? '' : $this->prefix.$i.$this->suffix;
                $link_url = str_replace($this->pn, $i, $this->base_url);
				$output .= $this->prev_tag_open.'<a '.$this->anchor_class.'href="'.$link_url.'">'.$this->prev_link.'</a>'.$this->prev_tag_close;
			}

		}

		// Render the pages
		if ($this->display_pages !== FALSE)
		{
			// Write the digit links
			for ($loop = $start -1; $loop <= $end; $loop++)
			{
				if ($this->use_page_numbers)
				{
					$i = $loop;
				}
				else
				{
					$i = ($loop * $this->per_page) - $this->per_page;
				}

				if ($i >= $base_page)
				{
					if ($this->cur_page == $loop)
					{
						$output .= $this->cur_tag_open.$loop.$this->cur_tag_close; // Current page
					}
					else
					{
						$n = ($i == $base_page) ? '' : $i;

						if ($n == '' && $this->first_url != '')
						{
							$output .= $this->num_tag_open.'<a '.$this->anchor_class.'href="'.$this->first_url.'">'.$loop.'</a>'.$this->num_tag_close;
						}
						else
						{
							$n = ($n == '') ? '' : $this->prefix.$n.$this->suffix;
                            $link_url = str_replace($this->pn, $n, $this->base_url);
							$output .= $this->num_tag_open.'<a '.$this->anchor_class.'href="'.$link_url.'">'.$loop.'</a>'.$this->num_tag_close;
						}
					}
				}
			}
		}

		// Render the "next" link
		if ($this->next_link !== FALSE AND $this->cur_page < $num_pages)
		{
			if ($this->use_page_numbers)
			{
				$i = $this->cur_page + 1;
			}
			else
			{
				$i = ($this->cur_page * $this->per_page);
			}
            
            $link_url = str_replace($this->pn, $this->prefix.$i.$this->suffix, $this->base_url);
			$output .= $this->next_tag_open.'<a '.$this->anchor_class.'href="'.$link_url.'">'.$this->next_link.'</a>'.$this->next_tag_close;
		}

		// Render the "Last" link
		if ($this->last_link !== FALSE AND ($this->cur_page + $this->num_links) < $num_pages)
		{
			if ($this->use_page_numbers)
			{
				$i = $num_pages;
			}
			else
			{
				$i = (($num_pages * $this->per_page) - $this->per_page);
			}
            
            $link_url = str_replace($this->pn, $this->prefix.$i.$this->suffix, $this->base_url);
			$output .= $this->last_tag_open.'<a '.$this->anchor_class.'href="'.$link_url.'">'.$this->last_link.'</a>'.$this->last_tag_close;
		}

		// Kill double slashes.  Note: Sometimes we can end up with a double slash
		// in the penultimate link so we'll kill all double slashes.
		$output = preg_replace("#([^:])//+#", "\\1/", $output);

		// Add the wrapper HTML if exists
		$output = $this->full_tag_open.$output.$this->full_tag_close;

		return $output;
	}
    
    // url中页码必须用 ':pn:' 表示。
    public static function pgbutton($total, $cur_page, $per_page, $url) 
    {
        $pgbutton = array();
        $pn = self::PN;
        
        if($total <= 0 || $per_page <= 0 || empty($url)) {
            return $pgbutton;
        }
        
        $pgbutton['total'] = ($total % $per_page == 0) ? (int)($total/$per_page) : (int)($total/$per_page) + 1;
        if($cur_page <= 0 || $cur_page > $pgbutton['total']) {
            $cur_page = 1;
        }
        $pgbutton['cur'] = $cur_page;

        if($pgbutton['cur'] > 1) {
            $pgbutton['prev'] = str_replace($pn, ($pgbutton['cur']-1), $url);
        }
        
        if($pgbutton['cur']+1 <= $pgbutton['total']) {
            $pgbutton['next'] = str_replace($pn, ($pgbutton['cur']+1), $url);
        }
        
        return $pgbutton;
    }
    
    // url中页码必须用 ':pn:' 表示。
    public static function pgparams($total, $cur_page, $per_page, $url, $first_url)
    {
        if($total <= 0 || $per_page <= 0 || empty($url)) {
            return array();
        }
        
         // 设置分页参数
        $pg_params = array(
             'base_url' => $url,
             'first_url' => $first_url,
             'total_rows' => (int)$total,
             'cur_page' => $cur_page,
             'per_page' => $per_page,
             'use_page_numbers' => true,
             'num_links' =>  5,
             'first_link' => false,
             'last_link' =>  false,
             'next_link' => '下一页<span class="pagearrow">&gt;</span>',
             'prev_link' => '<span class="pagearrow">&lt;</span>上一页',
             'full_tag_open' => '<div class="g-page g-page-sm g-page-sr">',
             'full_tag_close' => '</div>',
             'first_tag_open' => '',
             'first_tag_close' => '',
             'last_tag_open' => '',
             'last_tag_close' => '',
             'cur_tag_open' => '<span class="current"><a href="#">',
             'cur_tag_close' => '</a></span>',
             'next_tag_open' => '<span class="last pagenext">',
             'next_tag_close' => '</span>',
             'prev_tag_open' => '<span class="first pageprev">',
             'prev_tag_close' => '</span>',
             'num_tag_open' => '',
             'num_tag_close' => ''
             );
       
        return $pg_params;
    }
}
