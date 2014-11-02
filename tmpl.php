<?php

// -----------------------------------------------------------------------------
// Template class
// -----------------------------------------------------------------------------

class Tmpl
{
    protected $tokens = array();
    protected $ast = array();

    public function __construct($filename){
        $this->tree = $this->parse(file_get_contents($filename));
    }

    protected function parse($input){
        // find control tokens and normal text
        $pattern = '/({%.*?%})|({{.*?}})|({#.*?#})/';
        $matches = array();
        preg_match_all($pattern, $input, $matches);
        $matches = $matches[0];
        $rest = preg_split($pattern, $input);

        // convert control strings into control tokens
        $controlTokens = array();
        foreach($matches as $match){
            // find out the {% {{ or {#
            $controlChar = substr($match, 0, 2);
            // cut off the braces
            $match = trim(substr($match, 2, strlen($match)-4));

            if($controlChar == '{%'){
                if(substr($match, 0, 2) == 'if'){
                    $controlTokens[] = new Token($match, 'IfToken');
                }
                else if(substr($match, 0, 4) == 'elif'){
                    $controlTokens[] = new Token($match, 'ElifToken');
                }
                else if(substr($match, 0, 4) == 'else'){
                    $controlTokens[] = new Token($match, 'ElseToken');
                }
                else if(substr($match, 0, 3) == 'for'){
                    $controlTokens[] = new Token($match, 'ForToken');
                }
                else if(substr($match, 0, 3) == 'end'){
                    $controlTokens[] = new Token($match, 'EndToken');
                }
            }
            else if($controlChar == '{{'){
                $controlTokens[] = new Token($match, 'OutputToken');
            }
            else if($controlChar == '{#'){
                // throw away the result -- it's just a comment
                // empty text token for the number of text and
                // control tokens to be balanced
                $controlTokens[] = new Token('', 'TextToken');
            }
            else{
                $controlTokens[] = new Token($match, 'TextToken');
            }
        }
        $rest = array_map(function($element){
            return new Token($element, 'TextToken');
        }, $rest);

        // first there is always standard text (no control tokens)
        // there is always one control token more than text tokens
        $tokenstream = array();
        for($i = 0; $i < count($rest)-1; $i++){
            $tokenstream[] = $rest[$i];
            $tokenstream[] = $controlTokens[$i];
        }
        $tokenstream[] = $rest[count($rest)-1]; // also add the last element

        $parser = new TmplParser($tokenstream);
        $this->ast = $parser->parse();
    }

    public function render($params){
        $output = '';
        // evaluate the AST
        foreach($this->ast as $node){
            $output .= $node->evaluate($params);
        }
        return $output;
    }
}

// -----------------------------------------------------------------------------
// Parser classes
// -----------------------------------------------------------------------------
class Parser
{
    protected $tokenstream = array();
    protected $i = 0;

    public function __construct($tokenstream){
        $this->tokenstream = $tokenstream;
        $this->tokenstream[] = new Token('EOF', 'EOFToken');
    }

    protected function current(){
        return $this->tokenstream[$this->i];
    }

    protected function currentType(){
        return $this->current()->getType();
    }

    protected function accept($tokenType){
        if($this->currentType() == $tokenType){
            $output = $this->current();
            $this->i++;
            return $output;
        }
        else{
            throw new SyntaxException($this->current(), $tokenType);
        }
    }

    public function parse(){
        $ast = array();
        while($this->currentType() != 'EOFToken')
        {
            $ast[] = $this->parseStep();
        }
        return $ast;
    }

    protected function parseStep(){
        return null;
    }
}


class TmplParser extends Parser
{
    public function __construct($tokenstream){
        parent::__construct($tokenstream);
    }

    protected function parseStep(){
        if($this->currentType() == 'TextToken'){
            return $this->parseText();
        }
        else if($this->currentType() == 'IfToken'){
            return $this->parseIf();
        }
        else if($this->currentType() == 'ForToken'){
            return $this->parseFor();
        }
        else if($this->currentType() == 'OutputToken'){
            return $this->parseOutput();
        }
        else{
            throw new SyntaxException($this->current(),
                                        'Text, If, For or Output');
        }
    }

    protected function parseText(){
        $curr = $this->current();
        $this->accept('TextToken');
        return new TextNode($curr);
    }

    protected function parseIf(){
        $node = new IfNode($this->current());
        $this->accept('IfToken');

        while(($this->currentType() != 'ElifToken')
            &&($this->currentType() != 'ElseToken')
            &&($this->currentType() != 'EndToken'))
        {
            $node->addTrue($this->parseStep());
        }

        if($this->currentType() == 'ElifToken'){
            $node->addFalse($this->parseElif());
        }
        else if($this->currentType() == 'ElseToken'){
            $node->setFalse($this->parseElse());
        }
        $this->accept('EndToken');
        return $node;
    }

    protected function parseElif(){
        $node = new IfNode($this->current());
        $this->accept('ElifToken');
        while(($this->currentType() != 'ElifToken')
            &&($this->currentType() != 'ElseToken')
            &&($this->currentType() != 'EndToken'))
        {
            $node->addTrue($this->parseStep());
        }
        if($this->currentType() == 'ElifToken'){
            $node->addFalse($this->parseElif());
        }
        else if($this->currentType() == 'ElseToken'){
            $node->setFalse($this->parseElse());
        }
    }

    protected function parseElse(){
        $nodes = array();
        $this->accept('ElseToken');
        while($this->currentType() != 'EndToken'){
            $nodes[] = $this->parseStep();
        }
        return $nodes;
    }

    protected function parseFor(){
        $node = new ForNode($this->current());
        $this->accept('ForToken');
        while($this->currentType() != 'EndToken'){
            $node->addBody($this->parseStep());
        }
        $this->accept('EndToken');
        return $node;
    }

    protected function parseOutput(){
        $node = new OutputNode($this->current());
        $this->accept('OutputToken');
        return $node;
    }
}


class Lexer
{
    protected static $stringPtn = '/^("(\\\\"|[^"\\\\])*?")/';
    protected static $operatorPtn =
            '/^!=|^==|^>=|^<=|^<|^>|^and|^or|^not|^in|^\+|^\-|^\*|^\/|^%/';
    protected static $identPtn = '/^[_a-zA-Z][\._a-zA-Z0-9]*/';
    protected static $numberPtn = '/^[0-9]*\.?[0-9]+/';

    public static function lex($text){
        $tokens = array();
        $input = $text;
        while($input){
            $input = trim($input);
            $stringMatches = array();
            $operatorMatches = array();
            $identMatches = array();
            $numberMatches = array();

            preg_match(self::$stringPtn, $input, $stringMatches);
            preg_match(self::$operatorPtn, $input, $operatorMatches);
            preg_match(self::$identPtn, $input, $identMatches);
            preg_match(self::$numberPtn, $input, $numberMatches);

            // lex strings
            if(count($stringMatches) > 0){
                $tokens[] = new Token($stringMatches[0], 'StringToken');
                $input = substr($input, strlen($stringMatches[0]));
            }
            // lex identifiers
            else if(count($identMatches) > 0){
                // sort out atoms and keyword operators
                if(in_array($identMatches[0], array('true', 'false', 'null'))){
                    $tokens[] = new Token($identMatches[0], 'AtomToken');
                }
                else if(in_array($identMatches[0],
                                 array('and', 'or', 'in'))){
                    $tokens[] = new Token($identMatches[0],
                                                'OperatorToken');
                }
                else if($identMatches[0] == 'not'){
                    $tokens[] = new Token($identMatches[0],
                                                'UnaryOperatorToken');
                }
                else {
                    $tokens[] = new Token($identMatches[0],
                                                'IdentifierToken');
                }
                $input = substr($input, strlen($identMatches[0]));
            }
            // lex operators
            else if(count($operatorMatches) > 0){
                $tokens[] = new Token($operatorMatches[0],
                                            'OperatorToken');
                $input = substr($input, strlen($operatorMatches[0]));
            }
            // lex numbers
            else if(count($numberMatches) > 0){
                $tokens[] = new Token($numberMatches[0], 'NumberToken');
                $input = substr($input, strlen($numberMatches[0]));
            }
            else if(substr($input, 0, 1) == '('){
                $tokens[] = new Token('(', 'OpenToken');
            }
            else if(substr($input, 0, 1) == ')'){
                $tokens[] = new Token(')', 'CloseToken');
            }
            else{
                throw new SyntaxException(null, null,
                          'invalid expression token at \''.$input.'\'');
            }
        }
        return $tokens;
    }
}

class ExpressionParser extends Parser
{
    public function __construct($text){
        $tokenstream = Lexer::lex($text);
        parent::__construct($tokenstream);
    }

    public function parse(){
        $exp = $this->parseStep();
        $this->accept('EOFToken');
        return $exp;
    }

    protected function parseStep(){
        if($this->currentType() == 'OpenToken'){
            $exp = $this->parseParenthesis();
        }
        else if(($this->currentType() == 'UnaryOperatorToken')
            ||(($this->currentType() == 'OperatorToken') &&
               ($this->current()->getText() == '-'))){
            $exp = $this->parseUnaryOperator();
        }
        else if($this->currentType() == 'AtomToken'){
            $exp = $this->parseAtom();
        }
        else if($this->currentType() == 'StringToken'){
            $exp = $this->parseString();
        }
        else if($this->currentType() == 'NumberToken'){
            $exp = $this->parseNumber();
        }
        else if($this->currentType() == 'IdentifierToken'){
            $exp = $this->parseIdentifier();
        }
        else{
            throw new SyntaxException($this->current(),
                'Opening parenthesis, Unary operator, Identifier or Literal');
        }

        // binary operators
        if($this->current()->getType() == 'OperatorToken'){
            $operator = $this->current()->getText();
            $this->accept('OperatorToken');
            $exp = new BinaryOperatorNode($exp, $operator, $this->parseStep());
        }

        return $exp;
    }

    protected function parseParenthesis(){
        $this->accept('OpenToken');
        $exp = $this->parseStep();
        $this->accept('CloseToken');
        return $exp;
    }

    protected function parseUnaryOperator(){
        $value = $this->current()->getText();
        if($this->currentType() == 'UnaryOperatorToken'){
            $this->accept('UnaryOperatorToken');
        }
        else{
            $this->accept('OperatorToken');
        }
        $exp = $this->parseStep();
        return new UnaryOperatorNode($value, $exp);
    }

    protected function parseAtom(){
        $atom = $this->current()->getText();
        $this->accept('AtomToken');
        $value = null;
        switch($atom){
            case 'true':
                $value = true;
                break;
            case 'false':
                $value = false;
                break;
            case 'null':
                $value = null;
                break;
            default:
                throw new SyntaxException($this->current, '',
                                        "Invalid atomar value '".$atom."'");
        }
        return new ValueNode($value);
    }

    protected function parseString(){
        $value = $this->current()->getText();
        $this->accept('StringToken');
        $value = substr($value, 1, strlen($value)-2);
        $value = str_replace('\"', '"', $value);
        $value = str_replace('\n', "\n", $value);
        $value = str_replace('\r', "\r", $value);
        $value = str_replace('\t', "\t", $value);
        $value = str_replace('\v', "\v", $value);
        $value = str_replace('\f', "\f", $value);
        $value = str_replace("\\\\", "\\", $value);
        return new ValueNode($value);
    }

    protected function parseNumber(){
        $value = $this->current()->getText();
        $this->accept('NumberToken');
        if(is_numeric($value)){
            if(strpos($value, '.') !== false){
                $value = floatval($value);
            }
            else{
                $value = intval($value);
            }
        }
        else{
            throw new SyntaxException(null, null,
                    "invalid number literal: '".$value."'");
        }
        return new ValueNode($value);
    }

    protected function parseIdentifier(){
        $value = $this->current()->getText();
        $this->accept('IdentifierToken');
        return new VariableNode(null, $value);
    }
}

// -----------------------------------------------------------------------------
// SyntaxException class
// -----------------------------------------------------------------------------

class SyntaxException extends Exception
{
    protected $token;

    public function __construct($token, $expectedType, $msg=null) {
        $this->token = $token;
        if($msg == null){
            $message = "expected token '".substr($token->getText(), 0, 10)
                    ."...' to be of type '".$expectedType."'";
        }
        else {
            $message = $msg;
        }
        parent::__construct($message);
    }

    public function getToken(){
        return $this->token;
    }

    public function __toString() {
        if($this->token){
            return 'SyntaxException('.$this->token->getType()
                .', "'.$this->message.'")';
        }
        return 'SyntaxException("'.$this->message.'")';
    }
}

// -----------------------------------------------------------------------------
// ContextException class
// -----------------------------------------------------------------------------

class ContextException extends Exception
{
    public function __construct($msg) {
        parent::__construct($msg);
    }

    public function __toString() {
        return 'ContextException("'.$this->message.'")';
    }
}

// -----------------------------------------------------------------------------
// AST node classes
// -----------------------------------------------------------------------------

class ASTNode
{
    public function evaluate($params){
        return '';
    }
}

class TextNode
{
    protected $text = '';
    public function __construct($token){
        $this->text = $token->getText();
    }

    public function evaluate($params){
        return $this->text;
    }
}

class OutputNode
{
    protected $exp;
    public function __construct($token, $text=null){
        if(!$text){
            $text = trim($token->getText(), '{{');
            $text = trim($text, '}}');
        }
        $text = trim($text);
        $p = new ExpressionParser($text);
        $this->exp = $p->parse();
    }
    public function evaluate($params){
        return $this->exp->evaluate($params);
    }
}

class VariableNode
{
    protected $chain = array();
    public function __construct($token, $text=null){
        if(!$text){
            $text = trim($token->getText(), '{{');
            $text = trim($text, '}}');
        }
        $text = trim($text);
        $this->chain = explode('.', $text);
    }

    public function evaluate($params){
        if(array_key_exists($this->chain[0], $params)){
            $val = $params[$this->chain[0]];
            $chainstr = $this->chain[0];
            if(count($this->chain) > 1){
                for($i = 1; $i < count($this->chain); $i++){
                    $attr = $this->chain[$i];
                    $meth = 'get'.ucfirst($attr);

                    // array --> get index
                    if(gettype($val) == 'array'){
                        if(array_key_exists($attr, $val)){
                            $val = $val[$attr];
                        }
                        else{
                            throw new ContextException('The index \''.$attr.
                                        '\' of the variable \''.
                                        $chainstr.'\' does not exist!');
                        }
                    }
                    // object --> getAttribute or getter method
                    else if(gettype($val) == 'object'){
                        if(array_key_exists($attr, get_object_vars($val))){
                            $val = $val->$attr;
                        }
                        else if(method_exists($val, $attr)){
                            $val = $val->$attr();
                        }
                        else if(method_exists($val, $meth)){
                            $val = $val->$meth();
                        }
                        else{
                            throw new ContextException('The property \''.$attr.
                                                '\' of the variable \''.
                                                $chainstr.'\' does not exist!');
                        }
                    }
                    else{
                            throw new ContextException('The variable \''.
                                            $chainstr.
                                            '\' does not have any properties!');
                    }
                    $chainstr .= '.'.$attr;
                }
            }
            return $val;
        }
        else{
            throw new ContextException('The variable \''.$this->chain[0].
                                       '\' is not defined within this context');
        }
    }
}

class IfNode
{
    protected $condition;
    protected $truePart = array();
    protected $falsePart = array();

    public function __construct($token){
        $text = trim($token->getText(), '{%');
        $text = trim($text, '%}');
        $text = trim($text);
        $text = substr($text, 2); // cut off 'if'
        $text = trim($text);
        $p = new ExpressionParser($text);
        $this->condition = $p->parse();
    }

    public function setTrue($nodeArray){
        $this->truePart = $nodeArray;
    }

    public function setFalse($nodeArray){
        $this->falsePart = $nodeArray;
    }

    public function addTrue($node){
        $this->truePart[] = $node;
    }

    public function addFalse($node){
        $this->falsePart[] = $node;
    }

    public function evaluate($params){
        $output = '';
        if($this->condition->evaluate($params)){
            foreach($this->truePart as $slice){
                $output .= $slice->evaluate($params);
            }
        }
        else {
            foreach($this->falsePart as $slice){
                $output .= $slice->evaluate($params);
            }
        }
        return $output;
    }
}


class ForNode
{
    protected $iterator;
    protected $collection;
    protected $body = array();

    public function __construct($token){
        $text = trim($token->getText(), '{%');
        $text = trim($text, '%}');
        $text = trim($text);
        $text = substr($text, 3); // cut off 'for'
        $text = trim($text);

        $names = explode('in', $text);
        if(count($names) != 2){
            throw new SyntaxException($token, 'ForToken',
                      'A for header has to be of the form'.
                      ' {% for variable in collection %}, '.
                      $token->getText().' given');
        }
        $this->iterator = trim($names[0]);
        $this->collection = new VariableNode($token, $names[1]);
    }

    public function setBody($nodeArray){
        $this->body = $nodeArray;
    }

    public function addBody($node){
        $this->body[] = $node;
    }

    public function evaluate($params){
        $output = '';
        $collection = $this->collection->evaluate($params);

        foreach($collection as $i){
            foreach($this->body as $slice){
                // set iterator variable
                $params[$this->iterator] = $i;
                // evaluate body
                $output .= $slice->evaluate($params);
            }
        }
        return $output;
    }
}

class UnaryOperatorNode
{
    protected $value;
    protected $exp;

    public function __construct($value, $exp){
        $this->value = $value;
        $this->exp = $exp;
    }

    public function evaluate($params){
        if($this->value == 'not'){
            return ! ($this->exp->evaluate($params));
        }
        else if($this->value == '-'){
            return -($this->exp->evaluate($params));
        }
    }
}

class BinaryOperatorNode
{
    protected $operator;
    protected $left;
    protected $right;

    public function __construct($left, $operator, $right){
        $this->left = $left;
        $this->right = $right;
        $this->operator = $operator;
    }

    public function evaluate($params){
        switch($this->operator){
            case '+':
                return $this->left->evaluate($params)
                     + $this->right->evaluate($params);
                break;
            case '-':
                return $this->left->evaluate($params)
                     - $this->right->evaluate($params);
                break;
            case '*':
                return $this->left->evaluate($params)
                     * $this->right->evaluate($params);
                break;
            case '/':
                return $this->left->evaluate($params)
                     / $this->right->evaluate($params);
                break;
            case '%':
                return $this->left->evaluate($params)
                     % $this->right->evaluate($params);
                break;
            case '!=':
                return $this->left->evaluate($params)
                    != $this->right->evaluate($params);
                break;
            case '==':
                return $this->left->evaluate($params)
                    == $this->right->evaluate($params);
                break;
            case '>=':
                return $this->left->evaluate($params)
                    >= $this->right->evaluate($params);
                break;
            case '<=':
                break;
                return $this->left->evaluate($params)
                    <= $this->right->evaluate($params);
            case '<':
                return $this->left->evaluate($params)
                     < $this->right->evaluate($params);
                break;
            case '>':
                return $this->left->evaluate($params)
                     > $this->right->evaluate($params);
                break;
            case 'and':
                return $this->left->evaluate($params)
                    && $this->right->evaluate($params);
                break;
            case 'or':
                return $this->left->evaluate($params)
                    || $this->right->evaluate($params);
                break;
            case 'in':
                $right = $this->right->evaluate($params);
                switch(gettype($right)){
                    case 'array':
                        return in_array($this->left->evaluate($params),
                                        $right);
                        break;
                    case 'string':
                        return strpos($right,
                                      $this->left->evaluate($params)) !== false;
                        break;
                    default:
                        throw new ContextException("unsupported type '".
                                    gettype($right)."' for operator 'in'");
                }
                break;
        }
    }
}

class ValueNode
{
    protected $value;
    public function __construct($value){
        $this->value = $value;
    }

    public function evaluate($params){
        return $this->value;
    }
}
// -----------------------------------------------------------------------------
// Token classes
// -----------------------------------------------------------------------------

class Token
{
    protected $text;
    protected $type;

    public function __construct($text, $type){
        $this->text = $text;
        $this->type = $type;
    }

    public function getText(){
        return $this->text;
    }

    public function getType(){
        return $this->type;
    }
}

// usage:
// -----------------------------------------------------------------------------
// $a = new Tmpl('test.tmpl');
// echo $a->render(array(
//         'x'         => 42,
//         'lang'      => 'es',
//         'verbose'   => false,
//         'y'         => array('z' => 7),
//         'objects'   => array('Banane', 'Birne', 'Zitrone')
// ));
?>