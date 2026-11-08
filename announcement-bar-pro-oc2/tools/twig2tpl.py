#!/usr/bin/env python3
"""
twig2tpl.py — compiles the OpenCart 3 edition's Twig templates of Announcement Bar Pro into the plain PHP
(.tpl) templates of the OpenCart 2.x edition, so both editions always show the same markup.

Only the Twig subset the module uses is supported (output, if/elseif/else, for with loop.index0/first/last,
set, comments, filters e / e('url') / e('js') / format / length, tests "is [not] defined" / "is [not] empty",
attribute(), _context, ranges, array and hash literals, ~, in / not in, ternaries). Anything else is an error,
so a new Twig feature in the OC3 template can never be converted silently into something wrong.

Semantics match OpenCart 3's Twig set-up (autoescape off, strict_variables off): an undefined variable or
key is null, never a PHP notice — every lookup is guarded by isset().

Usage: python twig2tpl.py <in.twig> <out.tpl>          (or --check: exit 1 when out.tpl is not up to date)
"""
import re
import sys

# --------------------------------------------------------------------------- tokenizer
TOKEN_RE = re.compile(r"""
    (?P<ws>\s+)
  | (?P<num>\d+(?:\.\d+)?)
  | (?P<str>'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")
  | (?P<name>[A-Za-z_][A-Za-z0-9_]*)
  | (?P<op>\.\.|==|!=|<=|>=|\?:|[<>?:~+\-*/%|.\[\](),{}=])
""", re.X)

KEYWORD_OPS = {'and', 'or', 'not', 'in', 'is'}


def tokenize(src):
    pos, out = 0, []
    while pos < len(src):
        m = TOKEN_RE.match(src, pos)
        if not m:
            raise SyntaxError('cannot tokenize: %r' % src[pos:pos + 30])
        pos = m.end()
        kind = m.lastgroup
        if kind == 'ws':
            continue
        val = m.group(kind)
        if kind == 'name' and val in KEYWORD_OPS:
            kind = 'op'
        out.append((kind, val))
    out.append(('end', None))
    return out


# --------------------------------------------------------------------------- AST (tuples)
# ('lit', php) ('name', n) ('get', base, key_node) ('call', fname, args) ('filter', base, name, args)
# ('bin', op, a, b) ('not', a) ('neg', a) ('tern', c, a, b) ('elvis', a, b) ('test', node, test, negate)
# ('array', items) ('hash', pairs) ('range', a, b)

BIN_PREC = {
    'or': 10, 'and': 15,
    '==': 20, '!=': 20, '<': 20, '>': 20, '<=': 20, '>=': 20, 'in': 20, 'not in': 20,
    '..': 25, '+': 30, '-': 30, '~': 40, '*': 60, '/': 60, '%': 60,
}


class Parser:
    def __init__(self, src):
        self.t = tokenize(src)
        self.i = 0
        self.src = src

    def peek(self, k=0):
        return self.t[self.i + k]

    def next(self):
        tok = self.t[self.i]
        self.i += 1
        return tok

    def expect(self, val):
        tok = self.next()
        if tok[1] != val:
            raise SyntaxError('expected %r, got %r in %r' % (val, tok[1], self.src))
        return tok

    def at(self, val):
        return self.peek()[1] == val and self.peek()[0] in ('op', 'name')

    def parse_all(self):
        node = self.expression()
        if self.peek()[0] != 'end':
            raise SyntaxError('trailing tokens in %r' % self.src)
        return node

    # ternary is the lowest level
    def expression(self):
        cond = self.binary(0)
        if self.at('?'):
            self.next()
            a = self.expression()
            self.expect(':')
            b = self.expression()
            return ('tern', cond, a, b)
        if self.at('?:'):
            self.next()
            return ('elvis', cond, self.expression())
        return cond

    def binop(self):
        kind, val = self.peek()
        if kind != 'op':
            return None
        if val == 'not' and self.peek(1)[1] == 'in':
            return 'not in'
        return val if val in BIN_PREC else None

    def binary(self, min_prec):
        left = self.unary()
        while True:
            op = self.binop()
            if op is None or BIN_PREC[op] < min_prec:
                return left
            self.next()
            if op == 'not in':
                self.next()
            right = self.binary(BIN_PREC[op] + 1)
            left = ('range', left, right) if op == '..' else ('bin', op, left, right)

    def unary(self):
        if self.at('not'):
            self.next()
            return ('not', self.binary(50))
        if self.at('-'):
            self.next()
            return ('neg', self.unary())
        return self.postfix(self.primary())

    def primary(self):
        kind, val = self.next()
        if kind == 'num':
            return ('lit', val)
        if kind == 'str':
            body = val[1:-1]
            if val[0] == '"' and ('#{' in body):
                raise SyntaxError('string interpolation is not supported: %r' % val)
            return ('lit', "'" + body.replace('\\"', '"').replace("'", "\\'").replace("\\\\'", "\\'") + "'"
                    if val[0] == '"' else val)
        if kind == 'name':
            if val in ('true', 'false', 'null', 'none'):
                return ('lit', {'none': 'null'}.get(val, val))
            if self.at('('):
                self.next()
                return ('call', val, self.args(')'))
            return ('name', val)
        if val == '(':
            node = self.expression()
            self.expect(')')
            return node
        if val == '[':
            return ('array', self.args(']'))
        if val == '{':
            pairs = []
            while not self.at('}'):
                k = self.next()
                key = ("'%s'" % k[1]) if k[0] == 'name' else k[1]
                self.expect(':')
                pairs.append((key, self.expression()))
                if self.at(','):
                    self.next()
            self.next()
            return ('hash', pairs)
        raise SyntaxError('unexpected %r in %r' % (val, self.src))

    def args(self, close):
        out = []
        while not self.at(close):
            out.append(self.expression())
            if self.at(','):
                self.next()
        self.next()
        return out

    def postfix(self, node):
        while True:
            if self.at('.') and self.peek(1)[0] in ('name', 'num'):
                self.next()
                k = self.next()
                node = ('get', node, ('lit', "'%s'" % k[1] if k[0] == 'name' else k[1]))
            elif self.at('['):
                self.next()
                key = self.expression()
                self.expect(']')
                node = ('get', node, key)
            elif self.at('|'):
                self.next()
                name = self.next()[1]
                args = []
                if self.at('('):
                    self.next()
                    args = self.args(')')
                node = ('filter', node, name, args)
            elif self.at('is'):
                self.next()
                negate = False
                if self.at('not'):
                    self.next()
                    negate = True
                test = self.next()[1]
                if test not in ('defined', 'empty'):
                    raise SyntaxError('unsupported test %r' % test)
                node = ('test', node, test, negate)
            else:
                return node


# --------------------------------------------------------------------------- PHP code generation
def var(name):
    return '$_context' if name == '_context' else '$' + name


def path(node):
    """PHP lvalue-ish path for name / get chains rooted in a variable, else None."""
    if node[0] == 'name':
        return var(node[1])
    if node[0] == 'call' and node[1] == 'attribute' and len(node[2]) == 2:
        base = path(node[2][0])
        return base + '[' + php(node[2][1]) + ']' if base else None
    if node[0] == 'get':
        base = path(node[1])
        return base + '[' + php(node[2]) + ']' if base else None
    return None


def php(node):
    kind = node[0]
    if kind == 'lit':
        return node[1]
    if kind in ('name', 'get') or (kind == 'call' and node[1] == 'attribute'):
        p = path(node)
        if p is None:  # attribute of a non-variable value (never used by the templates)
            raise SyntaxError('unsupported attribute access: %r' % (node,))
        return '(isset(%s) ? %s : null)' % (p, p)
    if kind == 'call':
        raise SyntaxError('unsupported function %r' % node[1])
    if kind == 'filter':
        base, name, args = php(node[1]), node[2], node[3]
        if name in ('e', 'escape'):
            strategy = args[0][1].strip("'\"") if args else 'html'
            if strategy == 'html':
                return 'AbpOc2::e(%s)' % base
            if strategy == 'url':
                return 'rawurlencode((string)%s)' % base
            if strategy == 'js':
                return 'AbpOc2::escapeJs(%s)' % base
            raise SyntaxError('unsupported escape strategy %r' % strategy)
        if name == 'format':
            return 'sprintf(%s)' % ', '.join(['(string)' + base] + [php(a) for a in args])
        if name == 'length':
            return 'AbpOc2::length(%s)' % base
        raise SyntaxError('unsupported filter %r' % name)
    if kind == 'bin':
        op, a, b = node[1], php(node[2]), php(node[3])
        if op in ('and', 'or'):
            return cond(node)
        if op == '~':
            return '(%s . %s)' % (a, b)
        if op == 'in':
            return 'AbpOc2::in(%s, %s)' % (a, b)
        if op == 'not in':
            return '!AbpOc2::in(%s, %s)' % (a, b)
        return '(%s %s %s)' % (a, op, b)
    if kind == 'not':
        return cond(node)
    if kind == 'neg':
        return '-(%s)' % php(node[1])
    if kind == 'tern':
        return '(%s ? %s : %s)' % (cond(node[1]), php(node[2]), php(node[3]))
    if kind == 'elvis':
        return '(%s ?: %s)' % (php(node[1]), php(node[2]))
    if kind == 'test':
        p = path(node[1])
        if node[2] == 'defined':
            if p is None:
                raise SyntaxError('"is defined" needs a variable')
            code = 'isset(%s)' % p
        else:
            code = 'empty(%s)' % p if p else 'AbpOc2::isEmpty(%s)' % php(node[1])
        return '!' + code if node[3] else code
    if kind == 'array':
        return 'array(%s)' % ', '.join(php(a) for a in node[1])
    if kind == 'hash':
        return 'array(%s)' % ', '.join('%s => %s' % (k, php(v)) for k, v in node[1])
    if kind == 'range':
        return 'range(%s, %s)' % (php(node[1]), php(node[2]))
    raise SyntaxError('cannot compile %r' % (node,))


def cond(node):
    """PHP for a node used as a condition: a plain variable / key becomes !empty() (same truthiness as Twig)."""
    p = path(node) if node[0] in ('name', 'get', 'call') else None
    if p:
        return '!empty(%s)' % p
    if node[0] == 'bin' and node[1] in ('and', 'or'):
        return '(%s %s %s)' % (cond(node[2]), '&&' if node[1] == 'and' else '||', cond(node[3]))
    if node[0] == 'not':
        inner = node[1]
        p = path(inner) if inner[0] in ('name', 'get', 'call') else None
        return 'empty(%s)' % p if p else '!(%s)' % cond(inner)
    return php(node)


def expr(src):
    return php(Parser(src).parse_all())


def cond_expr(src):
    return cond(Parser(src).parse_all())


# --------------------------------------------------------------------------- template compiler
TAG_RE = re.compile(r'\{#.*?#\}|\{\{(.*?)\}\}|\{%(.*?)%\}', re.S)


def compile_template(src):
    out = []
    stack = []  # 'if' / ('for', n)
    loops = 0
    pos = 0
    for m in TAG_RE.finditer(src):
        out.append(src[pos:m.start()].replace('<?', "<?php echo '<?'; ?>"))
        pos = m.end()
        token = m.group(0)
        if token.startswith('{#'):
            # Twig drops one newline after a comment tag, like after block tags.
            if src.startswith('\n', pos):
                pos += 1
            continue
        if m.group(1) is not None:
            # PHP swallows one newline after "?>"; Twig keeps it after an output tag — add it back.
            out.append('<?php echo %s; ?>' % expr(m.group(1).strip()) + ('\n' if src.startswith('\n', pos) else ''))
            continue
        stmt = m.group(2).strip()
        word = stmt.split(None, 1)[0]
        rest = stmt[len(word):].strip()
        if word == 'if':
            stack.append('if')
            out.append('<?php if (%s) { ?>' % cond_expr(rest))
        elif word == 'elseif':
            out.append('<?php } elseif (%s) { ?>' % cond_expr(rest))
        elif word == 'else':
            if stack and isinstance(stack[-1], tuple) and stack[-1][0] == 'for':
                n = stack.pop()[1]  # for ... else: runs when the sequence is empty
                out.append('<?php $_i%d++; } $loop = $_o%d; if (!$_n%d) { ?>' % (n, n, n))
                stack.append(('forelse', n))
            elif stack and stack[-1] == 'if':
                out.append('<?php } else { ?>')
            else:
                raise SyntaxError('unbalanced else')
        elif word == 'endif':
            if stack.pop() != 'if':
                raise SyntaxError('unbalanced endif')
            out.append('<?php } ?>')
        elif word == 'for':
            fm = re.match(r'^([A-Za-z_]\w*)(?:\s*,\s*([A-Za-z_]\w*))?\s+in\s+(.+)$', rest, re.S)
            if not fm:
                raise SyntaxError('bad for: %r' % stmt)
            loops += 1
            n = loops
            k, v = (fm.group(1), fm.group(2)) if fm.group(2) else (None, fm.group(1))
            seq = expr(fm.group(3))
            head = '$_s%d = (array)%s; $_n%d = count($_s%d); $_i%d = 0; $_o%d = isset($loop) ? $loop : null; ' % (n, seq, n, n, n, n)
            each = ('foreach ($_s%d as $%s => $%s) { ' % (n, k, v)) if k else ('foreach ($_s%d as $%s) { ' % (n, v))
            loopvar = "$loop = array('index0' => $_i%d, 'index' => $_i%d + 1, 'first' => $_i%d === 0, 'last' => $_i%d === $_n%d - 1);" % (n, n, n, n, n)
            out.append('<?php ' + head + each + loopvar + ' ?>')
            stack.append(('for', n))
        elif word == 'endfor':
            top = stack.pop()
            if not isinstance(top, tuple):
                raise SyntaxError('unbalanced endfor')
            n = top[1]
            out.append('<?php } ?>' if top[0] == 'forelse' else '<?php $_i%d++; } $loop = $_o%d; ?>' % (n, n))
        elif word == 'set':
            sm = re.match(r'^([A-Za-z_]\w*)\s*=\s*(.+)$', rest, re.S)
            if not sm:
                raise SyntaxError('bad set: %r' % stmt)
            out.append('<?php $%s = %s; ?>' % (sm.group(1), expr(sm.group(2))))
        else:
            raise SyntaxError('unsupported tag %r' % stmt)
    out.append(src[pos:].replace('<?', "<?php echo '<?'; ?>"))
    if stack:
        raise SyntaxError('unclosed block(s): %r' % stack)
    return ''.join(out)


# OpenCart 2.x specifics applied to the converted markup (kept small and explicit).
POST = [
    # Language flags: 2.0/2.1 keep them in view/image/flags/, 2.2/2.3 in language/<code>/ — the controller passes the URL.
    ("<img src=\"language/<?php echo rawurlencode((string)(isset($language['code']) ? $language['code'] : null)); ?>/"
     "<?php echo rawurlencode((string)(isset($language['code']) ? $language['code'] : null)); ?>.png\" alt=\"\" />",
     "<img src=\"<?php echo AbpOc2::e(isset($language['flag']) ? $language['flag'] : null); ?>\" alt=\"\" />"),
]

HEADER = ("<?php defined('VERSION') || exit; /* Generated from the OpenCart 3 Twig template by tools/twig2tpl.py"
          " — edit the Twig source, then rebuild. */ ?>\n")


def convert(text):
    code = compile_template(text.replace('\r\n', '\n'))
    for old, new in POST:
        if old in code:
            code = code.replace(old, new)
    return HEADER + code


def main(argv):
    check = '--check' in argv
    args = [a for a in argv if a != '--check']
    if len(args) != 2:
        sys.stderr.write(__doc__)
        return 2
    with open(args[0], encoding='utf-8') as f:
        code = convert(f.read())
    if check:
        try:
            with open(args[1], encoding='utf-8', newline='') as f:
                return 0 if f.read() == code else 1
        except FileNotFoundError:
            return 1
    with open(args[1], 'w', encoding='utf-8', newline='\n') as f:
        f.write(code)
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
