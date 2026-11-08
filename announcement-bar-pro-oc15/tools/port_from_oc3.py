"""One-time porting helper: copies the OpenCart 3 PHP sources of Announcement Bar Pro and applies the mechanical
OpenCart 1.5 renames (class names, routes, setting code, token, models, PHP 5.6 catch blocks). The result was then
edited by hand; this script is kept as documentation of the mechanical part only. Run: python tools/port_from_oc3.py"""
import re, sys, os
OC3 = os.path.join(os.path.dirname(__file__), '..', '..', 'announcement-bar-pro', 'upload')
OUT = os.path.join(os.path.dirname(__file__), '..', 'upload')
FILES = {
    'admin/controller/extension/module/announcement_bar_pro.php': 'admin/controller/module/announcement_bar_pro.php',
    'admin/model/extension/module/announcement_bar_pro.php': 'admin/model/module/announcement_bar_pro.php',
    'catalog/controller/extension/module/announcement_bar_pro.php': 'catalog/controller/module/announcement_bar_pro.php',
    'catalog/model/extension/module/announcement_bar_pro.php': 'catalog/model/module/announcement_bar_pro.php',
}
for lang3, lang15 in (('en-gb', 'english'), ('lv-lv', 'latvian'), ('ru-ru', 'russian')):
    for side in ('admin', 'catalog'):
        FILES['%s/language/%s/extension/module/announcement_bar_pro.php' % (side, lang3)] = '%s/language/%s/module/announcement_bar_pro.php' % (side, lang15)
REPL = [
    ('ControllerExtensionModuleAnnouncementBarPro', 'ControllerModuleAnnouncementBarPro'),
    ('ModelExtensionModuleAnnouncementBarPro', 'ModelModuleAnnouncementBarPro'),
    ("'extension/module/announcement_bar_pro'", "'module/announcement_bar_pro'"),
    ("'module_announcement_bar_pro'", "'announcement_bar_pro'"),
    ('model_extension_module_announcement_bar_pro', 'model_module_announcement_bar_pro'),
    ("'user_token=' . $this->session->data['user_token']", "'token=' . $this->session->data['token']"),
    ("$this->link('marketplace/extension', '&type=module')", "$this->link('extension/module')"),
    ("'extension/extension/module'", "'extension/module'"),
    ("'customer/customer_group'", "'sale/customer_group'"),
    ('model_customer_customer_group', 'model_sale_customer_group'),
    ('$this->customer->getGroupId()', '$this->customer->getCustomerGroupId()'),
    ('$this->session->getId()', 'session_id()'),
    ("'shipping_free_status'", "'free_status'"),
    ("'shipping_free_total'", "'free_total'"),
    ("'config_mail_engine'", "'config_mail_protocol'"),
    ("'no_image.png'", "'no_image.jpg'"),
    ('$this->response->redirect(', '$this->redirect('),
    ('(OpenCart 3.0.x)', '(OpenCart 1.5.x)'),
]
CATCH = re.compile(r'catch \(.Throwable (\$\w+)\) \{([^{}]*)\}')
for src, dst in FILES.items():
    s = open(os.path.join(OC3, src), encoding='utf-8').read()
    for a, b in REPL:
        s = s.replace(a, b)
    # PHP 5.6 has no Throwable: catch Exception there, Throwable (Error) on PHP 7+.
    BS = chr(92)
    s = CATCH.sub(lambda m: 'catch (%sException %s) {%s} catch (%sThrowable %s) {%s}' % (BS, m.group(1), m.group(2), BS, m.group(1), m.group(2)), s)
    p = os.path.join(OUT, dst)
    os.makedirs(os.path.dirname(p), exist_ok=True)
    open(p, 'w', encoding='utf-8', newline='\n').write(s)
    print(dst)
