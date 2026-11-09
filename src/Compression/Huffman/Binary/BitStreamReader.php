<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Huffman\Binary;

/**
 * Turns string data into a stream of bits.
 *
 * @internal
 */
class BitStreamReader
{
    /**
     * @var BitArray
     */
    private $dataArray;

    /**
     * @var int
     */
    private $cursor = 0;

    /**
     * To initialize, provide string data containing the bits.
     */
    public function __construct(string $data)
    {
        $this->dataArray = BitArray::load($data);
    }

    /**
     * Reads one bit at a time from the buffer, returning null for EOF.
     */
    public function readBit(): ?int
    {
        if ($this->isEOF()) {
            return null;
        }

        return $this->dataArray[$this->cursor++];
    }

    /**
     * Whether we've reached the end of our data.
     */
    public function isEOF(): bool
    {
        return !$this->dataArray->offsetExists($this->cursor);
    }
}
