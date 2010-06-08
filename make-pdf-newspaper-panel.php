<?php if (isset($_POST['mpn_action'])) { ?>
<div id="message" class="updated fade" style="background-color: rgb(255, 251, 204);">
  <p><strong><?php echo $status; ?></strong></p>
</div>
<?php } ?>
<style type="text/css">
table, .box
{
    background-color: #fff;
    border: 2px solid #ccc;
    -moz-border-radius: 10px;
    -webkit-border-radius: 10px;
    border-radius: 10px;
    width:100%;
    padding: 2px;
}
table td, table th {
padding:4px;
vertical-align:top;
border-bottom:1px dashed #cccccc;
}
table th{
border-right:1px dashed #cccccc;
}

</style>
<div class="wrap">
<h2>Make PDF Newspaper </h2>
<div class="box">
  <h3>Intructions</h3>
  <ol>
    <li>Customise your PDF newspaper using the options below</li>
    <li>Everytime you make a post or want to remake your publication hit 'Remake PDF' - Making the PDF can take upto 60 seconds</li>
  </ol>
  <p>(I did consider automatically remaking the PDF after each new post but as it is a server intensive process I thought it better to give user control) </p>
</div>
<h3>Options</h3>
<div class="gdsr">
  <form method="post">
    <?php if ( function_exists('wp_nonce_field') )
			wp_nonce_field('mpn-1', 'mpn-main');
			?>
    <input type="hidden" name="mpn_action" value="save" />
    <table>
      <tbody>
        <tr>
          <th scope="row">Title</th>
          <td align="left"><input name="mpn_title" type="text" id="mpn_title" size="40" value="<?php echo $options['mpn_title']; ?>"></td>
        </tr>
        <tr>
          <th scope="row">Filename</th>
          <td align="left"><input name="mpn_filename" type="text" id="mpn_filename" size="40" value="<?php echo $options['mpn_filename']; ?>"></td>
        </tr>
        <tr>
          <th scope="row">Subtitle (Optional) </th>
          <td align="left"><input name="mpn_subtitle" type="text" id="mpn_subtitle" size="60" value="<?php echo $options['mpn_subtitle']; ?>"></td>
        </tr>
        <tr>
          <th scope="row">Banner Image (Optional)</th>
          <td align="left">Url:
            <input name="mpn_image" type="text" id="mpn_image" size="60" value="<?php echo $options['mpn_image']; ?>">
            Width:
            <input name="mpn_image_width" type="text" id="mpn_image_width" size="4" value="<?php echo $options['mpn_image_width']; ?>">
            (mm) </td>
        </tr>
        <tr>
          <th scope="row">Include post images </th>
          <td align="left"><label for="mpn_images">Include images:</label>
          <input name="mpn_images" type="checkbox" value="1" <?php if ($options['mpn_images'] == 1) echo "checked";?> /></td>
        </tr>
        <tr>
          <th scope="row">URL footnote/shortening</th>
          <td align="left"><label for="mpn_urlfootnote">Include post links as footnotes:</label>
            <input name="mpn_urlfootnote" type="checkbox" value="1" <?php if ($options['mpn_urlfootnote'] == 1) echo "checked";?> />
            <label for="mpn_urlshorten"></label>
            <p>
              <label for="mpn_urlshorten">Shorten footnote links using URL shortening:
              <input name="mpn_urlshorten" type="checkbox" value="1" <?php if ($options['mpn_urlshorten'] == 1) echo "checked";?> />
              </label>
            </p>
            <p>
              <label for="mpn_short_type">Shortening service:
              <select name="mpn_short_type" id="mpn_short_type">
                <option value="tinyurl" <?php if ($options['mpn_short_type'] == "tinyurl") echo "selected";?>>TinyURL</option>
                <option value="bitly" <?php if ($options['mpn_short_type'] == "bitly") echo "selected";?>>Bitly</option>
              </select>
              </label>
            </p>
            <p>If using Bit.ly this service requires authentication. You must enter your login name and API key below. If you're already logged into your bit.ly account, you can find those by going to <a href="http://bit.ly/account/" target="_blank">http://bit.ly/account/<br>
              </a>&nbsp;&nbsp;&nbsp;
              <label for="mpn_bitly_name">Login name:</label>
              <input name="mpn_bitly_name" type="text" id="mpn_bitly_name" value="<?php echo $options['mpn_bitly_name']; ?>">
              <br>
&nbsp;&nbsp;&nbsp;
              <label for="mpn_bitly_key">API Key:</label>
              <input name="mpn_bitly_key" type="text" id="mpn_bitly_key" value="<?php echo $options['mpn_bitly_key']; ?>" size="60">
            </p></td>
        </tr>
        <tr>
          <th scope="row">QR codes </th>
          <td align="left"><label for="mpn_qrcodeshow">Include QR code at the end of each entry linking to online post:</label>
            <input name="mpn_qrcodeshow" type="checkbox" value="1" <?php if ($options['mpn_qrcodeshow'] == 1) echo "checked";?> /></td>
        </tr>
        <tr>
          <th scope="row">Date order </th>
          <td><label for="mpn_order">Show stories in
            <select id="mpn_order" name="mpn_order">
              <option value="desc" <?php if ($options['mpn_order'] == "desc") echo "selected";?>>descending</option>
              <option value="asc" <?php if ($options['mpn_order'] == "asc") echo "selected";?>>ascending</option>
            </select>
            date order</label>
          </td>
        </tr>
        <tr>
          <th scope="row">Thumbnail image (Optional)</th>
          <td>To include a thumbnail of your publication on your site you must register with <a href="http://webthumb.bluga.net/" target="_blank">Bluga.net WebThumb</a>. Registration is free and allows you to generate 100 thumbnails a month.(Leave these setting blank if you don't want a thumbnail) <br>
&nbsp;&nbsp;&nbsp;User Id:
            <input name="mpn_thumb_id" type="text" id="mpn_thumb_id" value="<?php echo $options['mpn_thumb_id']; ?>">
            <br>
&nbsp;&nbsp;&nbsp;API Key :
            <input name="mpn_thumb_key" type="text" id="mpn_thumb_key" value="<?php echo $options['mpn_thumb_key']; ?>" size="60"></td>
        </tr>
        <tr>
          <th scope="row">Enable digest </th>
          <td>Enable digest:
            <input name="mpn_digest" type="checkbox" value="1" <?php if ($options['mpn_digest'] == 1) echo "checked";?> />
            <br>
            Select a category used to identify your cover page:
            <?php 
                        $dropdown_options = array('show_option_all' => '', 'hide_empty' => 0, 'hierarchical' => 1,
                            'show_count' => 0, 'depth' => 0, 'orderby' => 'ID', 'selected' => $options["mpn_digest_category"], 'name' => 'mpn_digest_category');
                        wp_dropdown_categories($dropdown_options);
                    ?>
            <br>
            - Our organisation creates a fortnightly newsletter. This contains a cover page which is assigned its own category, containing links to posts that have been made in the last 2 weeks. Enabling digest allows us to create a pdf which contains the cover page and all the posts made between it and the last cover page. <a href="http://scottish-rscs.org.uk/newsfeed/" target="_blank">Visit the example at RSC NewsFeed </a></td>
        </tr>
        <tr>
          <th scope="row"></th>
          <td><span class="submit">
            <input class="inputbutton" type="submit" value="Save settings" name="saving"/>
            <input class="inputbutton" type="submit" value="Reset" name="reset" />
            <input class="inputbutton" type="button" value="Test PDF" name="makepdf" onClick="window.location='<?php echo WP_PLUGIN_URL . '/make-pdf-newspaper/makepdf.php?action=test'?>'"/>
            <input class="inputbutton" type="button" value="Remake PDF" name="remakepdf" onClick="window.location='<?php echo WP_PLUGIN_URL . '/make-pdf-newspaper/makepdf.php?action=rebuild'?>'"/>
            </span></td>
        </tr>
      </tbody>
    </table>
  </form>
</div>
