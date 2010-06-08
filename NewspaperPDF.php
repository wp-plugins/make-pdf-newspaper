<?php
/**
 * Newspaper PDF class. Create PDFs in columnar format.
 * This class extends Nicola Asuni's TCPDF. The code is based on the multiple column example (example number 10: http://www.tecnick.com/public/code/cp_dpage.php?aiocp_dp=tcpdf_examples).
 * Note on the license: TCPDF (and the example code I based this class on) is licensed under the LGPL which allows copies and changes to the code to be licensed as GPL. If you modify this file, you have to keep the GPL license.
 * @abstract NewspaperPDF - Create PDFs in columnar format
 * @author Keyvan Minoukadeh (code based on Nicola Asuni's multiple column example)
 * @license http://www.gnu.org/licenses/gpl.html GPL
 */
 
/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class NewspaperPDF extends TCPDF
{
	//number of columns
	protected $ncols = 2;
	
	// column width
	protected $colwidth = 89;
	
	//current column
	protected $col = 0;
	
	//page y
	protected $pageY;
	
	//first page y
	protected $firstPageY;
	
	// number of stories
	protected $storyCount = 0;
	
	protected $newspaperHtml = '';
	
	// indent level - used when starting new column
	protected $indent = 0;
	
	// right indent level - used when starting a new column
	protected $rIndent = 0;
	
	// if true, element content must not be split across more than one page/column
	protected $curNoBreakElement = '';
	
	// a copy of the generated dom tree (used to restore values that have been overwritten in writeHTML())
	protected $domCopy = null;
	
	protected $rolledback = false;
	
	protected $enableSmartPageBreaks = true;
	
	protected $coverImage = '';
	
	//Set cover image for PDF - pass path to image
	public function setCoverImage($img)
	{
		$this->coverImage = $img;
	}
	
	//Set position at a given column
	public function SetCol($col)
	{
		//echo "start setcol $col... indent: {$this->indent} X: {$this->x} lMargin: {$this->lMargin} rMargin: {$this->rMargin}\n";
		$this->col = $col;
		// space between columns
		if ($this->ncols > 1) {
			$column_space = round((float)($this->w - $this->original_lMargin - $this->original_rMargin - ($this->ncols * $this->colwidth)) / ($this->ncols - 1));
		} else {
			$column_space = 0;
		}
		// X position of the current column
		if ($this->rtl) {
			$x = $this->w - $this->original_rMargin - ($col * ($this->colwidth + $column_space));
			$this->SetRightMargin($this->w - $x);
			$this->SetLeftMargin($x - $this->colwidth);
		} else {
			$x = $this->original_lMargin + ($col * ($this->colwidth + $column_space));
			if ($this->indent > 0) {
				$this->SetLeftMargin($x + ($this->listindent * $this->indent));
				$this->SetRightMargin($this->w - $x - $this->colwidth + ($this->listindent * $this->rIndent));
			} else {
				$this->SetLeftMargin($x);
				$this->SetRightMargin($this->w - $x - $this->colwidth);
			}
		}
		//$this->x = $x;
		$this->x = $x + $this->cMargin; // use this for html mode
		if ($this->indent > 0) $this->x += ($this->listindent * $this->indent);
		if ($col > 0 && $this->getPage() == 1) {
			$this->y = $this->firstPageY;
		} else {
			$this->y = $this->pageY;
		}
		//echo "end setcol $col... indent: {$this->indent} X: {$this->x} lMargin: {$this->lMargin} rMargin: {$this->rMargin}\n\n";
	}
	
	//Method accepting or not automatic page break
	public function AcceptPageBreak()
	{
		if($this->col < ($this->ncols - 1)) {
			//Go to next column
			$this->SetCol($this->col + 1);
			//Keep on page
			return false;
		} else {
			$this->AddPage();
			//Go back to first column
			$this->SetCol(0);
			//Page break
			return false;
		}
	}
	
	public function addItem($title, $content, $date=0)
	{
		$html = '<h1 style="page-break-inside: avoid">'.$title.'</h1>';
		if ((int)$date != 0) {
			$html .= '<span>'.date('M j, Y h:iA', $date).'</span><br />';
		}
		$html .= $content.'<hr />';
		$this->newspaperHtml .= $html;
		$this->storyCount++;
	}
	
	public function getNewspaperLineStyle()
	{
		return array('width' => 1.4 / $this->getScaleFactor(), 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(150, 150, 150));
	}
	
	public function getHeadlineSize()
	{
		return 18;
	}
	
	public function makePdf()
	{
		$this->AddPage();
		// check if we need to add cover
		if ($this->coverImage != '') {
			$this->setPrintFooter(false);
			$dim = $this->getPageDimensions();
			$w = (int)$dim['wk']; // 210
			$h = (int)$dim['hk']; // 297
			//var_dump($w, $h);exit;
			// temporarily disable auto page break (full image will trigger page/column break)
			$_autobreak = $this->AutoPageBreak;
			$_botMargin = $this->bMargin;
			$this->SetAutoPageBreak(false, 0);
			// add image
			$this->Image($this->coverImage, 0, 0, $w, $h, '', '', '', false, 300, '', false, false, 0);
			// restore page break setting
			$this->startPageGroup();
			$this->AddPage();
			$this->SetAutoPageBreak($_autobreak, $_botMargin);
			$this->setPrintFooter(true);
			$this->y = $this->pageY;
		} else { // no cover needed so assume header image/title has been printed
			$this->SetY($this->firstPageY);
		}
		//$this->storyBody($this->newspaperHtml);
		// Font
		//$this->SetFont('dejavuserifcondensed', '', 8);
		$this->SetFontSize(8);
		// Output text in a column
		$this->MultiCell($this->colwidth, 5, $this->newspaperHtml, 0, 'L', 0, 1, '', '', true, 0, true);
	}
	
	
	//////////////////////////////////////////////////////////////////////////////////////////////
	//  The functions below have been copied from the TCPDF class (tcpdf.php) and modified (very slightly)
	//  It's probably a good idea to compare the functions below with those in newer releases of TCPDF to 
	//  see if there are any significate changes that need to be copied over here.
	//////////////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * This method is used to render the page header.
	 * It is automatically called by AddPage() and could be overwritten in your own inherited class.
	 * @access public
	 */
	public function Header() {
		if ($this->getPage() > 1) return;
		$ormargins = $this->getOriginalMargins();
		$headerfont = $this->getHeaderFont();
		$headerdata = $this->getHeaderData();
		$this->pageY = $this->GetY();
		if (($headerdata['logo']) AND ($headerdata['logo'] != K_BLANK_IMAGE)) {
			$this->Image($headerdata['logo'], $this->GetX(), $this->getHeaderMargin(), $headerdata['logo_width']);
			$imgy = $this->getImageRBY();
		} else {
			$imgy = $this->GetY();
		}
		$cell_height = round(($this->getCellHeightRatio() * $headerfont[2]) / $this->getScaleFactor(), 2);
		// set starting margin for text data cell
		if ($this->getRTL()) {
			$header_x = $ormargins['right'] + ($headerdata['logo_width'] * 1.1);
		} else {
			$header_x = $ormargins['left'] + ($headerdata['logo_width'] * 1.1);
		}
		$this->SetTextColor(0, 0, 0);
		// header title
		$this->SetFont($headerfont[0], 'B', $headerfont[2] + 1);
		$this->SetX($header_x);			
		//$this->Cell(0, $cell_height, $headerdata['title'], 0, 1, '', 0, '', 0);
		$_cellhr = $this->getCellHeightRatio();
		$this->setCellHeightRatio(1.2);
		$this->MultiCell(0, 0, $headerdata['title'], 0, '', 0, 1, '', '', true, 0, false);
		// header string
		$this->SetFont($headerfont[0], $headerfont[1], 11);
		$this->SetX($header_x);
		$this->MultiCell(0, 0, $headerdata['string'], 0, '', 0, 1, '', '', true, 0, true);
		$this->setCellHeightRatio($_cellhr);
		// print an ending header line
		$this->SetLineStyle(array('width' => 10 / $this->getScaleFactor(), 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
		if ($headerdata['logo']) {
			$this->SetY((2.835 / $this->getScaleFactor()) + $imgy - 3);
		} else {
			$this->SetY($this->GetY() - 3);
		}
		//var_dump($this->getY());exit;
		//$this->SetY((2.835 / $this->getScaleFactor()) + max($imgy, $this->GetY()));
		//$this->SetY(2);
		if ($this->getRTL()) {
			$this->SetX($ormargins['right']);
		} else {
			$this->SetX($ormargins['left']);
		}
		// draw header underline
		//if ($headerdata['logo']) {
			$this->Cell(0, 0, '', 'B', 0, 'C');
		//}
		$this->firstPageY = $this->GetY() + 11;
		
	}

	/**
	 * This method is used to render the page footer. 
	 * It is automatically called by AddPage() and could be overwritten in your own inherited class.
	 * @access public
	 */
	public function Footer() {				
		$cur_y = $this->GetY();
		$ormargins = $this->getOriginalMargins();
		$this->SetTextColor(0, 0, 0);
		if (empty($this->pagegroups)) {
			$pagenumtxt = $this->getAliasNumPage();
		} else {
			$pagenumtxt = $this->getPageNumGroupAlias();
		}	
		$this->SetY($cur_y);
		//Print page number
		$this->Cell(0, 0, $pagenumtxt, 0, 0, 'C');
	}
	
	/**
	 * Process opening tags.
	 * @param array $dom html dom array 
	 * @param int $key current element id
	 * @param boolean $cell if true add the default cMargin space to each new line (default false).
	 * @access protected
	 */
	protected function openHTMLTagHandler(&$dom, &$key, $cell=false) {
		$tag = $dom[$key];
		$parent = $dom[($dom[$key]['parent'])];
		$firstorlast = ($key == 1);
		// check for text direction attribute
		if (isset($tag['attribute']['dir'])) {
			$this->tmprtl = $tag['attribute']['dir'] == 'rtl' ? 'R' : 'L';
		} else {
			$this->tmprtl = false;
		}
		//Opening tag
		switch($tag['value']) {
			case 'table': {
				$cp = 0;
				$cs = 0;
				$dom[$key]['rowspans'] = array();
				if (!$this->empty_string($dom[$key]['thead'])) {
					// set table header
					$this->thead = $dom[$key]['thead'];
				}
				if (isset($tag['attribute']['cellpadding'])) {
					$cp = $this->getHTMLUnitToUnits($tag['attribute']['cellpadding'], 1, 'px');
					$this->oldcMargin = $this->cMargin;
					$this->cMargin = $cp;
				}
				if (isset($tag['attribute']['cellspacing'])) {
					$cs = $this->getHTMLUnitToUnits($tag['attribute']['cellspacing'], 1, 'px');
				}
				$this->checkPageBreak((2 * $cp) + (2 * $cs) + $this->lasth);
				break;
			}
			case 'tr': {
				// array of columns positions
				$dom[$key]['cellpos'] = array();
				break;
			}
			case 'hr': {
				$this->addHTMLVertSpace(1, $cell, '', $firstorlast, $tag['value'], false);
				$this->htmlvspace = 0;
				$wtmp = $this->w - $this->lMargin - $this->rMargin;
				if ((isset($tag['attribute']['width'])) AND ($tag['attribute']['width'] != '')) {
					$hrWidth = $this->getHTMLUnitToUnits($tag['attribute']['width'], $wtmp, 'px');
				} else {
					$hrWidth = $wtmp;
				}
				$x = $this->GetX();
				$y = $this->GetY();
				$prevlinewidth = $this->GetLineWidth();
				$this->Line($x, $y, $x + $hrWidth, $y, $this->getNewspaperLineStyle());
				$this->SetLineWidth($prevlinewidth);
				$this->addHTMLVertSpace(1, $cell, '', !isset($dom[($key + 1)]), $tag['value'], false);
				break;
			}
			case 'a': {
				if (array_key_exists('href', $tag['attribute'])) {
					$this->HREF['url'] = $tag['attribute']['href'];
				}
				$this->HREF['color'] = $this->htmlLinkColorArray;
				$this->HREF['style'] = $this->htmlLinkFontStyle;
				if (array_key_exists('style', $tag['attribute'])) {
					// get style attributes
					preg_match_all('/([^;:\s]*):([^;]*)/', $tag['attribute']['style'], $style_array, PREG_PATTERN_ORDER);
					$astyle = array();
					while (list($id, $name) = each($style_array[1])) {
						$name = strtolower($name);
						$astyle[$name] = trim($style_array[2][$id]);
					}
					if (isset($astyle['color'])) {
						$this->HREF['color'] = $this->convertHTMLColorToDec($astyle['color']);
					}
					if (isset($astyle['text-decoration'])) {
						$this->HREF['style'] = '';
						$decors = explode(' ', strtolower($astyle['text-decoration']));
						foreach ($decors as $dec) {
							$dec = trim($dec);
							if (!$this->empty_string($dec)) {
								if ($dec{0} == 'u') {
									$this->HREF['style'] .= 'U';
								} elseif ($dec{0} == 'l') {
									$this->HREF['style'] .= 'D';
								}
							}
						}
					}
				}		
				break;
			}
			case 'img': {
				if (isset($tag['attribute']['src'])) {
					// replace relative path with real server path
					if ($tag['attribute']['src'][0] == '/') {
						$tag['attribute']['src'] = $_SERVER['DOCUMENT_ROOT'].$tag['attribute']['src'];
					}
					$tag['attribute']['src'] = urldecode($tag['attribute']['src']);
					$tag['attribute']['src'] = str_replace(K_PATH_URL, K_PATH_MAIN, $tag['attribute']['src']);
					if (!isset($tag['attribute']['width'])) {
						$tag['attribute']['width'] = 0;
					}
					if (!isset($tag['attribute']['height'])) {
						$tag['attribute']['height'] = 0;
					}
					//if (!isset($tag['attribute']['align'])) {
						// the only alignment supported is "bottom"
						// further development is required for other modes.
						$tag['attribute']['align'] = 'bottom';
					//} 
					switch($tag['attribute']['align']) {
						case 'top': {
							$align = 'T';
							break;
						}
						case 'middle': {
							$align = 'M';
							break;
						}
						case 'bottom': {
							$align = 'B';
							break;
						}
						default: {
							$align = 'B';
							break;
						}
					}
					$fileinfo = pathinfo($tag['attribute']['src']);
					$type = '';
					//return;
					if (isset($fileinfo['extension']) AND (!$this->empty_string($fileinfo['extension']))) {
						$type = strtolower($fileinfo['extension']);
					}
					if ($type != 'jpg' && $type != 'png' && $type != 'gif' && strpos($tag['attribute']['src'],"http://chart.apis.google.com") === false) {
						return;
					}
					$this->Ln(0.5, $cell);
					$prevy = $this->y;
					$xpos = $this->GetX();
					if (isset($dom[($key - 1)]) AND ($dom[($key - 1)]['value'] == ' ')) {
						if ($this->rtl) {
							$xpos += $this->GetStringWidth(' ');
						} else {
							$xpos -= $this->GetStringWidth(' ');
						}
					}
					$imglink = '';
					if (isset($this->HREF['url']) AND !$this->empty_string($this->HREF['url'])) {
						$imglink = $this->HREF['url'];
						if ($imglink{0} == '#') {
							// convert url to internal link
							$page = intval(substr($imglink, 1));
							$imglink = $this->AddLink();
							$this->SetLink($imglink, 0, $page);
						}
					}
					$border = 0;
					if (isset($tag['attribute']['border']) AND !empty($tag['attribute']['border'])) {
						// currently only support 1 (frame) or a combination of 'LTRB'
						$border = $tag['attribute']['border'];
					}
					
					if (($type == 'eps') OR ($type == 'ai')) {
						//$this->ImageEps($tag['attribute']['src'], $xpos, $this->GetY(), $this->pixelsToUnits($tag['attribute']['width']), $this->pixelsToUnits($tag['attribute']['height']), $imglink, true, $align, '', $border);
					} else {
					
						$this->Image($tag['attribute']['src'], $xpos, $this->GetY(), $this->colwidth, 0, '', '', $align, false, 300, '', false, false, $border);
						//$this->Image($tag['attribute']['src'], $xpos, $this->GetY(), $this->pixelsToUnits($tag['attribute']['width']), $this->pixelsToUnits($tag['attribute']['height']), '', $imglink, $align, false, 300, '', false, false, $border);
					}
					switch($align) {
						case 'T': {
							$this->y = $prevy;
							break;
						}
						case 'M': {
							$this->y = (($this->img_rb_y + $prevy - ($tag['fontsize'] / $this->k)) / 2) ;
							break;
						}
						case 'B': {
							$this->y = $this->img_rb_y - ($tag['fontsize'] / $this->k);
							break;
						}
					}
				}
				$this->Ln(6, $cell);
				break;
			}
			case 'dl': {
				++$this->listnum;
				$this->addHTMLVertSpace(0, $cell, '', $firstorlast, $tag['value'], false);
				break;
			}
			case 'dt': {
				$this->addHTMLVertSpace(1, $cell, '', $firstorlast, $tag['value'], false);
				break;
			}
			case 'dd': {
				$this->indent++;
				if ($this->rtl) {
					$this->rMargin += $this->listindent;
				} else {
					$this->lMargin += $this->listindent;
				}
				$this->addHTMLVertSpace(1, $cell, '', $firstorlast, $tag['value'], false);
				break;
			}
			case 'ul':
			case 'ol': {
				$this->indent++;
				//$this->addHTMLVertSpace(0, $cell, '', $firstorlast, $tag['value'], false);
				//$this->htmlvspace = 0;
				++$this->listnum;
				if ($tag['value'] == 'ol') {
					$this->listordered[$this->listnum] = true;
				} else {
					$this->listordered[$this->listnum] = false;
				}
				if (isset($tag['attribute']['start'])) {
					$this->listcount[$this->listnum] = intval($tag['attribute']['start']) - 1;
				} else {
					$this->listcount[$this->listnum] = 0;
				}
				if ($this->rtl) {
					$this->rMargin += $this->listindent;
				} else {
					$this->lMargin += $this->listindent;
				}
				//$this->addHTMLVertSpace(0, $cell, '', $firstorlast, $tag['value'], false);
				//$this->htmlvspace = 0;
				$this->Ln(0, true);
				break;
			}
			case 'li': {
				$this->addHTMLVertSpace(1, $cell, '', $firstorlast, $tag['value'], false);
				if ($this->listordered[$this->listnum]) {
					// ordered item
					if (isset($parent['attribute']['type']) AND !$this->empty_string($parent['attribute']['type'])) {
						$this->lispacer = $parent['attribute']['type'];
					} elseif (isset($parent['listtype']) AND !$this->empty_string($parent['listtype'])) {
						$this->lispacer = $parent['listtype'];
					} elseif (isset($this->lisymbol) AND !$this->empty_string($this->lisymbol)) {
						$this->lispacer = $this->lisymbol;
					} else {
						$this->lispacer = '#';
					}
					++$this->listcount[$this->listnum];
					if (isset($tag['attribute']['value'])) {
						$this->listcount[$this->listnum] = intval($tag['attribute']['value']);
					}
				} else {
					// unordered item
					if (isset($parent['attribute']['type']) AND !$this->empty_string($parent['attribute']['type'])) {
						$this->lispacer = $parent['attribute']['type'];
					} elseif (isset($parent['listtype']) AND !$this->empty_string($parent['listtype'])) {
						$this->lispacer = $parent['listtype'];
					} elseif (isset($this->lisymbol) AND !$this->empty_string($this->lisymbol)) {
						$this->lispacer = $this->lisymbol;
					} else {
						$this->lispacer = '!';
					}
				}
				break;
			}
			case 'blockquote': {
				$this->indent++;
				$this->rIndent++;
				//if ($this->rtl) {
					$this->rMargin += $this->listindent; // original
					//$this->original_rMargin += $this->listindent;
				//} else {
					$this->lMargin += $this->listindent; // original
					//$this->original_lMargin += $this->listindent;
				//}
				$this->addHTMLVertSpace(2, $cell, '', $firstorlast, $tag['value'], false);
				$this->Ln(0, true);
				break;
			}
			case 'br': {
				$this->Ln('', $cell);
				break;
			}
			case 'div': {
				$this->addHTMLVertSpace(1, $cell, '', $firstorlast, $tag['value'], false);
				break;
			}
			case 'p': {
				$this->addHTMLVertSpace(2, $cell, '', $firstorlast, $tag['value'], false);
				break;
			}
			case 'pre': {
				$this->addHTMLVertSpace(1, $cell, '', $firstorlast, $tag['value'], false);
				$this->premode = true;
				break;
			}
			case 'sup': {
				$this->SetXY($this->GetX(), $this->GetY() - ((0.7 * $this->FontSizePt) / $this->k));
				break;
			}
			case 'sub': {
				$this->SetXY($this->GetX(), $this->GetY() + ((0.3 * $this->FontSizePt) / $this->k));
				break;
			}
			case 'h1': 
			case 'h2': 
			case 'h3': 
			case 'h4': 
			case 'h5': 
			case 'h6': {
				if ($tag['value'] != 'h1') $this->addHTMLVertSpace(1, $cell, ($tag['fontsize'] * 1.5) / $this->k, $firstorlast, $tag['value'], false);
				// check for page break
				if ($this->rolledback) {
					$this->AcceptPageBreak();
					$this->rolledback = false;
				} else {
					// check for page break style rules
					if ($this->enableSmartPageBreaks && @$tag['style']['page-break-inside'] == 'avoid') {
						$this->startTransaction();
						$this->domCopy = $dom;
						$this->curNoBreakElement = $key;
					}
				}
				break;
			}
			case 'tcpdf': {
				// NOT HTML: used to call TCPDF methods
				if (isset($tag['attribute']['method'])) {
					$tcpdf_method = $tag['attribute']['method'];
					if (method_exists($this, $tcpdf_method)) {
						if (isset($tag['attribute']['params']) AND (!empty($tag['attribute']['params']))) {
							eval('$params = array('.$tag['attribute']['params'].');');
							call_user_func_array(array($this, $tcpdf_method), $params);
						} else {
							$this->$tcpdf_method();
						}
						$this->newline = true;
					}
				}
			}
			default: {
				break;
			}
		}
	}
	
	/**
	 * Process closing tags.
	 * @param array $dom html dom array 
	 * @param int $key current element id
	 * @param boolean $cell if true add the default cMargin space to each new line (default false).
	 * @access protected
	 */
	protected function closeHTMLTagHandler(&$dom, &$key, $cell=false) {
		$tag = $dom[$key];
		$parent = $dom[($dom[$key]['parent'])];
		$firstorlast = ((!isset($dom[($key + 1)])) OR ((!isset($dom[($key + 2)])) AND ($dom[($key + 1)]['value'] == 'marker')));
		// check for page break style rules
		if ($this->enableSmartPageBreaks && $this->curNoBreakElement != '' && @$tag['value'] == $dom[$this->curNoBreakElement]['value']) {
			//echo $tag['value'].'--,';
			// make sure we're not on a new column/page
			if (($this->getPage() > $this->objcopy->getPage()) || ($this->col > $this->objcopy->col)) {
				// oh dear, we are :(
				$key = $this->curNoBreakElement - 1;
				$dom = $this->domCopy;
				$_copy = $this->rollbackTransaction();
				foreach (array_keys(get_object_vars($_copy)) as $_val) {
					$this->$_val = $_copy->$_val;
				}
				$this->curNoBreakElement = '';
				//$this->AcceptPageBreak();
				//var_dump($key, $tag['value']);exit;
				$this->rolledback = true;
				return;
			} else {
				// we're not
				$this->commitTransaction(); 
			}
			$this->curNoBreakElement = '';
		}
		//Closing tag
		switch($tag['value']) {
			case 'tr': {
				$table_el = $dom[($dom[$key]['parent'])]['parent'];
				if(!isset($parent['endy'])) {
					$dom[($dom[$key]['parent'])]['endy'] = $this->y;
					$parent['endy'] = $this->y;
				}
				if(!isset($parent['endpage'])) {
					$dom[($dom[$key]['parent'])]['endpage'] = $this->page;
					$parent['endpage'] = $this->page;
				}
				// update row-spanned cells
				if (isset($dom[$table_el]['rowspans'])) {
					foreach ($dom[$table_el]['rowspans'] as $k => $trwsp) {
						$dom[$table_el]['rowspans'][$k]['rowspan'] -= 1;
						if ($dom[$table_el]['rowspans'][$k]['rowspan'] == 0) {
							if ($dom[$table_el]['rowspans'][$k]['endpage'] == $parent['endpage']) {
								$dom[($dom[$key]['parent'])]['endy'] = max($dom[$table_el]['rowspans'][$k]['endy'], $parent['endy']);
							} elseif ($dom[$table_el]['rowspans'][$k]['endpage'] > $parent['endpage']) {
								$dom[($dom[$key]['parent'])]['endy'] = $dom[$table_el]['rowspans'][$k]['endy'];
								$dom[($dom[$key]['parent'])]['endpage'] = $dom[$table_el]['rowspans'][$k]['endpage'];
							}
						}
					}
					// report new endy and endpage to the rowspanned cells
					foreach ($dom[$table_el]['rowspans'] as $k => $trwsp) {
						if ($dom[$table_el]['rowspans'][$k]['rowspan'] == 0) {
							$dom[$table_el]['rowspans'][$k]['endpage'] = max($dom[$table_el]['rowspans'][$k]['endpage'], $dom[($dom[$key]['parent'])]['endpage']);
							$dom[($dom[$key]['parent'])]['endpage'] = $dom[$table_el]['rowspans'][$k]['endpage'];
							$dom[$table_el]['rowspans'][$k]['endy'] = max($dom[$table_el]['rowspans'][$k]['endy'], $dom[($dom[$key]['parent'])]['endy']);
							$dom[($dom[$key]['parent'])]['endy'] = $dom[$table_el]['rowspans'][$k]['endy'];
						}
					}
					// update remaining rowspanned cells
					foreach ($dom[$table_el]['rowspans'] as $k => $trwsp) {
						if ($dom[$table_el]['rowspans'][$k]['rowspan'] == 0) {
							$dom[$table_el]['rowspans'][$k]['endpage'] = $dom[($dom[$key]['parent'])]['endpage'];
							$dom[$table_el]['rowspans'][$k]['endy'] = $dom[($dom[$key]['parent'])]['endy'];
						}
					}
				}
				$this->setPage($dom[($dom[$key]['parent'])]['endpage']);
				$this->y = $dom[($dom[$key]['parent'])]['endy'];					
				if (isset($dom[$table_el]['attribute']['cellspacing'])) {
					$cellspacing = $this->getHTMLUnitToUnits($dom[$table_el]['attribute']['cellspacing'], 1, 'px');
					$this->y += $cellspacing;
				}				
				$this->Ln(0, $cell);
				$this->x = $parent['startx'];
				// account for booklet mode
				if ($this->page > $parent['startpage']) {
					if (($this->rtl) AND ($this->pagedim[$this->page]['orm'] != $this->pagedim[$parent['startpage']]['orm'])) {
						$this->x += ($this->pagedim[$this->page]['orm'] - $this->pagedim[$parent['startpage']]['orm']);
					} elseif ((!$this->rtl) AND ($this->pagedim[$this->page]['olm'] != $this->pagedim[$parent['startpage']]['olm'])) {
						$this->x += ($this->pagedim[$this->page]['olm'] - $this->pagedim[$parent['startpage']]['olm']);
					}
				}
				break;
			}
			case 'table': {
				// draw borders
				$table_el = $parent;
				if ((isset($table_el['attribute']['border']) AND ($table_el['attribute']['border'] > 0)) 
					OR (isset($table_el['style']['border']) AND ($table_el['style']['border'] > 0))) {
						$border = 1;
				} else {
					$border = 0;
				}
				// fix bottom line alignment of last line before page break
				foreach ($dom[($dom[$key]['parent'])]['trids'] as $j => $trkey) {
					// update row-spanned cells
					if (isset($dom[($dom[$key]['parent'])]['rowspans'])) {
						foreach ($dom[($dom[$key]['parent'])]['rowspans'] as $k => $trwsp) {
							if ($trwsp['trid'] == $trkey) {
								$dom[($dom[$key]['parent'])]['rowspans'][$k]['mrowspan'] -= 1;
							}
							if (isset($prevtrkey) AND ($trwsp['trid'] == $prevtrkey) AND ($trwsp['mrowspan'] >= 0)) {
								$dom[($dom[$key]['parent'])]['rowspans'][$k]['trid'] = $trkey;
							}
						}
					}
					if (isset($prevtrkey) AND ($dom[$trkey]['startpage'] > $dom[$prevtrkey]['endpage'])) {
						$pgendy = $this->pagedim[$dom[$prevtrkey]['endpage']]['hk'] - $this->pagedim[$dom[$prevtrkey]['endpage']]['bm'];
						$dom[$prevtrkey]['endy'] = $pgendy;
						// update row-spanned cells
						if (isset($dom[($dom[$key]['parent'])]['rowspans'])) {
							foreach ($dom[($dom[$key]['parent'])]['rowspans'] as $k => $trwsp) {
								if (($trwsp['trid'] == $trkey) AND ($trwsp['mrowspan'] == 1) AND ($trwsp['endpage'] == $dom[$prevtrkey]['endpage'])) {
									$dom[($dom[$key]['parent'])]['rowspans'][$k]['endy'] = $pgendy;
									$dom[($dom[$key]['parent'])]['rowspans'][$k]['mrowspan'] = -1;
								}
							}
						}
					}
					$prevtrkey = $trkey;
					$table_el = $dom[($dom[$key]['parent'])];
				}
				// for each row
				foreach ($table_el['trids'] as $j => $trkey) {
					$parent = $dom[$trkey];
					// for each cell on the row
					foreach ($parent['cellpos'] as $k => $cellpos) {
						if (isset($cellpos['rowspanid']) AND ($cellpos['rowspanid'] >= 0)) {
							$cellpos['startx'] = $table_el['rowspans'][($cellpos['rowspanid'])]['startx'];
							$cellpos['endx'] = $table_el['rowspans'][($cellpos['rowspanid'])]['endx'];
							$endy = $table_el['rowspans'][($cellpos['rowspanid'])]['endy'];
							$startpage = $table_el['rowspans'][($cellpos['rowspanid'])]['startpage'];
							$endpage = $table_el['rowspans'][($cellpos['rowspanid'])]['endpage'];
						} else {
							$endy = $parent['endy'];
							$startpage = $parent['startpage'];
							$endpage = $parent['endpage'];
						}
						if ($endpage > $startpage) {
							// design borders around HTML cells.
							for ($page=$startpage; $page <= $endpage; ++$page) {
								$this->setPage($page);
								if ($page == $startpage) {
									$this->y = $parent['starty']; // put cursor at the beginning of row on the first page
									$ch = $this->getPageHeight() - $parent['starty'] - $this->getBreakMargin();
									$cborder = $this->getBorderMode($border, $position='start');
								} elseif ($page == $endpage) {
									$this->y = $this->tMargin; // put cursor at the beginning of last page
									$ch = $endy - $this->tMargin;
									$cborder = $this->getBorderMode($border, $position='end');
								} else {
									$this->y = $this->tMargin; // put cursor at the beginning of the current page
									$ch = $this->getPageHeight() - $this->tMargin - $this->getBreakMargin();
									$cborder = $this->getBorderMode($border, $position='middle');
								}
								if (isset($cellpos['bgcolor']) AND ($cellpos['bgcolor']) !== false) {
									$this->SetFillColorArray($cellpos['bgcolor']);
									$fill = true;
								} else {
									$fill = false;
								}
								$cw = abs($cellpos['endx'] - $cellpos['startx']);
								$this->x = $cellpos['startx'];
								// account for margin changes
								if ($page > $startpage) {
									if (($this->rtl) AND ($this->pagedim[$page]['orm'] != $this->pagedim[$startpage]['orm'])) {
										$this->x -= ($this->pagedim[$page]['orm'] - $this->pagedim[$startpage]['orm']);
									} elseif ((!$this->rtl) AND ($this->pagedim[$page]['lm'] != $this->pagedim[$startpage]['olm'])) {
										$this->x += ($this->pagedim[$page]['olm'] - $this->pagedim[$startpage]['olm']);
									}
								}
								// design a cell around the text
								$ccode = $this->FillColor."\n".$this->getCellCode($cw, $ch, '', $cborder, 1, '', $fill, '', 0, true);
								if ($cborder OR $fill) {
									$pagebuff = $this->getPageBuffer($this->page);
									$pstart = substr($pagebuff, 0, $this->intmrk[$this->page]);
									$pend = substr($pagebuff, $this->intmrk[$this->page]);
									$this->setPageBuffer($this->page, $pstart.$ccode."\n".$pend);
									$this->intmrk[$this->page] += strlen($ccode."\n");
								}
							}
						} else {
							$this->setPage($startpage);
							if (isset($cellpos['bgcolor']) AND ($cellpos['bgcolor']) !== false) {
								$this->SetFillColorArray($cellpos['bgcolor']);
								$fill = true;
							} else {
								$fill = false;
							}
							$this->x = $cellpos['startx'];
							$this->y = $parent['starty'];
							$cw = abs($cellpos['endx'] - $cellpos['startx']);
							$ch = $endy - $parent['starty'];
							// design a cell around the text
							$ccode = $this->FillColor."\n".$this->getCellCode($cw, $ch, '', $border, 1, '', $fill, '', 0, true);
							if ($border OR $fill) {
								if (end($this->transfmrk[$this->page]) !== false) {
									$pagemarkkey = key($this->transfmrk[$this->page]);
									$pagemark = &$this->transfmrk[$this->page][$pagemarkkey];
								} elseif ($this->InFooter) {
									$pagemark = &$this->footerpos[$this->page];
								} else {
									$pagemark = &$this->intmrk[$this->page];
								}
								$pagebuff = $this->getPageBuffer($this->page);
								$pstart = substr($pagebuff, 0, $pagemark);
								$pend = substr($pagebuff, $pagemark);
								$this->setPageBuffer($this->page, $pstart.$ccode."\n".$pend);
								$pagemark += strlen($ccode."\n");
							}					
						}
					}					
					if (isset($table_el['attribute']['cellspacing'])) {
						$cellspacing = $this->getHTMLUnitToUnits($table_el['attribute']['cellspacing'], 1, 'px');
						$this->y += $cellspacing;
					}				
					$this->Ln(0, $cell);
					$this->x = $parent['startx'];
					if ($endpage > $startpage) {
						if (($this->rtl) AND ($this->pagedim[$endpage]['orm'] != $this->pagedim[$startpage]['orm'])) {
							$this->x += ($this->pagedim[$endpage]['orm'] - $this->pagedim[$startpage]['orm']);
						} elseif ((!$this->rtl) AND ($this->pagedim[$endpage]['olm'] != $this->pagedim[$startpage]['olm'])) {
							$this->x += ($this->pagedim[$endpage]['olm'] - $this->pagedim[$startpage]['olm']);
						}
					}
				}
				if (isset($parent['cellpadding'])) {
					$this->cMargin = $this->oldcMargin;
				}
				$this->lasth = $this->FontSize * $this->cell_height_ratio;
				if (!$this->empty_string($table_el['thead']) AND !$this->empty_string($this->theadMargin)) {
					// reset table header
					$this->thead = '';
					// restore top margin
					$this->tMargin = $this->theadMargin;
					$this->pagedim[$this->page]['tm'] = $this->theadMargin;
					$this->theadMargin = '';
				}
				break;
			}
			case 'a': {
				$this->HREF = '';
				break;
			}
			case 'sup': {
				$this->SetXY($this->GetX(), $this->GetY() + ((0.7 * $parent['fontsize']) / $this->k));
				break;
			}
			case 'sub': {
				$this->SetXY($this->GetX(), $this->GetY() - ((0.3 * $parent['fontsize'])/$this->k));
				break;
			}
			case 'div': {
				$this->addHTMLVertSpace(1, $cell, '', $firstorlast, $tag['value'], true);
				break;
			}
			case 'blockquote': {
				$this->indent--;
				$this->rIndent--;
				//if ($this->rtl) {
					$this->rMargin -= $this->listindent; // original
					//$this->original_rMargin -= $this->listindent;
				//} else {
					$this->lMargin -= $this->listindent; // original
					//$this->original_lMargin -= $this->listindent;
				//}
				$this->addHTMLVertSpace(2, $cell, '', $firstorlast, $tag['value'], true);
				$this->Ln(0, true);
				break;
			}
			case 'p': {
				$this->addHTMLVertSpace(2, $cell, '', $firstorlast, $tag['value'], true);
				break;
			}
			case 'pre': {
				$this->addHTMLVertSpace(1, $cell, '', $firstorlast, $tag['value'], true);
				$this->premode = false;
				break;
			}
			case 'dl': {
				--$this->listnum;
				if ($this->listnum <= 0) {
					$this->listnum = 0;
					$this->addHTMLVertSpace(2, $cell, '', $firstorlast, $tag['value'], true);
				}
				break;
			}
			case 'dt': {
				$this->lispacer = '';
				$this->addHTMLVertSpace(0, $cell, '', $firstorlast, $tag['value'], true);
				break;
			}
			case 'dd': {
				$this->indent--;
				$this->lispacer = '';
				if ($this->rtl) {
					$this->rMargin -= $this->listindent;
				} else {
					$this->lMargin -= $this->listindent;
				}
				$this->addHTMLVertSpace(0, $cell, '', $firstorlast, $tag['value'], true);
				break;
			}
			case 'ul':
			case 'ol': {
				$this->indent--;
				--$this->listnum;
				$this->lispacer = '';
				if ($this->rtl) {
					$this->rMargin -= $this->listindent;
				} else {
					$this->lMargin -= $this->listindent;
				}
				if ($this->listnum <= 0) {
					$this->listnum = 0;
					$this->addHTMLVertSpace(2, $cell, '', $firstorlast, $tag['value'], true);
				}
				$this->lasth = $this->FontSize * $this->cell_height_ratio;
				break;
			}
			case 'li': {
				$this->lispacer = '';
				$this->addHTMLVertSpace(0, $cell, '', $firstorlast, $tag['value'], true);
				break;
			}
			case 'h1': 
			case 'h2': 
			case 'h3': 
			case 'h4': 
			case 'h5': 
			case 'h6': {
				$this->addHTMLVertSpace(1, $cell, ($parent['fontsize'] * 1.5) / $this->k, $firstorlast, $tag['value'], true);
				break;
			}
			default : {
				break;
			}
		}
		$this->tmprtl = false;
	}
	
	/**
	 * Returns the HTML DOM array.
	 * <ul><li>$dom[$key]['tag'] = true if tag, false otherwise;</li><li>$dom[$key]['value'] = tag name or text;</li><li>$dom[$key]['opening'] = true if opening tag, false otherwise;</li><li>$dom[$key]['attribute'] = array of attributes (attribute name is the key);</li><li>$dom[$key]['style'] = array of style attributes (attribute name is the key);</li><li>$dom[$key]['parent'] = id of parent element;</li><li>$dom[$key]['fontname'] = font family name;</li><li>$dom[$key]['fontstyle'] = font style;</li><li>$dom[$key]['fontsize'] = font size in points;</li><li>$dom[$key]['bgcolor'] = RGB array of background color;</li><li>$dom[$key]['fgcolor'] = RGB array of foreground color;</li><li>$dom[$key]['width'] = width in pixels;</li><li>$dom[$key]['height'] = height in pixels;</li><li>$dom[$key]['align'] = text alignment;</li><li>$dom[$key]['cols'] = number of colums in table;</li><li>$dom[$key]['rows'] = number of rows in table;</li></ul>
	 * @param string $html html code
	 * @return array
	 * @access protected
	 * @since 3.2.000 (2008-06-20)
	 */
	protected function getHtmlDomArray($html) {
		// remove all unsupported tags (the line below lists all supported tags)
		$html = strip_tags($html, '<marker/><a><b><blockquote><br><br/><dd><del><div><dl><dt><em><font><h1><h2><h3><h4><h5><h6><hr><i><img><li><ol><p><pre><small><span><strong><sub><sup><table><tcpdf><td><th><thead><tr><tt><u><ul>');
		//replace some blank characters
		$html = preg_replace('@(\r\n|\r)@', "\n", $html);
		$repTable = array("\t" => ' ', "\0" => ' ', "\x0B" => ' ', "\\" => "\\\\");
		$html = strtr($html, $repTable);
		while (preg_match("'<pre([^\>]*)>(.*?)\n(.*?)</pre>'si", $html)) {
			// preserve newlines on <pre> tag
			$html = preg_replace("'<pre([^\>]*)>(.*?)\n(.*?)</pre>'si", "<pre\\1>\\2<br />\\3</pre>", $html);
		}
		$html = str_replace("\n", ' ', $html);
		/*
		$html = preg_replace("'<div([^\>]*)>'si", "<br /><table><tr><td\\1>", $html);
		$html = preg_replace("'</div>'si", "</td></tr></table>", $html);
		$html = preg_replace("'<pre([^\>]*)>'si", "<table><tr><td\\1>", $html);
		$html = preg_replace("'</pre>'si", "</td></tr></table>", $html);
		*/
		// remove extra spaces from code
		$html = preg_replace('/[\s]+<\/(table|tr|td|th|ul|ol|li)>/', '</\\1>', $html);
		$html = preg_replace('/[\s]+<(tr|td|th|ul|ol|li|br)/', '<\\1', $html);
		// 2009-04-19: modified this to remove whitespace between all tags listed - the line is duplicated to capture all occurences
		$html = preg_replace('!<(/?(table|tr|td|th|blockquote|dd|div|dt|h1|h2|hr|li|ol|p|ul))>\s+<!', '<\\1><', $html);
		$html = preg_replace('!<(/?(table|tr|td|th|blockquote|dd|div|dt|h1|h2|hr|li|ol|p|ul))>\s+<!', '<\\1><', $html);
		$html = preg_replace('/<\/(td|th)>/', '<marker style="font-size:0"/></\\1>', $html);
		$html = preg_replace('/<\/table>([\s]*)<marker style="font-size:0"\/>/', '</table>', $html);
		$html = preg_replace('/[^>]<img/', '<br /><img', $html);
		//$html = preg_replace('/<img([^\>]*)>/xi', '<img\\1>', $html);
		// trim string
		$html = preg_replace('/^[\s]+/', '', $html);
		$html = preg_replace('/[\s]+$/', '', $html);
		//die($html);
		// pattern for generic tag
		$tagpattern = '/(<[^>]+>)/';
		// explodes the string
		$a = preg_split($tagpattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		// count elements
		$maxel = count($a);
		$elkey = 0;
		$key = 0;
		// create an array of elements
		$dom = array();
		$dom[$key] = array();
		// set first void element
		$dom[$key]['tag'] = false;
		$dom[$key]['value'] = '';
		$dom[$key]['parent'] = 0;
		$dom[$key]['fontname'] = $this->FontFamily;
		$dom[$key]['fontstyle'] = $this->FontStyle;
		$dom[$key]['fontsize'] = $this->FontSizePt;
		$dom[$key]['bgcolor'] = false;
		$dom[$key]['fgcolor'] = $this->fgcolor;
		$dom[$key]['align'] = '';
		$dom[$key]['listtype'] = '';
		$thead = false; // true when we are inside the THEAD tag
		++$key;
		$level = array();
		array_push($level, 0); // root
		while ($elkey < $maxel) {
			$dom[$key] = array();
			$element = $a[$elkey];
			$dom[$key]['elkey'] = $elkey;
			if (preg_match($tagpattern, $element)) {
				// html tag
				$element = substr($element, 1, -1);
				// get tag name
				preg_match('/[\/]?([a-zA-Z0-9]*)/', $element, $tag);
				$tagname = strtolower($tag[1]);
				// check if we are inside a table header
				if ($tagname == 'thead') {
					if ($element{0} == '/') {
						$thead = false;
					} else {
						$thead = true;
					}
					++$elkey;
					continue;
				}
				$dom[$key]['tag'] = true;
				$dom[$key]['value'] = $tagname;
				if ($element{0} == '/') {
					// closing html tag
					$dom[$key]['opening'] = false;
					$dom[$key]['parent'] = end($level);
					array_pop($level);
					$dom[$key]['fontname'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['fontname'];
					$dom[$key]['fontstyle'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['fontstyle'];
					$dom[$key]['fontsize'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['fontsize'];
					$dom[$key]['bgcolor'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['bgcolor'];
					$dom[$key]['fgcolor'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['fgcolor'];
					$dom[$key]['align'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['align'];
					if (isset($dom[($dom[($dom[$key]['parent'])]['parent'])]['listtype'])) {
						$dom[$key]['listtype'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['listtype'];
					}
					// set the number of columns in table tag
					if (($dom[$key]['value'] == 'tr') AND (!isset($dom[($dom[($dom[$key]['parent'])]['parent'])]['cols']))) {
						$dom[($dom[($dom[$key]['parent'])]['parent'])]['cols'] = $dom[($dom[$key]['parent'])]['cols'];
					}
					if (($dom[$key]['value'] == 'td') OR ($dom[$key]['value'] == 'th')) {
						$dom[($dom[$key]['parent'])]['content'] = '';
						for ($i = ($dom[$key]['parent'] + 1); $i < $key; ++$i) {
							$dom[($dom[$key]['parent'])]['content'] .= $a[$dom[$i]['elkey']];
						}
						$key = $i;
					}
					// store header rows on a new table
					if (($dom[$key]['value'] == 'tr') AND ($dom[($dom[$key]['parent'])]['thead'] == true)) {
						if ($this->empty_string($dom[($dom[($dom[$key]['parent'])]['parent'])]['thead'])) {
							$dom[($dom[($dom[$key]['parent'])]['parent'])]['thead'] = $a[$dom[($dom[($dom[$key]['parent'])]['parent'])]['elkey']];
						}
						for ($i = $dom[$key]['parent']; $i <= $key; ++$i) {
							$dom[($dom[($dom[$key]['parent'])]['parent'])]['thead'] .= $a[$dom[$i]['elkey']];
						}
					}
					if (($dom[$key]['value'] == 'table') AND (!$this->empty_string($dom[($dom[$key]['parent'])]['thead']))) {
						$dom[($dom[$key]['parent'])]['thead'] .= '</table>';
					}
				} else {
					// opening html tag
					$dom[$key]['opening'] = true;
					$dom[$key]['parent'] = end($level);
					if (substr($element, -1, 1) != '/') {
						// not self-closing tag
						array_push($level, $key);
						$dom[$key]['self'] = false;
					} else {
						$dom[$key]['self'] = true;
					}
					// copy some values from parent
					$parentkey = 0;
					if ($key > 0) {
						$parentkey = $dom[$key]['parent'];
						$dom[$key]['fontname'] = $dom[$parentkey]['fontname'];
						$dom[$key]['fontstyle'] = $dom[$parentkey]['fontstyle'];
						$dom[$key]['fontsize'] = $dom[$parentkey]['fontsize'];
						$dom[$key]['bgcolor'] = $dom[$parentkey]['bgcolor'];
						$dom[$key]['fgcolor'] = $dom[$parentkey]['fgcolor'];
						$dom[$key]['align'] = $dom[$parentkey]['align'];
						$dom[$key]['listtype'] = $dom[$parentkey]['listtype'];
					}
					// get attributes
					preg_match_all('/([^=\s]*)=["]?([^"]*)["]?/', $element, $attr_array, PREG_PATTERN_ORDER);
					$dom[$key]['attribute'] = array(); // reset attribute array
					while (list($id, $name) = each($attr_array[1])) {
						$dom[$key]['attribute'][strtolower($name)] = $attr_array[2][$id];
					}
					// split style attributes
					if (isset($dom[$key]['attribute']['style'])) {
						// get style attributes
						preg_match_all('/([^;:\s]*):([^;]*)/', $dom[$key]['attribute']['style'], $style_array, PREG_PATTERN_ORDER);
						$dom[$key]['style'] = array(); // reset style attribute array
						while (list($id, $name) = each($style_array[1])) {
							$dom[$key]['style'][strtolower($name)] = trim($style_array[2][$id]);
						}
						// --- get some style attributes ---
						if (isset($dom[$key]['style']['font-family'])) {
							// font family
							if (isset($dom[$key]['style']['font-family'])) {
								$fontslist = split(',', strtolower($dom[$key]['style']['font-family']));
								foreach ($fontslist as $font) {
									$font = trim(strtolower($font));
									if (in_array($font, $this->fontlist) OR in_array($font, $this->fontkeys)) {
										$dom[$key]['fontname'] = $font;
										break;
									}
								}
							}
						}
						// list-style-type
						if (isset($dom[$key]['style']['list-style-type'])) {
							$dom[$key]['listtype'] = trim(strtolower($dom[$key]['style']['list-style-type']));
							if ($dom[$key]['listtype'] == 'inherit') {
								$dom[$key]['listtype'] = $dom[$parentkey]['listtype'];
							}
						}
						// font size
						if (isset($dom[$key]['style']['font-size'])) {
							$fsize = trim($dom[$key]['style']['font-size']);
							switch ($fsize) {
								// absolute-size
								case 'xx-small': {
									$dom[$key]['fontsize'] = $dom[0]['fontsize'] - 4;
									break;
								}
								case 'x-small': {
									$dom[$key]['fontsize'] = $dom[0]['fontsize'] - 3;
									break;
								}
								case 'small': {
									$dom[$key]['fontsize'] = $dom[0]['fontsize'] - 2;
									break;
								}
								case 'medium': {
									$dom[$key]['fontsize'] = $dom[0]['fontsize'];
									break;
								}
								case 'large': {
									$dom[$key]['fontsize'] = $dom[0]['fontsize'] + 2;
									break;
								}
								case 'x-large': {
									$dom[$key]['fontsize'] = $dom[0]['fontsize'] + 4;
									break;
								}
								case 'xx-large': {
									$dom[$key]['fontsize'] = $dom[0]['fontsize'] + 6;
									break;
								}
								// relative-size
								case 'smaller': {
									$dom[$key]['fontsize'] = $dom[$parentkey]['fontsize'] - 3;
									break;
								}
								case 'larger': {
									$dom[$key]['fontsize'] = $dom[$parentkey]['fontsize'] + 3;
									break;
								}
								default: {
									$dom[$key]['fontsize'] = $this->getHTMLUnitToUnits($fsize, $dom[$parentkey]['fontsize'], 'pt', true);
								}
							}
						}
						// font style
						if (isset($dom[$key]['style']['font-weight']) AND (strtolower($dom[$key]['style']['font-weight']{0}) == 'b')) {
							$dom[$key]['fontstyle'] .= 'B';
						}
						if (isset($dom[$key]['style']['font-style']) AND (strtolower($dom[$key]['style']['font-style']{0}) == 'i')) {
							$dom[$key]['fontstyle'] .= '"I';
						}
						// font color
						if (isset($dom[$key]['style']['color']) AND (!$this->empty_string($dom[$key]['style']['color']))) {
							$dom[$key]['fgcolor'] = $this->convertHTMLColorToDec($dom[$key]['style']['color']);
						}
						// background color
						if (isset($dom[$key]['style']['background-color']) AND (!$this->empty_string($dom[$key]['style']['background-color']))) {
							$dom[$key]['bgcolor'] = $this->convertHTMLColorToDec($dom[$key]['style']['background-color']);
						}
						// text-decoration
						if (isset($dom[$key]['style']['text-decoration'])) {
							$decors = explode(' ', strtolower($dom[$key]['style']['text-decoration']));
							foreach ($decors as $dec) {
								$dec = trim($dec);
								if (!$this->empty_string($dec)) {
									if ($dec{0} == 'u') {
										$dom[$key]['fontstyle'] .= 'U';
									} elseif ($dec{0} == 'l') {
										$dom[$key]['fontstyle'] .= 'D';
									}
								}
							}
						}
						// check for width attribute
						if (isset($dom[$key]['style']['width'])) {
							$dom[$key]['width'] = $dom[$key]['style']['width'];
						}
						// check for height attribute
						if (isset($dom[$key]['style']['height'])) {
							$dom[$key]['height'] = $dom[$key]['style']['height'];
						}
						// check for text alignment
						if (isset($dom[$key]['style']['text-align'])) {
							$dom[$key]['align'] = strtoupper($dom[$key]['style']['text-align']{0});
						}
						// check for border attribute
						if (isset($dom[$key]['style']['border'])) {
							$dom[$key]['attribute']['border'] = $dom[$key]['style']['border'];
						}
					}
					// check for font tag
					if ($dom[$key]['value'] == 'font') {
						// font family
						if (isset($dom[$key]['attribute']['face'])) {
							$fontslist = split(',', strtolower($dom[$key]['attribute']['face']));
							foreach ($fontslist as $font) {
								$font = trim(strtolower($font));
								if (in_array($font, $this->fontlist) OR in_array($font, $this->fontkeys)) {
									$dom[$key]['fontname'] = $font;
									break;
								}
							}
						}
						// font size
						if (isset($dom[$key]['attribute']['size'])) {
							if ($key > 0) {
								if ($dom[$key]['attribute']['size']{0} == '+') {
									$dom[$key]['fontsize'] = $dom[($dom[$key]['parent'])]['fontsize'] + intval(substr($dom[$key]['attribute']['size'], 1));
								} elseif ($dom[$key]['attribute']['size']{0} == '-') {
									$dom[$key]['fontsize'] = $dom[($dom[$key]['parent'])]['fontsize'] - intval(substr($dom[$key]['attribute']['size'], 1));
								} else {
									$dom[$key]['fontsize'] = intval($dom[$key]['attribute']['size']);
								}
							} else {
								$dom[$key]['fontsize'] = intval($dom[$key]['attribute']['size']);
							}
						}
					}
					// force natural alignment for lists
					if ((($dom[$key]['value'] == 'ul') OR ($dom[$key]['value'] == 'ol') OR ($dom[$key]['value'] == 'dl'))
						AND (!isset($dom[$key]['align']) OR $this->empty_string($dom[$key]['align']) OR ($dom[$key]['align'] != 'J'))) {
						if ($this->rtl) {
							$dom[$key]['align'] = 'R';
						} else {
							$dom[$key]['align'] = 'L';
						}
					}
					if (($dom[$key]['value'] == 'small') OR ($dom[$key]['value'] == 'sup') OR ($dom[$key]['value'] == 'sub')) {
						$dom[$key]['fontsize'] = $dom[$key]['fontsize'] * K_SMALL_RATIO;
					}
					if (($dom[$key]['value'] == 'strong') OR ($dom[$key]['value'] == 'b')) {
						$dom[$key]['fontstyle'] .= 'B';
					}
					if (($dom[$key]['value'] == 'em') OR ($dom[$key]['value'] == 'i')) {
						$dom[$key]['fontstyle'] .= 'I';
					}
					if ($dom[$key]['value'] == 'u') {
						$dom[$key]['fontstyle'] .= 'U';
					}
					if ($dom[$key]['value'] == 'del') {
						$dom[$key]['fontstyle'] .= 'D';
					}
					if (($dom[$key]['value'] == 'pre') OR ($dom[$key]['value'] == 'tt')) {
						$dom[$key]['fontname'] = $this->default_monospaced_font;
					}
					if (($dom[$key]['value']{0} == 'h') AND (intval($dom[$key]['value']{1}) > 0) AND (intval($dom[$key]['value']{1}) < 7)) {
						//$headsize = (4 - intval($dom[$key]['value']{1})) * 2;
						if (intval($dom[$key]['value']{1}) == 1) {
							//$dom[$key]['fontsize'] = $dom[0]['fontsize'] + $headsize;
							$dom[$key]['fontsize'] = $this->getHeadlineSize();
						} else {
							$dom[$key]['fontstyle'] .= 'B';
						}
					}
					if (($dom[$key]['value'] == 'table')) {
						$dom[$key]['rows'] = 0; // number of rows
						$dom[$key]['trids'] = array(); // IDs of TR elements
						$dom[$key]['thead'] = ''; // table header rows
					}
					if (($dom[$key]['value'] == 'tr')) {
						$dom[$key]['cols'] = 0;
						// store the number of rows on table element
						++$dom[($dom[$key]['parent'])]['rows'];
						// store the TR elements IDs on table element
						array_push($dom[($dom[$key]['parent'])]['trids'], $key);
						if ($thead) {
							$dom[$key]['thead'] = true;
						} else {
							$dom[$key]['thead'] = false;
						}
					}
					if (($dom[$key]['value'] == 'th') OR ($dom[$key]['value'] == 'td')) {
						if (isset($dom[$key]['attribute']['colspan'])) {
							$colspan = intval($dom[$key]['attribute']['colspan']);
						} else {
							$colspan = 1;
						}
						$dom[$key]['attribute']['colspan'] = $colspan;
						$dom[($dom[$key]['parent'])]['cols'] += $colspan;
					}
					// set foreground color attribute
					if (isset($dom[$key]['attribute']['color']) AND (!$this->empty_string($dom[$key]['attribute']['color']))) {
						$dom[$key]['fgcolor'] = $this->convertHTMLColorToDec($dom[$key]['attribute']['color']);
					}
					// set background color attribute
					if (isset($dom[$key]['attribute']['bgcolor']) AND (!$this->empty_string($dom[$key]['attribute']['bgcolor']))) {
						$dom[$key]['bgcolor'] = $this->convertHTMLColorToDec($dom[$key]['attribute']['bgcolor']);
					}
					// check for width attribute
					if (isset($dom[$key]['attribute']['width'])) {
						$dom[$key]['width'] = $dom[$key]['attribute']['width'];
					}
					// check for height attribute
					if (isset($dom[$key]['attribute']['height'])) {
						$dom[$key]['height'] = $dom[$key]['attribute']['height'];
					}
					// check for text alignment
					if (isset($dom[$key]['attribute']['align']) AND (!$this->empty_string($dom[$key]['attribute']['align'])) AND ($dom[$key]['value'] !== 'img')) {
						$dom[$key]['align'] = strtoupper($dom[$key]['attribute']['align']{0});
					}
				} // end opening tag
			} else {
				// text
				$dom[$key]['tag'] = false;
				$dom[$key]['value'] = stripslashes($this->unhtmlentities($element));
				$dom[$key]['parent'] = end($level);
			}
			++$elkey;
			++$key;
		}
		return $dom;
	}
	
	/**
	* Puts an image in the page. 
	* The upper-left corner must be given. 
	* The dimensions can be specified in different ways:<ul>
	* <li>explicit width and height (expressed in user unit)</li>
	* <li>one explicit dimension, the other being calculated automatically in order to keep the original proportions</li>
	* <li>no explicit dimension, in which case the image is put at 72 dpi</li></ul>
	* Supported formats are JPEG and PNG images whitout GD library and all images supported by GD: GD, GD2, GD2PART, GIF, JPEG, PNG, BMP, XBM, XPM;
	* The format can be specified explicitly or inferred from the file extension.<br />
	* It is possible to put a link on the image.<br />
	* Remark: if an image is used several times, only one copy will be embedded in the file.<br />
	* @param string $file Name of the file containing the image.
	* @param float $x Abscissa of the upper-left corner.
	* @param float $y Ordinate of the upper-left corner.
	* @param float $w Width of the image in the page. If not specified or equal to zero, it is automatically calculated.
	* @param float $h Height of the image in the page. If not specified or equal to zero, it is automatically calculated.
	* @param string $type Image format. Possible values are (case insensitive): JPEG and PNG (whitout GD library) and all images supported by GD: GD, GD2, GD2PART, GIF, JPEG, PNG, BMP, XBM, XPM;. If not specified, the type is inferred from the file extension.
	* @param mixed $link URL or identifier returned by AddLink().
	* @param string $align Indicates the alignment of the pointer next to image insertion relative to image height. The value can be:<ul><li>T: top-right for LTR or top-left for RTL</li><li>M: middle-right for LTR or middle-left for RTL</li><li>B: bottom-right for LTR or bottom-left for RTL</li><li>N: next line</li></ul>
	* @param boolean $resize If true resize (reduce) the image to fit $w and $h (requires GD library).
	* @param int $dpi dot-per-inch resolution used on resize
	* @param string $palign Allows to center or align the image on the current line. Possible values are:<ul><li>L : left align</li><li>C : center</li><li>R : right align</li><li>'' : empty string : left for LTR or right for RTL</li></ul>
	* @param boolean $ismask true if this image is a mask, false otherwise
	* @param mixed $imgmask image object returned by this function or false
	* @param mixed $border Indicates if borders must be drawn around the image. The value can be either a number:<ul><li>0: no border (default)</li><li>1: frame</li></ul>or a string containing some or all of the following characters (in any order):<ul><li>L: left</li><li>T: top</li><li>R: right</li><li>B: bottom</li></ul>
	* @return image information
	* @access public
	* @since 1.1
	*/
	public function Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0) {
		

		// Fix by Christian Sciberras 2/17/2010 Covac Software / Keen Advertising Ltd.
if(!function_exists('_img_httpGet')){ // get string from url via GET
function _img_httpGet($url){
$url = urldecode($url);
$url = str_replace( "&amp;","&",$url );
$ch=curl_init();

curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_HEADER,False);
curl_setopt($ch,CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,True);
curl_setopt($ch,CURLOPT_FOLLOWLOCATION,True);
curl_setopt($ch,CURLOPT_MAXREDIRS,50);

$data=curl_exec($ch);
if(curl_getinfo($ch,CURLINFO_HTTP_CODE)>=400)return '';
curl_close($ch);
if($data===false)return '';
return $data;
}
}
if(!function_exists('_img_cleanCache')){ // remove all cache files if there are more then 40
function _img_cleanCache($cache){
$files=glob($cache.'*');
if(count($files)>=40)foreach($files as $file)unlink($file);
}
}
if(!function_exists('_img_makeImgFileName')){ // makes filename according to file magic number
function _img_makeImgFileName($data){
$res=mt_rand().'.';
switch(substr($data,0,2)){ // File verification
case "\x00\x00": return $res.'ico';
case "\x47\x49": return $res.'gif';
case "\x89\x50": return $res.'png';
case "\xFF\xD8": return $res.'jpg';
case 'BM': case 'BA': case 'CI':
case 'CP': case 'IC': case 'PT':
return $res.'bmp';
default:
trigger_error('Remote file magic number is unidentified ($'
.dechex(ord($data[0])).dechex(ord($data[1])).').',E_USER_ERROR);
die;
}
}
}
if(!function_exists('_img_fixUrl')){ // makes filename according to file magic number
function _img_fixUrl($url){
if(strpos($url,'?')===false)return $url;
$hrf=substr($url,0,strpos($url,'?')+1);
$arg=explode('&',substr($url,strpos($url,'?')+1,strlen($url)-strlen($hrf)));
foreach($arg as $k=>$v){
$v=explode('=',$v,2);
$arg[$k]=$v[0].'='.rawurlencode($v[1]);
}
return $hrf.implode('&',$arg);
}
}
if(strtolower(substr($file,0,4))=='http'){ // remote file (download to cache fix)
$cache=dirname(__FILE__) .'/cache/';
if(!is_writable($cache)){trigger_error('Cache folder inexistent or unwritable.',E_USER_ERROR);die;}
_img_cleanCache($cache);
$file=_img_fixUrl($file);

$data=_img_httpGet($file);
if($data==''){trigger_error('Remote source didn\'t return any data.',E_USER_ERROR);die;}
$file=$cache._img_makeImgFileName($data);
file_put_contents($file,$data);
}/*else{ // local file (correct path fix)
$cns='libraries/tcpdf//';
$pos=strpos($file,$cns)+strlen($cns);
if($pos!==false)$file=substr($file,$pos,strlen($file)-$pos);
}*/

//====================== END CODE ======================
		if ($x === '') {
			$x = $this->x;
		}
		if ($y === '') {
			$y = $this->y;
		}
		// get image dimensions
		$imsize = @getimagesize($file);
		if ($imsize === FALSE) {
			// encode spaces on filename
			$file = str_replace(' ', '%20', $file);
			$imsize = @getimagesize($file);
			if ($imsize === FALSE) {
				$this->Error('[Image] No such file or directory in '.$file);
			}
		}
		// get original image width and height in pixels
		list($pixw, $pixh) = $imsize;
		// calculate image width and height on document
		if (($w <= 0) AND ($h <= 0)) {
			// convert image size to document unit
			$w = $pixw / ($this->imgscale * $this->k);
			$h = $pixh / ($this->imgscale * $this->k);
		} elseif ($w <= 0) {
			$w = $h * $pixw / $pixh;
		} elseif ($h <= 0) {
			// use smallest acceptable width for image
			$w = min($w, $this->getHTMLUnitToUnits($pixw));
			//echo "$file - width: $pixw, height: $pixh, actual width: $w, converted width: ".$this->getHTMLUnitToUnits($pixw)."\n";
			$h = $w * $pixh / $pixw;
		}
		// calculate new minimum dimensions in pixels
		$neww = round($w * $this->k * $dpi / $this->dpi);
		$newh = round($h * $this->k * $dpi / $this->dpi);
		// check if resize is necessary (resize is used only to reduce the image)
		if (($neww * $newh) >= ($pixw * $pixh)) {
			$resize = false;
		}
		// check if image has been already added on document
		if (!in_array($file, $this->imagekeys)) {
			//First use of image, get info
			if ($type == '') {
				$fileinfo = pathinfo($file);
				if (isset($fileinfo['extension']) AND (!$this->empty_string($fileinfo['extension']))) {
					$type = $fileinfo['extension'];
				} else {
					$this->Error('Image file has no extension and no type was specified: '.$file);
				}
			}
			$type = strtolower($type);
			if ($type == 'jpg') {
				$type = 'jpeg';
			}
			$mqr = get_magic_quotes_runtime();
			set_magic_quotes_runtime(0);
			// Specific image handlers
			$mtd = '_parse'.$type;
			// GD image handler function
			$gdfunction = 'imagecreatefrom'.$type;
			$info = false;
			if ((method_exists($this, $mtd)) AND (!($resize AND function_exists($gdfunction)))) {
				// TCPDF image functions
				$info = $this->$mtd($file);
				if ($info == 'pngalpha') {
					return $this->ImagePngAlpha($file, $x, $y, $w, $h, 'PNG', $link, $align, $resize, $dpi, $palign);
				}
			} 
			if (!$info) {
				if (function_exists($gdfunction)) {
					// GD library
					$img = $gdfunction($file);
					if ($resize) {
						$imgr = imagecreatetruecolor($neww, $newh);
						imagecopyresampled($imgr, $img, 0, 0, 0, 0, $neww, $newh, $pixw, $pixh); 
						$info = $this->_toJPEG($imgr);
					} else {
						$info = $this->_toJPEG($img);
					}
				} elseif (extension_loaded('imagick')) {
					// ImageMagick library
					$img = new Imagick();
					$img->readImage($file);
					if ($resize) {
						$img->resizeImage($neww, $newh, 10, 1, false);
					}
					$img->setCompressionQuality($this->jpeg_quality);
					$img->setImageFormat('jpeg');
					$tempname = tempnam(K_PATH_CACHE, 'jpg_');
					$img->writeImage($tempname);
					$info = $this->_parsejpeg($tempname);
					unlink($tempname);
					$img->destroy();
				} else {
					return;
				}
			}
			if ($info === false) {
				//If false, we cannot process image
				return;
			}
			set_magic_quotes_runtime($mqr);
			if ($ismask) {
				// force grayscale
				$info['cs'] = 'DeviceGray';
			}
			$info['i'] = $this->numimages + 1;
			if ($imgmask !== false) {
				$info['masked'] = $imgmask;
			}
			// add image to document
			$this->setImageBuffer($file, $info);
		} else {
			$info = $this->getImageBuffer($file);
		}
		// Check whether we need a new page first as this does not fit
		//echo '---', $file, ': ';
		//if ((($y + $h) > $this->PageBreakTrigger) AND (!$this->InFooter)) echo 'PAGE BREAK!';
		if ((($y + $h) > $this->PageBreakTrigger) AND (!$this->InFooter) AND $this->AcceptPageBreak()) {
			// Automatic page break
			$this->AddPage($this->CurOrientation);
			// Reset Y coordinate to the top of next page
			$y = $this->GetY() + $this->cMargin;
		}
		if ((($y + $h) > $this->PageBreakTrigger) AND (!$this->InFooter)) {
			$y = $this->GetY();
			$x = $this->GetX();
		}
		// set bottomcoordinates
		$this->img_rb_y = $y + $h;
		// set alignment
		if ($this->rtl) {
			if ($palign == 'L') {
				$ximg = $this->lMargin;
				// set right side coordinate
				$this->img_rb_x = $ximg + $w;
			} elseif ($palign == 'C') {
				$ximg = ($this->w - $x - $w) / 2;
				// set right side coordinate
				$this->img_rb_x = $ximg + $w;
			} else {
				$ximg = $this->w - $x - $w;
				// set left side coordinate
				$this->img_rb_x = $ximg;
			}
		} else {
			if ($palign == 'R') {
				$ximg = $this->w - $this->rMargin - $w;
				// set left side coordinate
				$this->img_rb_x = $ximg;
			} elseif ($palign == 'C') {
				$ximg = ($this->w - $x - $w) / 2;
				// set right side coordinate
				$this->img_rb_x = $ximg + $w;
			} else {
				$ximg = $x;
				// set right side coordinate
				$this->img_rb_x = $ximg + $w;
			}
		}
		if ($ismask) {
			// embed hidden, ouside the canvas
			$xkimg = ($this->pagedim[$this->page]['w'] + 10);
		} else {
			$xkimg = $ximg * $this->k;
		}
		$this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', ($w * $this->k), ($h * $this->k), $xkimg, (($this->h - ($y + $h)) * $this->k), $info['i']));
		if (!empty($border)) {
			$bx = $x;
			$by = $y;
			$this->x = $ximg;
			$this->y = $y;
			$this->Cell($w, $h, '', $border, 0, '', 0, '', 0);
			$this->x = $bx;
			$this->y = $by;
		}
		if ($link) {
			$this->Link($ximg, $y, $w, $h, $link, 0);
		}
		// set pointer to align the successive text/objects
		switch($align) {
			case 'T': {
				$this->y = $y;
				$this->x = $this->img_rb_x;
				break;
			}
			case 'M': {
				$this->y = $y + round($h/2);
				$this->x = $this->img_rb_x;
				break;
			}
			case 'B': {
				$this->y = $this->img_rb_y;
				$this->x = $this->img_rb_x;
				break;
			}
			case 'N': {
				$this->SetY($this->img_rb_y);
				break;
			}
			default:{
				break;
			}
		}
		$this->endlinex = $this->img_rb_x;
		return $info['i'];
	}
}
?>
