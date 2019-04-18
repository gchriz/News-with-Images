<?php
/**
 *
 * @category        modules
 * @package         news_img
 * @author          WBCE Community
 * @copyright       2004-2009, Ryan Djurovich
 * @copyright       2009-2010, Website Baker Org. e.V.
 * @copyright       2019-, WBCE Community
 * @link            https://www.wbce.org/
 * @license         http://www.gnu.org/licenses/gpl.html
 * @platform        WBCE
 *
 */

require_once '../../config.php';

// check if module language file exists for the language set by the user (e.g. DE, EN)
if (!file_exists(WB_PATH .'/modules/news_img/languages/'.LANGUAGE .'.php')) {
    // no module language file exists for the language set by the user, include default module language file EN.php
    require_once WB_PATH .'/modules/news_img/languages/EN.php';
} else {
    // a module language file exists for the language defined by the user, load it
    require_once WB_PATH .'/modules/news_img/languages/'.LANGUAGE .'.php';
}

require_once WB_PATH."/include/jscalendar/jscalendar-functions.php";
require_once __DIR__.'/functions.inc.php';

// Get id
if (!isset($_POST['post_id']) or !is_numeric($_POST['post_id'])) {
    header("Location: ".ADMIN_URL."/pages/index.php");
    exit(0);
} else {
    $id = $_POST['post_id'];
    $post_id = $id;
}

$imageErrorMessage ='';
$file_dir  = WB_PATH.MEDIA_DIRECTORY.'/news_img/'.$post_id.'/';
$thumb_dir = WB_PATH.MEDIA_DIRECTORY.'/news_img/'.$post_id.'/thumb/';

// Include WB admin wrapper script
$update_when_modified = true; // Tells script to update when this page was last updated
require WB_PATH.'/modules/admin.php';

// fetch settings
$query_content = $database->query("SELECT * FROM `".TABLE_PREFIX."mod_news_img_settings` WHERE `section_id` = '$section_id'");
$fetch_content = $query_content->fetchRow();

$fetch_content['imgmaxsize'] = intval($fetch_content['imgmaxsize']);
$iniset = ini_get('upload_max_filesize');
$iniset = return_bytes($iniset);

$previewwidth = $previewheight = $thumbwidth = $thumbheight = '';
if(substr_count($fetch_content['resize_preview'],'x')>0) {
    list($previewwidth,$previewheight) = explode('x',$fetch_content['resize_preview'],2);
}
if(substr_count($fetch_content['imgthumbsize'],'x')>0) {
    list($thumbwidth,$thumbheight) = explode('x',$fetch_content['imgthumbsize'],2);
}

$imagemaxsize  = ($fetch_content['imgmaxsize']>0 && $fetch_content['imgmaxsize'] < $iniset)
    ? $fetch_content['imgmaxsize']
    : $iniset;

$imagemaxwidth  = $fetch_content['imgmaxwidth'];
$imagemaxheight = $fetch_content['imgmaxheight'];
$crop           = ($fetch_content['crop_preview'] == 'Y') ? 1 : 0;

$group="";

// Validate all fields
if ($admin->get_post('title') == '' and $admin->get_post('url') == '') {
    $admin->print_error($MESSAGE['GENERIC']['FILL_IN_ALL'], WB_URL.'/modules/news_img/modify_post.php?page_id='.$page_id.'&section_id='.$section_id.'&post_id='.$id);
} else {
    $title = $admin->get_post_escaped('title');
    $short = $admin->get_post_escaped('short');
    $long = $admin->get_post_escaped('long');
    $block2 = $admin->get_post_escaped('block2');
    $image = $admin->get_post_escaped('image');
    $active = $admin->get_post_escaped('active');
    $old_link = $admin->get_post_escaped('link');
    $group = $admin->get_post_escaped('group');
}

$group_id = 0;
$old_section_id = $section_id;
$old_page_id = $page_id;

if (!empty($group)) {
    $gid_value = urldecode($group);
    $values = unserialize($gid_value);
    if (!isset($values['s']) or  !isset($values['g']) or  !isset($values['p'])) {
        header("Location: ".ADMIN_URL."/pages/index.php");
        exit(0);
    }
    if (intval($values['p'])!=0) {
        $group_id = intval($values['g']);
        $section_id = intval($values['s']);
        $page_id = intval($values['p']);
    }
}

// Get page link URL
$query_page = $database->query("SELECT `level`,`link` FROM `".TABLE_PREFIX."pages` WHERE `page_id` = '$page_id'");
$page = $query_page->fetchRow();
$page_level = $page['level'];
$page_link = $page['link'];

// Include WB functions file
require_once WB_PATH.'/framework/functions.php';

// Work-out what the link should be
$post_link = '/posts/'.page_filename($title).PAGE_SPACER.$post_id;

// Make sure the post link is set and exists
// Make news post access files dir
make_dir(WB_PATH.PAGES_DIRECTORY.'/posts/');
$file_create_time = '';
if (!is_writable(WB_PATH.PAGES_DIRECTORY.'/posts/')) {
    $admin->print_error($MESSAGE['PAGES']['CANNOT_CREATE_ACCESS_FILE']);
} elseif (($old_link != $post_link) or !file_exists(WB_PATH.PAGES_DIRECTORY.$post_link.PAGE_EXTENSION)) {
    // We need to create a new file
    // First, delete old file if it exists
    if (file_exists(WB_PATH.PAGES_DIRECTORY.$old_link.PAGE_EXTENSION)) {
        $file_create_time = filemtime(WB_PATH.PAGES_DIRECTORY.$old_link.PAGE_EXTENSION);
        unlink(WB_PATH.PAGES_DIRECTORY.$old_link.PAGE_EXTENSION);
    }

    // Specify the filename
    $filename = WB_PATH.PAGES_DIRECTORY.'/'.$post_link.PAGE_EXTENSION;
    create_file($filename, $file_create_time);
}

// get publisedwhen and publisheduntil
$publishedwhen = jscalendar_to_timestamp($admin->get_post_escaped('publishdate'));
if ($publishedwhen == '' || $publishedwhen < 1) {
    $publishedwhen=0;
}
$publisheduntil = jscalendar_to_timestamp($admin->get_post_escaped('enddate'), $publishedwhen);
if ($publisheduntil == '' || $publisheduntil < 1) {
    $publisheduntil=0;
}

if (!defined('ORDERING_CLASS_LOADED')) {
    require WB_PATH.'/framework/class.order.php';
}

//post images
if (isset($_FILES["foto"])) {
    // make sure the folder exists
    if(!is_dir($file_dir)) {
        mod_news_img_makedir($file_dir);
    }
    // 2014-04-10 by BlackBird Webprogrammierung:
    //            image position (order)
    foreach ($_FILES as $picture) {
        if (!isset($picture['name']) || !is_array($picture['name'])) {
            continue;
        }
        for ($i=0; $i<sizeof($picture['name']); $i++) {
            //wenn nur vorschaubild hochgeladen wird und alle galeriefotos leer sind.....
            if (isset($picture['name'][$i]) && $picture['name'][$i] && (strlen($picture['name'][$i]) > 3)) {
                //change special characters
                $imagename = media_filename($picture['name'][$i]);
                //small characters
                $imagename = strtolower($imagename) ;

                // 2014-04-10 by BlackBird Webprogrammierung:
                //            if file exists, find new name by adding a number
                if (file_exists($file_dir.$imagename)) {
                    $num = 1;
                    $f_name = pathinfo($file_dir.$imagename, PATHINFO_FILENAME);
                    $suffix = pathinfo($file_dir.$imagename, PATHINFO_EXTENSION);
                    while (file_exists($file_dir.$f_name.'_'.$num.'.'.$suffix)) {
                        $num++;
                    }
                    $imagename = $f_name.'_'.$num.'.'.$suffix;
                }

                // check
                if ($picture['size'][$i] > $imagemaxsize) {
                    $pic_error.= $MOD_NEWS_IMG['IMAGE_LARGER_THAN'].byte_convert($imagemaxsize).'<br />';
                } elseif (strlen($imagename) > '256') {
                    $pic_error.= $MOD_NEWS_IMG['IMAGE_FILENAME_ERROR'].'1<br />';
                } else {
                    // move to media folder
                    if(true===move_uploaded_file($picture['tmp_name'][$i], $file_dir.$imagename)) {

                        // 2014-04-10 by BlackBird Webprogrammierung:
                        //            resize image
                        if (list($w, $h) = getimagesize($file_dir.$imagename)) {
                            if ($w>$imagemaxwidth || $h>$imagemaxheight) {
                                image_resize($file_dir.$imagename, $file_dir.$imagename, $imagemaxwidth, $imagemaxheight, $crop);
                            }
                        }

                        //create thumb
                        if (true !== ($pic_error = @image_resize($file_dir.$imagename, $thumb_dir.$imagename, $thumbwidth, $thumbheight, $crop))) {
                            $imageErrorMessage.=$pic_error.'<br />';
                            //@unlink($imagename);
                        } else {

                            // 2014-04-10 by BlackBird Webprogrammierung:
                            //            image position
                            $order = new order(TABLE_PREFIX.'mod_news_img_img', 'position', 'id', 'post_id');
                            $position = $order->get_new($post_id);

                            // DB insert
                            $database->query("INSERT INTO ".TABLE_PREFIX."mod_news_img_img (bildname, post_id, position) VALUES ('".$imagename."', ".$post_id.", ".$position.')');
                        }
                    }
                }
            }
        }
    }
}

// ----- post picture; shown on overview page ----------------------------------
if (isset($_FILES["postfoto"]) && $_FILES["postfoto"]["name"] != "") {
    // make sure file_dir exists
    if(!is_dir($file_dir)) {
        mod_news_img_makedir($file_dir);
    }
    // there should only be one...
    foreach ($_FILES as $postpicture) {
        if ($postpicture['name'] && !is_array($postpicture['name'])) {
            //change special characters
            $postimgname = media_filename($postpicture['name']);
            //small characters
            $postimgname = strtolower("$postimgname") ;

            // 2014-04-10 by BlackBird Webprogrammierung:
            //            if file exists, find new name by adding a number
            if (file_exists($file_dir.$postimgname)) {
                $num = 1;
                $f_name = pathinfo($postimgname, PATHINFO_FILENAME);
                $suffix = pathinfo($postimgname, PATHINFO_EXTENSION);
                while (file_exists($file_dir.$f_name.'_'.$num.'.'.$suffix)) {
                    $num++;
                }
                $postimgname = $f_name.'_'.$num.'.'.$suffix;
            }

            // checks
            if ($postpicture['size'] > $imagemaxsize) {
                $imageErrorMessage.= $MOD_NEWS_IMG['IMAGE_LARGER_THAN'].byte_convert($imagemaxsize).'<br />';
            } elseif (strlen($postimgname) > '256') {
                $imageErrorMessage.= $MOD_NEWS_IMG['IMAGE_FILENAME_ERROR'].'<br />';
            } else {
                // move to media folder
                $tmpname = pathinfo($postpicture['tmp_name'],PATHINFO_FILENAME).'.'.pathinfo($postpicture['name'],PATHINFO_EXTENSION);
                if(true===move_uploaded_file($postpicture['tmp_name'], $file_dir.$tmpname)) {
                    // resize
                    if (substr_count($fetch_content['resize_preview'], 'x')>0) {
                        list($previewwidth, $previewheight) = explode('x', $fetch_content['resize_preview'], 2);
                        if (true !== ($pic_error = @image_resize($file_dir.$tmpname, $file_dir.$postimgname, $previewwidth, $previewheight, $crop))) {
                            $imageErrorMessage .= 'resize image: '.$pic_error.'<br />';
                            @unlink($file_dir.$tmpname);
                            @unlink($file_dir.$postimgname);
                        } else {
                            $image = $postimgname;
                            @unlink($file_dir.$tmpname);
                        }
                    } else {
                        // just rename
                        rename($file_dir.$tmpname,$file_dir.$postimgname);
                    }
                }
            }
        }
    }
    //input file nur bei leerem db-feld
} elseif (!isset($_FILES["postfoto"])) {
    $image = $_POST['previewimage'];
}
  
// strip HTML from title
$title = strip_tags($title);

$position="";
// if we are moving posts across section borders we have to update the order of the posts
if ($old_section_id!=$section_id) {
    // Get new order
    $order = new order(TABLE_PREFIX.'mod_news_img_posts', 'position', 'post_id', 'section_id');
    $position = "`position` = '".$order->get_new($section_id)."',";
}

     
// Update row
$database->query("UPDATE `".TABLE_PREFIX."mod_news_img_posts` SET `page_id` = '$page_id', `section_id` = '$section_id', $position `group_id` = '$group_id', `title` = '$title', `link` = '$post_link', `content_short` = '$short', `content_long` = '$long', `content_block2` = '$block2', `image` = '$image', `active` = '$active', `published_when` = '$publishedwhen', `published_until` = '$publisheduntil', `posted_when` = '".time()."', `posted_by` = '".$admin->get_user_id()."' WHERE `post_id` = '$post_id'");

// when no error has occurred go ahead and update the image descriptions
if (!($database->is_error())) {
    //update Bildbeschreibungen der tabelle mod_news_img_img
    $query_img = $database->query("SELECT * FROM `".TABLE_PREFIX."mod_news_img_img` WHERE `post_id` = ".$post_id);
    if ($query_img->numRows() > 0) {
        while ($row = $query_img->fetchRow()) {
            $row_id = $row['id'];
            // var_dump($row_id);
            //var_dump($_POST['bildbeschreibung'][$row_id]);
            $bildbeschreibung = isset($_POST['bildbeschreibung'][$row_id])
                          ? $_POST['bildbeschreibung'][$row_id]
                          : '';
            $database->query("UPDATE `".TABLE_PREFIX."mod_news_img_img` SET `bildbeschreibung` = '$bildbeschreibung' WHERE id = '$row_id'");
        }
    }
}

// if this went fine so far and we are moving posts across section borders we still have to reorder
if ((!($database->is_error()))&&($old_section_id!=$section_id)) {
    // Clean up ordering
    $order = new order(TABLE_PREFIX.'mod_news_img_posts', 'position', 'post_id', 'section_id');
    $order->clean($old_section_id);
}

//   exit;
// Check if there is a db error, otherwise say successful
if ($database->is_error()) {
    $admin->print_error($database->get_error(), WB_URL.'/modules/news_img/modify_post.php?page_id='.$page_id.'&section_id='.$section_id.'&post_id='.$id);
} else {
    if ($imageErrorMessage!='') {
        $admin->print_error($MOD_NEWS_IMG['GENERIC_IMAGE_ERROR'].'<br />'.$imageErrorMessage, WB_URL.'/modules/news_img/modify_post.php?page_id='.$page_id.'&section_id='.$section_id.'&post_id='.$id);
    } else {
        if (isset($_POST['savegoback']) && $_POST['savegoback']=='1') {
            $admin->print_success($TEXT['SUCCESS'], ADMIN_URL.'/pages/modify.php?page_id='.$page_id);
        } else {
            $admin->print_success($TEXT['SUCCESS'], WB_URL.'/modules/news_img/modify_post.php?page_id='.$page_id.'&section_id='.$section_id.'&post_id='.$id);
        }
    }
}

// Print admin footer
$admin->print_footer();
