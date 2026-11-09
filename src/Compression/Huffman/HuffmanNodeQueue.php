<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Huffman;

use Mfonte\Base62x\Exception\CompressionException;

/**
 * A queue of Huffman Nodes that need to be incorporated into a tree.
 *
 * @internal
 */
class HuffmanNodeQueue
{
    /**
     * @var list<HuffmanNode>
     */
    private $nodes = [];

    /**
     * Puts the new node into the queue, keeping it in order by weight.
     */
    public function addNode(HuffmanNode $node): void
    {
        $index = 0;
        $count = \count($this->nodes);
        while ($index < $count && $this->nodes[$index]->getWeight() < $node->getWeight()) {
            ++$index;
        }
        array_splice($this->nodes, $index, 0, [$node]);
    }

    /**
     * Gets the two nodes with the lowest weights from the front of the queue.
     *
     * @return array{0: HuffmanNode, 1: HuffmanNode}|false
     */
    public function popTwoNodes()
    {
        if (\count($this->nodes) < 2) {
            return false;
        }

        [$first, $second] = $this->nodes;
        $this->nodes = \array_slice($this->nodes, 2);

        return [$first, $second];
    }

    /**
     * Once there's only one node left, extracts it.
     *
     * @throws CompressionException
     */
    public function getOnlyNode(): HuffmanNode
    {
        if (\count($this->nodes) !== 1) {
            throw new CompressionException('huffman', 'Wrong number of nodes. Only call getOnlyNode when exactly one node exists.');
        }

        return $this->nodes[0];
    }
}
