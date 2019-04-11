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

require '../../config.php';

// Include WB admin wrapper script
require WB_PATH.'/modules/admin.php';

// Include the ordering class
require WB_PATH.'/framework/class.order.php';
// Get new order
$order = new order(TABLE_PREFIX.'mod_news_img_posts', 'position', 'post_id', 'section_id');
$position = $order->get_new($section_id);

// Get default commenting
$query_settings = $database->query("SELECT `commenting` FROM `".TABLE_PREFIX."mod_news_img_settings` WHERE `section_id` = '$section_id'");
$fetch_settings = $query_settings->fetchRow();
$commenting = $fetch_settings['commenting'];

// Insert new row into database
$sql = "INSERT INTO `".TABLE_PREFIX."mod_news_img_posts` (`section_id`,`page_id`,`position`,`link`,`content_short`,`content_long`,`commenting`,`active`) VALUES ('$section_id','$page_id','$position','','','','$commenting','1')";
$database->query($sql);

// Say that a new record has been added, then redirect to modify page
if($database->is_error()) {
	$admin->print_error($database->get_error(), WB_URL.'/modules/news_img/modify_post.php?page_id='.$page_id.'&section_id='.$section_id);
} else {
    // Get the id
    $post_id = $database->get_one("SELECT LAST_INSERT_ID()");
    $admin->print_success($TEXT['SUCCESS'], WB_URL.'/modules/news_img/modify_post.php?page_id='.$page_id.'&section_id='.$section_id.'&post_id='.$post_id);
}

// Print admin footer
$admin->print_footer();
