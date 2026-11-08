<?php
defined('VERSION') || exit; // not callable directly
/**
 * Announcement Bar Pro — OpenCart 2.0.x–2.2.x entry point (Extensions › Modules links to module/announcement_bar_pro).
 * The controller is system/library/announcementbarpro/admin_controller.php (shared with the 2.3 package); it works
 * out its own route, permissions and links (AbpOc2).
 * @version 2.4.1
 */
require_once(DIR_SYSTEM . 'library/announcementbarpro/admin_controller.php');

class ControllerModuleAnnouncementBarPro extends AbpAdminController {
}
