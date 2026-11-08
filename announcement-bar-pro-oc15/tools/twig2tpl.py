"""twig2tpl.py — converts the Twig templates of the OpenCart 3 edition into OpenCart 1.5 PHP templates (.tpl).

Covers the Twig subset those templates use: {{ }}, {# #}, if / elseif / else, for (with key, ranges, loop.first /
loop.last / loop.index), set, attribute(), the filters e / e('url') / e('js') / format / length and the tests
"is defined" / "is not defined". Twig semantics kept: a missing variable or key is null (no PHP notice), loop variables
do not leak out of their loop. The output calls the small helpers in system/library/announcementbarpro/tpl.php.

Usage: python tools/twig2tpl.py <in.twig> <out.tpl> [--admin15]
--admin15 applies the OpenCart 1.5 admin page changes (header / footer, setting names, scoped styles).
"""
import re
import sys

PREC = {'or': 10, 'and': 15, '==': 20, '!=': 20, '<': 20, '>': 20, '<=': 20, '>=': 20, 'in': 20, 'not in': 20,
        '..': 25, '+': 30, '-': 30, '~': 40, '*': 60, '/': 60, '%': 60}
PHP_OP = {'or': '||', 'and': '&&', '~': '.', '==': '==', '!=': '!=', '<': '<', '>': '>', '<=': '<=', '>=': '>=',
          '+': '+', '-': '-', '*': '*', '/': '/', '%': '%'}

TOKEN = re.compile(r"""\s*(?:
    (?P<str>'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")|
    (?P<num>\d+(?:\.\d+)?)|
    (?P<op>\.\.|==|!=|<=|>=|[-+*/%~<>?:.,|()\[\]{}=])|
    (?P<name>[A-Za-z_][A-Za-z0-9_]*)
)""", re.X)


def tokenize(src):
    pos, out = 0, []
    src = src.strip()
    while pos < len(src):
        m = TOKEN.match(src, pos)
        if not m or m.end() == pos:
            raise SyntaxError('cannot tokenize at %r' % src[pos:pos + 20])
        pos = m.end()
        for kind in ('str', 'num', 'op', 'name'):
            if m.group(kind) is not None:
                out.append((kind, m.group(kind)))
                break
    out.append(('end', None))
    return out


class Parser:
    def __init__(self, src, ctx):
        self.t = tokenize(src)
        self.i = 0
        self.ctx = ctx  # Converter (loop stack)

    def peek(self, k=0):
        return self.t[self.i + k]

    def next(self):
        tok = self.t[self.i]
        self.i += 1
        return tok

    def expect(self, val):
        tok = self.next()
        if tok[1] != val:
            raise SyntaxError('expected %r, got %r' % (val, tok))

    # ---- expressions: AST nodes are tuples ----
    def parse(self):
        node = self.expr(0)
        if self.peek()[0] != 'end':
            raise SyntaxError('trailing tokens: %r' % self.t[self.i:])
        return node

    def expr(self, min_prec):
        left = self.unary()
        while True:
            kind, val = self.peek()
            op = None
            if kind == 'name' and val in ('and', 'or', 'in'):
                op = val
            elif kind == 'name' and val == 'not' and self.peek(1) == ('name', 'in'):
                op = 'not in'
            elif kind == 'name' and val == 'is':
                if 100 < min_prec:
                    break
                self.next()
                neg = False
                if self.peek() == ('name', 'not'):
                    self.next()
                    neg = True
                test = self.next()[1]
                if test != 'defined':
                    raise SyntaxError('unsupported test %r' % test)
                left = ('defined', left, neg)
                continue
            elif kind == 'op' and val in PREC:
                op = val
            elif kind == 'op' and val == '?' and min_prec == 0:
                self.next()
                a = self.expr(0)
                self.expect(':')
                b = self.expr(0)
                left = ('cond', left, a, b)
                continue
            if op is None or PREC[op] < min_prec:
                break
            self.next()
            if op == 'not in':
                self.next()
            right = self.expr(PREC[op] + 1)
            left = ('bin', op, left, right)
        return left

    def unary(self):
        kind, val = self.peek()
        if kind == 'name' and val == 'not':
            self.next()
            return ('not', self.expr(50))
        if kind == 'op' and val == '-':
            self.next()
            return ('neg', self.postfix(self.primary()))
        return self.postfix(self.primary())

    def primary(self):
        kind, val = self.next()
        if kind == 'str':
            return ('lit', "'" + val[1:-1].replace("\\", "\\\\").replace("'", "\\'") + "'" if val[0] == '"' else val)
        if kind == 'num':
            return ('lit', val)
        if kind == 'op' and val == '(':
            e = self.expr(0)
            self.expect(')')
            return ('paren', e)
        if kind == 'op' and val == '[':
            items = []
            while self.peek()[1] != ']':
                items.append(self.expr(0))
                if self.peek()[1] == ',':
                    self.next()
            self.next()
            return ('array', items)
        if kind == 'name':
            if val in ('true', 'false', 'null'):
                return ('lit', val)
            if val == 'attribute' and self.peek()[1] == '(':
                self.next()
                base = self.expr(0)
                self.expect(',')
                key = self.expr(0)
                self.expect(')')
                if base == ('var', '_context'):
                    return ('dynvar', key)
                return ('path', base, key)
            return ('var', val)
        raise SyntaxError('unexpected %r' % ((kind, val),))

    def postfix(self, node):
        while True:
            kind, val = self.peek()
            if kind == 'op' and val == '.' and self.peek(1)[0] == 'name':
                self.next()
                node = ('path', node, ('lit', "'" + self.next()[1] + "'"))
            elif kind == 'op' and val == '[':
                self.next()
                key = self.expr(0)
                self.expect(']')
                node = ('path', node, key)
            elif kind == 'op' and val == '|':
                self.next()
                name = self.next()[1]
                args = []
                if self.peek()[1] == '(':
                    self.next()
                    while self.peek()[1] != ')':
                        args.append(self.expr(0))
                        if self.peek()[1] == ',':
                            self.next()
                    self.next()
                node = ('filter', name, node, args)
            else:
                return node


def lvalue(node, ctx):
    """PHP variable expression for a variable / path / dynamic variable, or None."""
    if node[0] == 'var':
        name = node[1]
        if name == 'loop':
            if not ctx.loops:
                raise SyntaxError('loop outside of for')
            return '$' + ctx.loops[-1]
        return '$' + name
    if node[0] == 'dynvar':
        return '${' + php(node[1], ctx) + '}'
    if node[0] == 'path':
        base = lvalue(node[1], ctx)
        if base is None:
            return None
        return base + '[' + php(node[2], ctx) + ']'
    return None


def php(node, ctx):
    k = node[0]
    if k == 'lit':
        return node[1]
    if k in ('var', 'path', 'dynvar'):
        lv = lvalue(node, ctx)
        if node[0] == 'var' and node[1] == 'loop':
            return lv
        return 'abp15_v(' + lv + ')'
    if k == 'paren':
        return '(' + php(node[1], ctx) + ')'
    if k == 'array':
        return 'array(' + ', '.join(php(x, ctx) for x in node[1]) + ')'
    if k == 'not':
        return '!(' + php(node[1], ctx) + ')'
    if k == 'neg':
        return '-' + php(node[1], ctx)
    if k == 'cond':
        return '(' + php(node[1], ctx) + ' ? ' + php(node[2], ctx) + ' : ' + php(node[3], ctx) + ')'
    if k == 'defined':
        lv = lvalue(node[1], ctx)
        if lv is None:
            raise SyntaxError('"is defined" needs a variable')
        return ('!' if node[2] else '') + 'isset(' + lv + ')'
    if k == 'bin':
        op, a, b = node[1], php(node[2], ctx), php(node[3], ctx)
        if op == '..':
            return 'range(' + a + ', ' + b + ')'
        if op == 'in':
            return 'abp15_in(' + a + ', ' + b + ')'
        if op == 'not in':
            return '!abp15_in(' + a + ', ' + b + ')'
        return '(' + a + ' ' + PHP_OP[op] + ' ' + b + ')'
    if k == 'filter':
        name, val, args = node[1], php(node[2], ctx), [php(x, ctx) for x in node[3]]
        if name in ('e', 'escape'):
            mode = args[0].strip("'") if args else 'html'
            return {'html': 'abp15_e(', 'url': 'abp15_url(', 'js': 'abp15_js('}[mode] + val + ')'
        if name == 'format':
            return 'sprintf(' + ', '.join([val] + args) + ')'
        if name == 'length':
            return 'abp15_len(' + val + ')'
        raise SyntaxError('unsupported filter ' + name)
    raise SyntaxError('node ' + k)


class Converter:
    def __init__(self):
        self.loops = []   # PHP names of the loop arrays ("loop1", ...)
        self.blocks = []  # 'if' / ('for', n, vars)
        self.n = 0

    def expr(self, src):
        return php(Parser(src, self).parse(), self)

    def stmt(self, body):
        m = re.match(r'(\w+)\s*(.*)$', body.strip(), re.S)
        word, rest = m.group(1), m.group(2)
        if word == 'if':
            self.blocks.append('if')
            return 'if (' + self.expr(rest) + ') { '
        if word == 'elseif':
            return '} elseif (' + self.expr(rest) + ') { '
        if word == 'else':
            top = self.blocks[-1]
            if top != 'if':
                # for ... else: the else part runs when the sequence was empty (the loop variables are gone already)
                self.loops.pop()
                self.blocks[-1] = ('forelse', top[1], top[2])
                return '} if ($__c%d === 0) { ' % top[1]
            return '} else { '
        if word == 'endif':
            assert self.blocks.pop() == 'if'
            return '} '
        if word == 'set':
            name, value = re.match(r'(\w+)\s*=\s*(.*)$', rest, re.S).groups()
            return '$' + name + ' = ' + self.expr(value) + '; '
        if word == 'for':
            fm = re.match(r'(\w+)(?:\s*,\s*(\w+))?\s+in\s+(.*)$', rest, re.S)
            a, b, src = fm.groups()
            self.n += 1
            n = self.n
            names = [x for x in (a, b) if x]
            seq = self.expr(src)
            self.loops.append('loop%d' % n)
            self.blocks.append(('for', n, names))
            each = ('$%s => $%s' % (a, b)) if b else ('$' + a)
            # Twig: the loop variables do not exist outside the loop (an outer variable of the same name comes back).
            save = '$__k%d = array(); foreach (array(%s) as $__x) { $__k%d[$__x] = isset($$__x) ? array($$__x) : null; } ' % (
                n, ', '.join("'%s'" % x for x in names), n)
            return (save + '$__s%d = (array)%s; $__c%d = count($__s%d); $__i%d = 0; foreach ($__s%d as %s) { '
                    '$loop%d = array(\'index0\' => $__i%d, \'index\' => $__i%d + 1, \'first\' => $__i%d === 0, \'last\' => $__i%d === $__c%d - 1); $__i%d++; ') % (
                n, seq, n, n, n, n, each, n, n, n, n, n, n, n)
        if word == 'endfor':
            kind, n, names = self.blocks.pop()
            if kind == 'for':
                self.loops.pop()
            return '} foreach ($__k%d as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ' % n
        raise SyntaxError('unsupported tag ' + word)

    def convert(self, src):
        src = re.sub(r'\{#.*?#\}', '', src, flags=re.S)
        out, pos = [], 0
        for m in re.finditer(r'\{\{(.*?)\}\}|\{%-?(.*?)-?%\}', src, re.S):
            out.append(src[pos:m.start()])
            if m.group(1) is not None:
                out.append('<?php echo ' + self.expr(m.group(1)) + '; ?>')
            else:
                out.append('<?php ' + self.stmt(m.group(2)) + '?>')
            pos = m.end()
        out.append(src[pos:])
        assert not self.blocks, self.blocks
        return ''.join(out)


def admin15(src):
    """OpenCart 1.5 admin page: header / footer, setting names, scoped styles, no column_left."""
    def sub(old, new, count=1):
        nonlocal src
        if src.count(old) != count:
            raise SystemExit('admin15: %r found %d times, expected %d' % (old[:60], src.count(old), count))
        src = src.replace(old, new)
    sub('{{ header }}{{ column_left }}', '{{ header }}')
    src = src.replace('module_announcement_bar_pro_', 'announcement_bar_pro_')
    # Own scoped styles (OpenCart 1.5 admin has no Bootstrap) and the bundled Font Awesome 4.7.
    sub('<div id="content" class="abp-admin">\n  <link rel="stylesheet" href="{{ admin_css|e }}" />',
        '<div id="content" class="abp-admin abp15">\n  <link rel="stylesheet" href="{{ fa_css|e }}" />\n'
        '  <link rel="stylesheet" href="{{ admin_css|e }}" />\n  <link rel="stylesheet" href="{{ oc15_css|e }}" />')
    # Image manager of OpenCart 1.5 (jQuery UI dialog with common/filemanager) instead of Bootstrap's popover.
    if 'data-toggle="image"' in src:
        sub('<a href="" id="thumb-image" data-toggle="image" class="img-thumbnail" aria-label="{{ entry_image|e }}"><img src="{{ image_thumb|e }}" alt="" data-placeholder="{{ placeholder|e }}" /></a>',
            '<a href="#" id="thumb-image" data-abp15-image="input-image" data-manager="{{ filemanager|e }}" data-thumb-url="{{ filemanager_image|e }}" data-title="{{ text_image_manager|e }}" class="img-thumbnail" aria-label="{{ entry_image|e }}"><img src="{{ image_thumb|e }}" alt="" data-placeholder="{{ placeholder|e }}" /></a>'
            '<button type="button" class="btn btn-default btn-sm abp15-image-clear" data-abp15-clear="input-image"><i class="fa fa-times"></i> {{ button_clear_image }}</button>')
    # Settings: "Load the icon font" (OpenCart 1.5 edition only), after "Wait for cookie consent".
    if 'help_consent_wait' in src:
        sub("""                  <span class="abp-help">{{ help_consent_wait }}</span>
                </div>
              </div>
""", """                  <span class="abp-help">{{ help_consent_wait }}</span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-fa-load">{{ entry_fa_load }}</label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_fa_load" id="input-fa-load" value="1"{% if setting.fa_load %} checked="checked"{% endif %} /> {{ text_yes }}</label></div>
                  <span class="abp-help">{{ help_fa_load }}</span>
                </div>
              </div>
""")
    # Language flags: OpenCart 1.5 keeps them in view/image/flags/<image>.
    if 'src="language/' in src:
        sub("""src="language/{{ language.code|e('url') }}/{{ language.code|e('url') }}.png" """.strip(), """src="view/image/flags/{{ language.image|e('url') }}" """.strip())
    # Date + time: jQuery UI timepicker of OpenCart 1.5 (abp_oc15.js) instead of Bootstrap's datetimepicker.
    if 'jQuery.fn.datetimepicker' in src:
        sub("""if (window.jQuery && jQuery.fn.datetimepicker) {
  jQuery('.datetime').datetimepicker({ language: '{{ datepicker|e('js') }}', pickDate: true, pickTime: true });
}
""", '')
    sub('{{ footer }}', '<script type="text/javascript" src="view/javascript/jquery/ui/jquery-ui-timepicker-addon.js"></script>\n'
        '<script type="text/javascript" src="{{ oc15_js|e }}"></script>\n{{ footer }}')
    return src


if __name__ == '__main__':
    src = open(sys.argv[1], encoding='utf-8').read()
    if '--admin15' in sys.argv:
        src = admin15(src)
    out = Converter().convert(src)
    head = '<?php /* Announcement Bar Pro 2.4.1 — generated from the OpenCart 3 Twig template by tools/twig2tpl.py, then adapted for OpenCart 1.5. */ ?>\n'
    open(sys.argv[2], 'w', encoding='utf-8', newline='\n').write(head + out)
    print(sys.argv[2])
