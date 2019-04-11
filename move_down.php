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

// Get id
if(!isset($_GET['post_id']) OR !is_numeric($_GET['post_id'])) {
	if(!isset($_GET['group_id']) OR !is_numeric($_GET['group_id'])) {
		header("Location: index.php");
		exit(0);
	} else {
		$id = $_GET['group_id'];
		$id_field = 'group_id';
		$table = TABLE_PREFIX.'mod_news_img_groups';
	}
} else {
	$id = $_GET['post_id'];
	$id_field = 'post_id';
	$table = TABLE_PREFIX.'mod_news_img_posts';
}

// Include WB admin wrapper script
require WB_PATH.'/modules/admin.php';

// Include the ordering class
require WB_PATH.'/framework/class.order.php';

// Create new order object an reorder
$order = new order($table, 'position', $id_field, 'section_id');
if($order->move_down($id)) {
	$admin->print_success($TEXT['SUCCESS'], ADMIN_URL.'/pages/modify.php?page_id='.$page_id);
} else {
	$admin->print_error($TEXT['ERROR'], ADMIN_URL.'/pages/modify.php?page_id='.$page_id);
}

// Print admin footer
$admin->print_footer();
