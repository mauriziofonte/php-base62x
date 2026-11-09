<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Huffman\Binary;

/**
 * A mechanism for writing a stream of bits into a string, which can then be easily transmitted or stored.
 *
 * @internal
 */
class BitStreamWriter
{
    /**
     * @var string
     */
    private $data = '';

    /**
     * @var BitArray
     */
    private $workingByte;

    /**
     * @var int
     */
    private $cursor = 0;

    /**
     * Initializes the writer, by creating our working byte / buffer.
     */
    public function __construct()
    {
        $this->workingByte = new BitArray(8);
    }

    /**
     * Converts a string of zeroes and ones into a binary representation.
     */
    public function writeString(string $bitStr): void
    {
        $length = \strlen($bitStr);
        for ($i = 0; $i < $length; ++$i) {
            $this->writeBit($bitStr[$i] === '1');
        }
    }

    /**
     * Writes a bit: 1 if $bit is true.
     */
    public function writeBit(bool $bit): void
    {
        $this->workingByte[$this->cursor] = $bit ? 1 : 0;
        ++$this->cursor;
        if ($this->cursor > 7) {
            $this->data .= $this->workingByte->getData();
            $this->workingByte = new BitArray(8);
            $this->cursor = 0;
        }
    }

    /**
     * When the data is accessed, make sure to include any data byte in $this->workingByte.
     */
    public function getData(): string
    {
        $data = $this->data;
        if ($this->cursor > 0) {
            $data .= $this->workingByte->getData();
        }

        return $data;
    }
}
