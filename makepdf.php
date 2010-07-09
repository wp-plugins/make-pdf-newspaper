<?php
// RSS to PDF
// Author: Keyvan Minoukadeh
// License: AGPLv3
// Date: 2009-06-29
// How to use: request this file passing it your feed in the querystring: makepdf.php?feed=http://mysite.org
// To include images in the PDF, add images=true to the querystring: makepdf.php?feed=http://mysite.org&images=true

/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
error_reporting(E_ALL ^ E_NOTICE);
ini_set("display_errors", 1);
@set_time_limit(360);

function getImageData($url){
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

function makeThumb($pdfURL, $thumbName){
		$o = get_option('make-pdf-newspaper-options');
		$hash = md5(gmdate("Ymd").$pdfURL.$o['mpn_thumb_key']);
		$url = "http://webthumb.bluga.net/easythumb.php?user=".$o['mpn_thumb_id']."&url=".$pdfURL."&hash=".$hash."&size=medium&cache=1";
		$data = getImageData($url);
		if($data==''){trigger_error('Remote source didn\'t return any data.',E_USER_ERROR);die;}
		file_put_contents(WP_CONTENT_DIR.$thumbName,$data);
}
// add wordpress functions
if (isset($_GET['action'])){
	require_once("../../../wp-load.php");
}
$o = get_option('make-pdf-newspaper-options');

//Collect 
if ($_GET['action'] == "rebuild"){
	$rebuild = true; 
}

// Check item ordering
$order = $o['mpn_order'];

$get_images = false;
// Check if images should be downloaded
if ($o['mpn_images'] == 1) $get_images = true;

// Max string length (total feed)
if ($get_images) {
	$max_strlen = 300000;
} else {
	$max_strlen = 100000;
}

// Check for feed URL
$url = $catFeed;
if ($url !="") $catExt = "-".$url;
$catExt = str_replace("/","-",$catExt);
$catExt = rtrim($catExt,"-");
$ext = '/pdf/'.$o['mpn_filename'].$catExt.'.pdf';
$thumbName= '/pdf/'.$o['mpn_filename'].$catExt.'.jpg';
$output_file = WP_CONTENT_DIR.$ext;
$pdfURL = WP_CONTENT_URL.$ext;

if (file_exists($output_file)) {
		if ($o['mpn_thumb_id']!=""){
			if (!file_exists(WP_CONTENT_DIR.$thumbName) || (filesize(WP_CONTENT_DIR.$thumbName))<60){
			//$thumbsize = filesize(WP_CONTENT_DIR.$thumbName);
			//if ($thumbsize<60){
				makeThumb($pdfURL,$thumbName);
			} 
			if ((filesize(WP_CONTENT_DIR.$thumbName))>60){
				$previewHTML = "<div style=\"font-size:80%; font-style:italic;\" align=\"center\"><a href=\"".$pdfURL."\" target=\"_blank\" ><img src=\"".WP_CONTENT_URL.$thumbName."\" width=\"160\" height=\"226\"/></a><br/>Preview powered by:<br/><a href=\"http://webthumb.bluga.net/\" target=\"_blank\">Bluga.net Webthumb</a></div>";
			}
		}
		$linkString = "<a href=\"".$pdfURL."\" target=\"_blank\" >%LINKTEXT%</a>".$previewHTML;
} 
if (!(file_exists($output_file)) || $rebuild) {
 if($o['mpn_engine_url']!=""){
 	if ($get_images) $imageStr = "true";
 	$extURL = $o['mpn_engine_url']."?feed=".get_bloginfo( 'wpurl' ).$catFeed.urlencode("?feed=make-pdf-newspaper")."&title=".urlencode($o['mpn_title'])."&sub=".urlencode($o['mpn_subtitle'])."&order=".$order."&images=".$imageStr.$o['mpn_engine_para'];
	$data = getImageData($extURL);
	if($data==''){trigger_error('Remote source didn\'t return any data.',E_USER_ERROR);die;}
	file_put_contents($output_file,$data);
 } else { 
	if(! class_exists('SimplePie'))
		require_once('libraries/simplepie/simplepie.inc');
	require_once('SimplePie_Chronological.php');
	// Include HTML Purifier to clean up and filter HTML input
	require_once('libraries/htmlpurifier/library/HTMLPurifier.auto.php');
	// Include SmartyPants to make pretty, curly quotes
	require_once('libraries/smartypants/smartypants.php');
	// Include TCPDF to turn all this into a PDF
	require_once('libraries/tcpdf/config/lang/eng.php');
	require_once('libraries/tcpdf/tcpdf.php');
	// Include NewspaperPDF to let us add stories to our PDF easily
	require_once('NewspaperPDF.php');
	
	// Get RSS/Atom feed
	if ($order == 'asc') {
		$feed = new SimplePie_Chronological();
	} else {
		$feed = new SimplePie();
	}
	$feed->set_feed_url(get_bloginfo( 'wpurl' ).$url.'?feed=make-pdf-newspaper');
	$feed->set_timeout(180);
	$feed->enable_cache(false);
	$feed->set_stupidly_fast(true);
	$feed->enable_order_by_date(true);
	$feed->set_url_replacements(array());
	$result = $feed->init();
	if ($result && (!is_array($feed->data) || count($feed->data) == 0)) {
		die('Sorry, no feed items found');
	}
	
	// Create new PDF document (LETTER/A4)
	$pdf = new NewspaperPDF('P', 'mm', 'A4', true, 'UTF-8', false);
	
	$pdf->SetCreator('http://fivefilters.org/pdf-newspaper/ (free software using TCPDF)');
	$pdf->SetAuthor(get_bloginfo('name'));
	$pdf->SetTitle($o['mpn_title']);
	$pdf->SetSubject($o['mpn_subtitle']);
	
	// set default header data
	if ($o['mpn_image'] != '') {
		$pdf->SetHeaderData($o['mpn_image'], $o['mpn_image_width'], $o['mpn_title'], '<span style="color: #666">'.date('j F, Y').' | </span>'.$o['mpn_subtitle']);
	} else {
		$pdf->SetHeaderData('', 0, $o['mpn_title'], '<span style="color: #666">'.date('j F, Y').' | </span>'.$o['mpn_subtitle']);
	}
	
	// set header and footer fonts
	$pdf->setHeaderFont(Array('dejavuserifcondensed', '', 39));
	$pdf->setFooterFont(Array('helveticab', 'B', 9));
	//set margins
	$pdf->SetMargins(13, PDF_MARGIN_TOP, 13);
	$pdf->SetHeaderMargin(16);
	$pdf->SetFooterMargin(15);
	
	// Set default image ratio and font
	$pdf->setCellHeightRatio(1.5);
	$pdf->SetFont('dejavuserifcondensed');
	
	//set auto page breaks
	$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
	
	//set image scale factor
	$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);  // 4
	$pdf->SetDisplayMode('default', 'continuous');
	
	// set default monospaced font
	$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
	
	//set some language-dependent strings
	$pdf->setLanguageArray($l); 
	
	// Black links with no underlining
	$pdf->setHtmlLinksStyle(array(0, 0, 0), '');
	
	// Define vertical spacing for various HTML elements
	$tagvs = array(
				'blockquote' => array(0 => array('h' => '', 'n' => 1), 1 => array('h' => '', 'n' => 1)),
				'img' => array(0 => array('h' => '', 'n' => 0), 1 => array('h' => '', 'n' => 0)),
				'p' => array(0 => array('h' => '', 'n' => 1.6), 1 => array('h' => '', 'n' => 1.6)),
				'h1' => array(0 => array('h' => '', 'n' => 1), 1 => array('h' => '', 'n' => 1.5)),
				'h2' => array(0 => array('h' => '', 'n' => 2), 1 => array('h' => '', 'n' => 1)),
				'h3' => array(0 => array('h' => '', 'n' => 1), 1 => array('h' => '', 'n' => 1)),
				'h4' => array(0 => array('h' => '', 'n' => 1), 1 => array('h' => '', 'n' => 1)),
				'h5' => array(0 => array('h' => '', 'n' => 1), 1 => array('h' => '', 'n' => 1)),
				'h6' => array(0 => array('h' => '', 'n' => 1), 1 => array('h' => '', 'n' => 1)),	
				'ul' => array(0 => array('h' => '', 'n' => 0), 1 => array('h' => '', 'n' => 1.5)),
				'li' => array(0 => array('h' => '', 'n' => 1.4))
				);
	$pdf->setHtmlVSpace($tagvs);
	
	// Set up HTML Purifier, HTML Tidy
	$purifier = new HTMLPurifier();
	
	// Loop through feed items
	$items = $feed->get_items();
	$strlen = 0;
	
	foreach ($items as $item) {
		// skip items which fall outside date range
		if (isset($date_start) && (int)$item->get_date('U') < $date_start) continue;
		if (isset($date_end) && (int)$item->get_date('U') > $date_end) continue;
	
		$config = HTMLPurifier_Config::createDefault();
		// these are the HTML elements/attributes that will be preserved
		if ($get_images) {
			$config->set('HTML', 'Allowed', 'div,p,b,strong,em,a[href],i,ul,li,ol,blockquote,br,h1,h2,h3,h4,h5,h6,code,pre,sub,sup,del,img[src]');
		} else {
			$config->set('HTML', 'Allowed', 'div,p,b,strong,em,a[href],i,ul,li,ol,blockquote,br,h1,h2,h3,h4,h5,h6,code,pre,sub,sup,del');
		}
		// Attempt to autoparagraph when 2 linebreaks are detected -- we use feature after we run HTML through Tidy and replace double <br>s with linebreaks (\n\n)
		$config->set('AutoFormat', 'AutoParagraph', true);
		// Remove empty elements - TCPDF still applies padding/vertical spacing rules to empty elements
		$config->set('AutoFormat', 'RemoveEmpty', true);
		// disable cache
		$config->set('Cache', 'DefinitionImpl', null);
		$config->set('URI', 'Base', $item->get_permalink());
		$config->set('URI', 'MakeAbsolute', true);
		$config->set('HTML', 'DefinitionID', 'extra-transforms');
		$config->set('HTML', 'DefinitionRev', 1);
		$def = $config->getHTMLDefinition(true);
		// Change <div> elements to <p> elements - We don't want <div><p>Bla bla bla</p></div> (makes it easier for TCPDF)
		$def->info_tag_transform['div'] = new HTMLPurifier_TagTransform_Simple('p');
		// <h1> elements are treated as story headlines so we downgrade any that appear to <h2>
		// <h2> to <h6> elements are treated the same (made bold but kept the same size)
		$def->info_tag_transform['h1'] = new HTMLPurifier_TagTransform_Simple('h2');
		$def->info_tag_transform['h3'] = new HTMLPurifier_TagTransform_Simple('h2');
		$def->info_tag_transform['h4'] = new HTMLPurifier_TagTransform_Simple('h2');
		$def->info_tag_transform['h5'] = new HTMLPurifier_TagTransform_Simple('h2');
		$def->info_tag_transform['h6'] = new HTMLPurifier_TagTransform_Simple('h2');
		//$def->info_tag_transform['i'] = new HTMLPurifier_TagTransform_Simple('em');
		
		$story = '';
		$content = $item->get_content();
		// replace double <br>s to linebreaks
		$content = preg_replace('!<br[^>]+>\s*<br[^>]+>!m', "\n\n", $content);
		// end here if character count is about to exceed our maximum
		$strlen += strlen($content);
		if ($strlen > $max_strlen) {
			break;
		}
		// run content through HTML Purifier
		$content = $purifier->purify($content, $config);
		// a little additional cleanup...
		$content = str_replace('<p><br /></p>', '<br />', $content);
		$content = preg_replace('!<br />\s*<(/?(h2|p))>!', '<$1>', $content);
		//$content = preg_replace('!<br />\s*</p>!', '</p>', $content);
		$content = preg_replace('!\s*<br />\s*!', '<br />', $content);
		$content = preg_replace('!</(p|blockquote)>\s*<br />\s*!', '</$1>', $content);
		$content = str_replace('<p>&nbsp;</p>', '', $content);
		// run content through SmartyPants to make things pretty
		$content = SmartyPants($content);
		$title = SmartyPants($item->get_title());
		$story .= $content;
	
		$pdf->addItem('<a href="'.$item->get_permalink().'">'.$title.'</a>', $story, (int)$item->get_date('U'));
	}
	// make PDF
	$pdf->makePdf();
	// output PDF
	$pdf->Output($output_file, 'F');
 }
 if ($o['mpn_thumb_id']!=""){
		makeThumb($pdfURL,$thumbName);
		if (filesize(WP_CONTENT_DIR.$thumbName)>60)
		$previewHTML = "<div style=\"font-size:80%; font-style:italic;\" align=\"center\"><a href=\"".$pdfURL."\" target=\"_blank\" ><img src=\"".WP_CONTENT_URL.$thumbName."\" width=\"160\" height=\"226\"/></a><br/>Preview powered by:<br/><a href=\"http://webthumb.bluga.net/\" target=\"_blank\">Bluga.net Webthumb</a></div>";
 }
 $linkString = "<a href=\"".$pdfURL."\" target=\"_blank\" >%LINKTEXT%</a>".$previewHTML;
}
if ($_GET['action']=="test" || $_GET['action']=="rebuild"){
	header('Location: '.$pdfURL);
} 
?>