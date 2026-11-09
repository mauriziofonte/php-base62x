<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Huffman\Binary;

/**
 * A simple, fixed-size bit array, backed internally by a (binary) string.
 *
 * @internal
 *
 * @implements \ArrayAccess<int, int>
 */
class BitArray implements \ArrayAccess
{
    /**
     * @var string
     */
    private $data = '';

    /**
     * @var int
     */
    private $size = 0;

    /**
     * Creates a new bit array of the given size, with all bits set to 0.
     */
    public function __construct(int $size)
    {
        $this->size = $size;
        $this->data = str_repeat("\0", (int) ceil($size / 8));
    }

    /**
     * Creates a new BitArray from the bytes in the supplied string.
     */
    public static function load(string $data): self
    {
        $array = new self(\strlen($data) * 8);
        $array->data = $data;

        return $array;
    }

    /**
     * Retrieves the internal string representation of the binary data.
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Whether the given offset exists: ArrayAccess.
     */
    public function offsetExists($offset): bool
    {
        return \is_int($offset) && $offset >= 0 && $offset < $this->size;
    }

    /**
     * Gets the value (0 or 1) at the given offset: ArrayAccess.
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        [$index, $bit] = $this->getPosition((int) $offset);

        return (\ord($this->data[$index]) & (1 << $bit)) !== 0 ? 1 : 0;
    }

    /**
     * Sets the value at the given offset: ArrayAccess.
     */
    public function offsetSet($offset, $value): void
    {
        [$index, $bit] = $this->getPosition((int) $offset);
        $byte = \ord($this->data[$index]);
        $byte = $value ? $byte | (1 << $bit) : $byte & ~(1 << $bit);
        $this->data[$index] = \chr($byte);
    }

    /**
     * Unsets the given offset: ArrayAccess.
     * NOTE: This deviates a little from the true ArrayAccess meaning,
     * b/c we have a value (0 or 1) at every bit no matter what. Unsetting sets to 0.
     */
    public function offsetUnset($offset): void
    {
        $this->offsetSet($offset, 0);
    }

    /**
     * Gets the byte index and the bit position that contain the given offset.
     *
     * @return array{0: int, 1: int}
     */
    private function getPosition(int $offset): array
    {
        if ($offset < 0 || $offset >= $this->size) {
            throw new \OutOfRangeException('Bit offset '.$offset.' is out of range');
        }

        return [intdiv($offset, 8), $offset % 8];
    }
}
