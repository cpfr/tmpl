## Classes
### Tmpl
- __construct($filename)
- parse($input) : array<Node\>
- render($params) : string

### Lexer
- lex($text) : array<Token\>

### Parser <abstract\>
- __construct($tokenstream)
- current() : Token
- currentType() : string
- accept($tokenType) : Token
- parse() : array<Node\>
- parseStep() : Node
    
### TmplParser
- __construct($tokenstream)
- current() : Token
- currentType() : string
- accept($tokenType) : Token
- parse() : array<Node\>
- parseStep() : Node
- parseText() : TextNode
- parseIf() : IfNode
- parseElif() : IfNode
- parseElse() : array<Node\>
- parseFor() : ForNode
- parseBlock() : BlockNode
- parseOutput() : OutputNode

### ExpressionParser
- __construct($text)
- current() : Token
- currentType() : string
- accept($tokenType) : Token
- parse() : Node
- parseStep() : Node
- parseParenthesis() : Node
- parseUnaryOperator() : Node
- parseAtom() : Node
- parseString() : Node
- parseNumber() : Node
- parseIdentifier() : Node
    
### SyntaxException
- __construct($token, $expectedType, $msg=null)
- getToken() : Token
- __toString() : string
    
### ContextException
- __construct($msg)
- __toString() : string

### ASTNode <abstract\>
*abstract base for node classes*

- evaluate($params) : string

### TextNode
*a node which only contains text*

- __construct($token)
- evaluate($params) : string

### OutputNode
*a node which contains an expression which should be printed*

- __construct($token, $text=null)
- evaluate($params) : string

### VariableNode
**Expression:** *a node which contains a variable name (and possibly access to its properties)*

- __construct($token, $text=null)
- evaluate($params) : string

### IfNode
*a node which contains an if statement (and possibly its elif and else blocks)*

- __construct($token)
- evaluate($params) : string
- setTrue($nodeArray)  
- setFalse($nodeArray)
- addTrue($node)
- addFalse($node)

### ForNode
*a node which contains a for loop*

- __construct($token)
- evaluate($params) : string
- setBody($nodeArray)
- addBody($node)

### BlockNode
*a node which contains a block*

- __construct($token)
- evaluate($params) : string
- setBody($nodeArray)
- addBody($node)
- setChild($block)
- setParent($block)
- getChild() : BlockNode
- getParent() : BlockNode
- getUltimateChild() : BlockNode
- getUltimateParent() : BlockNode
- getName() : string

### UnaryOperatorNode
**Expression:** *a node which contains a unary operator and an expression to which it should be applied*

- __construct($value, $exp)
- evaluate($params) : string

### BinaryOperatorNode
**Expression:** *a node which contains a binary operator and two expressions to which it should be applied*
- __construct($left, $operator, $right)
- evaluate($params) : string

### ValueNode
**Expression:** *a node which contains a simple value (string, number or atom)*

- __construct($value)
- evaluate($params) : string
    
### Token
- __construct($text, $type)
- getText() : string
- getType() : string