#!/bin/bash
# Removes every trace of the extension from an OpenCart 4 test shop: $1 = db, $2 = shop dir
DB=$1; DIR=$2
mysql $DB -e "DELETE FROM oc_event WHERE code='announcement_bar_pro'; DELETE FROM oc_setting WHERE code='module_announcement_bar_pro'; DELETE FROM oc_extension WHERE code='announcement_bar_pro';
DELETE p FROM oc_extension_path p JOIN oc_extension_install i ON i.extension_install_id=p.extension_install_id WHERE i.code='announcement_bar_pro';
DELETE FROM oc_extension_install WHERE code='announcement_bar_pro'; DROP TABLE IF EXISTS oc_abp_message, oc_abp_message_description;
DELETE FROM oc_user WHERE username='viewer'; DELETE FROM oc_user_group WHERE name='Viewer';"
[ -d "$DIR/extension/announcement_bar_pro" ] && find "$DIR/extension/announcement_bar_pro" -delete
find "$DIR/system/storage/marketplace" -name 'announcement_bar_pro*' -delete 2>/dev/null
echo "reset $DB done"
