<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Huffman;

use Mfonte\Base62x\Exception\CompressionException;
use Mfonte\Base62x\Compression\Huffman\Binary\BitStreamReader;
use Mfonte\Base62x\Compression\Huffman\Binary\BitStreamWriter;

/**
 * Implementation of a Huffman Coding.
 *
 * @see https://en.wikipedia.org/wiki/Huffman_coding
 *
 * @internal This class is not part of the public API: use Base62x::compress('huffman')
 */
class HuffmanCoding
{
    public const SYMBOL_EOF = 'EOF';

    /**
     * Creates a code tree whose weights and symbols come from the sample indicated.
     * NOTE: if a byte isn't in this sample, it can't be encoded with the generated tree.
     *
     * @throws CompressionException
     */
    public static function createCodeTree(string $sample): HuffmanNode
    {
        $weights = [];
        $counts = count_chars($sample, 1);
        foreach (\is_array($counts) ? $counts : [] as $byte => $count) {
            $weights[\chr($byte)] = $count;
        }
        $weights[self::SYMBOL_EOF] = 1; // add the EOF marker to the encoding

        $queue = new HuffmanNodeQueue();
        arsort($weights);
        foreach ($weights as $symbol => $weight) {
            // numeric-string array keys are cast to int by PHP: cast them back
            $queue->addNode(new HuffmanNode((string) $symbol, $weight));
        }

        while ($nodes = $queue->popTwoNodes()) {
            $queue->addNode(HuffmanNode::join($nodes[0], $nodes[1]));
        }

        return $queue->getOnlyNode();
    }

    /**
     * Encodes the given data using the Huffman tree. The serialized tree is prepended to the output.
     *
     * @throws CompressionException
     */
    public static function encode(string $data, HuffmanNode $codeTree): string
    {
        $codeHash = [];
        $codeTree->getCodeHash($codeHash);
        $stream = new BitStreamWriter();
        $length = \strlen($data);
        for ($i = 0; $i < $length; ++$i) {
            $symbol = $data[$i];
            if (!isset($codeHash[$symbol])) {
                throw new CompressionException('huffman', 'Cannot encode byte 0x'.bin2hex($symbol).': it was not found in the encoding tree.');
            }
            $stream->writeString($codeHash[$symbol]);
        }
        $stream->writeString($codeHash[self::SYMBOL_EOF]);

        return $codeTree.$stream->getData();
    }

    /**
     * Decodes data produced by self::encode().
     *
     * @throws CompressionException
     */
    public static function decode(string $data): string
    {
        $rootNode = HuffmanNode::loadFromString($data);
        if ($rootNode->isLeaf()) {
            // a valid tree always has at least two leaves (a symbol and EOF): a single leaf would loop forever
            throw new CompressionException('huffman', 'Encoding is out of sync.');
        }
        $currentNode = $rootNode;
        $reader = new BitStreamReader($data);
        $decoded = '';
        while (true) {
            if ($currentNode->isLeaf()) {
                $nextSymbol = $currentNode->getSymbol();
                if ($nextSymbol === self::SYMBOL_EOF) {
                    return $decoded;
                }
                $decoded .= $nextSymbol;
                $currentNode = $rootNode;
                continue;
            }

            $bit = $reader->readBit();
            if ($bit === null) {
                throw new CompressionException('huffman', 'Reached the end of the encoded data, but did not find the EOF symbol.');
            }
            $nextNode = $bit === 1 ? $currentNode->getRightChild() : $currentNode->getLeftChild();
            if ($nextNode === null) {
                throw new CompressionException('huffman', 'Encoding is out of sync.');
            }
            $currentNode = $nextNode;
        }
    }
}
