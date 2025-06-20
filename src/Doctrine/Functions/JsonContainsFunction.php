<?php

namespace App\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * JSON_CONTAINS(target, candidate) : bool
 *
 * Usage: JSON_CONTAINS(column, :value)
 */
class JsonContainsFunction extends FunctionNode
{
    private Node $target;
    private Node $candidate;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER); // JSON_CONTAINS
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        
        $this->target = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->candidate = $parser->ArithmeticPrimary();
        
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            'JSON_CONTAINS(%s, %s)',
            $this->target->dispatch($sqlWalker),
            $this->candidate->dispatch($sqlWalker)
        );
    }
}