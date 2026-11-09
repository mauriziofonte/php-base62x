<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Huffman;

use Mfonte\Base62x\Exception\CompressionException;

/**
 * Node in a huffman tree implementing a huffman coding.
 *
 * @internal
 */
class HuffmanNode
{
    /**
     * @var int|null
     */
    private $weight;

    /**
     * A single byte, HuffmanCoding::SYMBOL_EOF, or null for internal nodes.
     *
     * @var string|null
     */
    private $symbol;

    /**
     * @var HuffmanNode|null
     */
    private $leftChild;

    /**
     * @var HuffmanNode|null
     */
    private $rightChild;

    /**
     * Creates a new node (a leaf node, if the symbol is not null).
     */
    public function __construct(?string $symbol, ?int $weight = null)
    {
        $this->symbol = $symbol;
        $this->weight = $weight;
    }

    /**
     * Creates a new node whose children are the supplied nodes.
     */
    public static function join(self $left, self $right): self
    {
        $newParent = new self(null, (int) $left->getWeight() + (int) $right->getWeight());
        $newParent->leftChild = $left;
        $newParent->rightChild = $right;

        return $newParent;
    }

    /**
     * Performs a depth-first search to construct the symbol => bit-string code table.
     *
     * @param array<string, string> $hashTable
     */
    public function getCodeHash(array &$hashTable, string $prefix = ''): void
    {
        if ($this->isLeaf()) {
            // a tree made of a single leaf still needs a non-empty code
            $hashTable[(string) $this->symbol] = $prefix !== '' ? $prefix : '0';

            return;
        }

        if ($this->leftChild !== null) {
            $this->leftChild->getCodeHash($hashTable, "{$prefix}0");
        }
        if ($this->rightChild !== null) {
            $this->rightChild->getCodeHash($hashTable, "{$prefix}1");
        }
    }

    public function isLeaf(): bool
    {
        return $this->symbol !== null;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function getRightChild(): ?self
    {
        return $this->rightChild;
    }

    public function getLeftChild(): ?self
    {
        return $this->leftChild;
    }

    /**
     * @deprecated nodes do not keep a reference to their parent: this method always returns null
     */
    public function getParent(): ?self
    {
        return null;
    }

    /**
     * Opposite of __toString(): reads a tree from the beginning of the string,
     * and removes the consumed bytes from it.
     *
     * @throws CompressionException
     */
    public static function loadFromString(string &$str): self
    {
        return self::readEncodedNode($str, 0);
    }

    /**
     * Creates a string for compactly encoding this node (and its children).
     */
    public function __toString(): string
    {
        if ($this->isLeaf()) {
            // "~" stands in, because HuffmanCoding::SYMBOL_EOF is more than one byte
            return $this->symbol === HuffmanCoding::SYMBOL_EOF ? '2~' : '1'.$this->symbol;
        }

        return '0'.$this->leftChild.$this->rightChild;
    }

    /**
     * Reads a node encoded using __toString().
     *
     * @throws CompressionException
     */
    private static function readEncodedNode(string &$str, int $depth): self
    {
        // a tree of at most 257 leaves cannot be deeper than 257 levels: guard against malicious input
        if ($str === '' || $depth > 257) {
            throw new CompressionException('huffman', 'Encoding is out of sync.');
        }

        $switch = $str[0];
        $str = (string) substr($str, 1);
        switch ($switch) {
            case '0':
                $parent = new self(null);
                $parent->leftChild = self::readEncodedNode($str, $depth + 1);
                $parent->rightChild = self::readEncodedNode($str, $depth + 1);

                return $parent;
            case '1':
            case '2':
                if ($str === '') {
                    throw new CompressionException('huffman', 'Encoding is out of sync.');
                }
                $symbol = $switch === '1' ? $str[0] : HuffmanCoding::SYMBOL_EOF;
                $str = (string) substr($str, 1);

                return new self($symbol);
            default:
                throw new CompressionException('huffman', 'Encoding is out of sync.');
        }
    }
}
