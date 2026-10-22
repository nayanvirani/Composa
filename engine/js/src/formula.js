// Sandboxed formula evaluator for price formulas. No eval: the expression is tokenised, parsed
// into a small tree and walked. Must stay in step with app/Engine/Formula.php — both run the
// fixtures in tests/engine/fixtures.
//
// Grammar (lowest to highest precedence):
//   or     := and ('||' and)*
//   and    := eq ('&&' eq)*
//   eq     := cmp (('==' | '!=') cmp)*
//   cmp    := add (('<' | '<=' | '>' | '>=') add)*
//   add    := mul (('+' | '-') mul)*
//   mul    := unary (('*' | '/' | '%') unary)*
//   unary  := ('-' | '!') unary | pow
//   pow    := primary ('^' unary)?
//   primary:= number | ident | ident '(' args ')' | '(' or ')'
// Booleans are 1 and 0. Division or modulo by zero yields 0.

export const FUNCTIONS = ['min', 'max', 'round', 'ceil', 'floor', 'abs', 'if'];
const MAX_LENGTH = 500;
const MAX_NODES = 200;

export class FormulaError extends Error {}

export function roundHalfUp(value, digits = 0) {
  const factor = Math.pow(10, digits);
  const sign = value < 0 ? -1 : 1;
  return (sign * Math.floor(Math.abs(value) * factor + 0.5)) / factor;
}

function tokenize(source) {
  if (typeof source !== 'string') throw new FormulaError('Formula must be text');
  if (source.length > MAX_LENGTH) throw new FormulaError('Formula is too long');
  const tokens = [];
  let i = 0;
  while (i < source.length) {
    const c = source[i];
    if (c === ' ' || c === '\t' || c === '\n' || c === '\r') { i++; continue; }
    if ((c >= '0' && c <= '9') || (c === '.' && source[i + 1] >= '0' && source[i + 1] <= '9')) {
      let j = i;
      while (j < source.length && ((source[j] >= '0' && source[j] <= '9') || source[j] === '.')) j++;
      const text = source.slice(i, j);
      if ((text.match(/\./g) || []).length > 1) throw new FormulaError('Bad number ' + text);
      tokens.push({ t: 'num', v: parseFloat(text) });
      i = j;
      continue;
    }
    if (/[A-Za-z_]/.test(c)) {
      let j = i;
      while (j < source.length && /[A-Za-z0-9_]/.test(source[j])) j++;
      tokens.push({ t: 'id', v: source.slice(i, j) });
      i = j;
      continue;
    }
    const two = source.slice(i, i + 2);
    if (['<=', '>=', '==', '!=', '&&', '||'].includes(two)) { tokens.push({ t: 'op', v: two }); i += 2; continue; }
    if ('+-*/%^()<>!,'.includes(c)) { tokens.push({ t: 'op', v: c }); i++; continue; }
    throw new FormulaError('Unexpected character ' + c);
  }
  return tokens;
}

export function parse(source) {
  const tokens = tokenize(source);
  let pos = 0;
  let nodes = 0;
  const peek = () => tokens[pos];
  const isOp = (v) => peek() && peek().t === 'op' && peek().v === v;
  const node = (n) => { if (++nodes > MAX_NODES) throw new FormulaError('Formula is too complex'); return n; };
  const expect = (v) => { if (!isOp(v)) throw new FormulaError('Expected ' + v); pos++; };

  const binary = (next, ops) => () => {
    let left = next();
    while (peek() && peek().t === 'op' && ops.includes(peek().v)) {
      const op = tokens[pos++].v;
      left = node({ k: 'bin', op, l: left, r: next() });
    }
    return left;
  };

  let orExpr;
  const primary = () => {
    const tok = tokens[pos];
    if (!tok) throw new FormulaError('Unexpected end of formula');
    if (tok.t === 'num') { pos++; return node({ k: 'num', v: tok.v }); }
    if (tok.t === 'id') {
      pos++;
      if (isOp('(')) {
        pos++;
        if (!FUNCTIONS.includes(tok.v)) throw new FormulaError('Unknown function ' + tok.v);
        const args = [];
        if (!isOp(')')) {
          args.push(orExpr());
          while (isOp(',')) { pos++; args.push(orExpr()); }
        }
        expect(')');
        return node({ k: 'call', f: tok.v, a: args });
      }
      return node({ k: 'var', n: tok.v });
    }
    if (tok.t === 'op' && tok.v === '(') {
      pos++;
      const inner = orExpr();
      expect(')');
      return inner;
    }
    throw new FormulaError('Unexpected ' + tok.v);
  };
  let unary;
  const pow = () => {
    const base = primary();
    if (isOp('^')) { pos++; return node({ k: 'bin', op: '^', l: base, r: unary() }); }
    return base;
  };
  unary = () => {
    if (isOp('-') || isOp('!')) { const op = tokens[pos++].v; return node({ k: 'un', op, e: unary() }); }
    return pow();
  };
  const mul = binary(unary, ['*', '/', '%']);
  const add = binary(mul, ['+', '-']);
  const cmp = binary(add, ['<', '<=', '>', '>=']);
  const eq = binary(cmp, ['==', '!=']);
  const and = binary(eq, ['&&']);
  orExpr = binary(and, ['||']);

  const tree = orExpr();
  if (pos !== tokens.length) throw new FormulaError('Unexpected ' + tokens[pos].v);
  return tree;
}

export function variablesOf(tree, out = new Set()) {
  if (tree.k === 'var') out.add(tree.n);
  if (tree.k === 'bin') { variablesOf(tree.l, out); variablesOf(tree.r, out); }
  if (tree.k === 'un') variablesOf(tree.e, out);
  if (tree.k === 'call') tree.a.forEach((a) => variablesOf(a, out));
  return out;
}

function run(n, vars) {
  switch (n.k) {
    case 'num': return n.v;
    case 'var':
      if (!Object.prototype.hasOwnProperty.call(vars, n.n)) throw new FormulaError('Unknown variable ' + n.n);
      return Number(vars[n.n]) || 0;
    case 'un': {
      const v = run(n.e, vars);
      return n.op === '-' ? -v : (v ? 0 : 1);
    }
    case 'bin': {
      const a = run(n.l, vars);
      const b = run(n.r, vars);
      switch (n.op) {
        case '+': return a + b;
        case '-': return a - b;
        case '*': return a * b;
        case '/': return b === 0 ? 0 : a / b;
        case '%': return b === 0 ? 0 : a % b;
        case '^': return Math.pow(a, b);
        case '<': return a < b ? 1 : 0;
        case '<=': return a <= b ? 1 : 0;
        case '>': return a > b ? 1 : 0;
        case '>=': return a >= b ? 1 : 0;
        case '==': return a === b ? 1 : 0;
        case '!=': return a !== b ? 1 : 0;
        case '&&': return a && b ? 1 : 0;
        case '||': return a || b ? 1 : 0;
      }
      break;
    }
    case 'call': {
      const args = n.a.map((x) => run(x, vars));
      switch (n.f) {
        case 'min': if (!args.length) throw new FormulaError('min needs arguments'); return Math.min(...args);
        case 'max': if (!args.length) throw new FormulaError('max needs arguments'); return Math.max(...args);
        case 'round': return roundHalfUp(args[0] ?? 0, args[1] ?? 0);
        case 'ceil': return Math.ceil(args[0] ?? 0);
        case 'floor': return Math.floor(args[0] ?? 0);
        case 'abs': return Math.abs(args[0] ?? 0);
        case 'if':
          if (args.length !== 3) throw new FormulaError('if needs 3 arguments');
          return args[0] ? args[1] : args[2];
      }
    }
  }
  throw new FormulaError('Bad formula');
}

export function evaluate(source, vars = {}) {
  const value = run(typeof source === 'string' ? parse(source) : source, vars);
  if (!Number.isFinite(value)) throw new FormulaError('Formula result is not a number');
  return value;
}
