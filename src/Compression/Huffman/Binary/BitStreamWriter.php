<?php

namespace Mfonte\Base62x\Compression\Huffman\Binary;

/**
 *	a mechanism for writing a stream if bits into a string, which can then be easily transmistted or stored.
 */
class BitStreamWriter
{
    private $data = '';
    private $workingByte = null;
    private $cursor = 0;

    /**
     *	initialize be creating our working byte / buffer.
     */
    public function __construct()
    {
        $this->workingByte = new BitArray(8);
    }

    /**
     * 	convert a string of zeroes and ones into a binary representation.
     */
    public function writeString($bitStr)
    {
        for ($i = 0; $i < \mb_strlen($bitStr); ++$i) {
            $this->writeBit((bool) $bitStr[$i]);
        }
    }

    /**
     * 	write a bit. 1 if $bit is true.
     */
    public function writeBit($bit)
    {
        $this->workingByte[$this->cursor] = $bit;
        ++$this->cursor;
        if ($this->cursor > 7) {
            $this->data .= $this->workingByte->getData();
            $this->workingByte = new BitArray(8);
            $this->cursor = 0;
        }
    }

    /**
     * 	when the data is accessed, make sure to include any data
     * 	byte in $this->workingByte.
     */
    public function getData()
    {
        $data = $this->data;
        if ($this->cursor > 0) {
            $data .= $this->workingByte->getData();
        }

        return $data;
    }
}
