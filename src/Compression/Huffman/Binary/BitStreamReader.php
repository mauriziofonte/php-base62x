<?php

namespace Mfonte\Base62x\Compression\Huffman\Binary;

/**
 *	turns string data into a stream of bits.
 */
class BitStreamReader
{
    private $dataArray = null;
    private $cursor = 0;

    /**
     *	To initialize, provide string data containing the bits.
     */
    public function __construct($data)
    {
        $this->dataArray = BitArray::load($data);
    }

    /**
     *	read one bit at a time from the buffer, returning null for EOF.
     */
    public function readBit()
    {
        if ($this->isEOF()) {
            return null;
        } else {
            $bit = $this->dataArray[$this->cursor];
            ++$this->cursor;

            return $bit;
        }
    }

    /**
     *	whether we've reached the end of our data.
     */
    public function isEOF()
    {
        return !$this->dataArray->offsetExists($this->cursor);
    }
}
