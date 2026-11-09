<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Laravel\Rules;

use Mfonte\Base62x\Base62x;
use Illuminate\Contracts\Validation\Rule;

/**
 * Validates that the attribute is a well-formed Base62x string.
 *
 *     $request->validate(['token' => ['required', new Base62xRule()]]);
 *     $request->validate(['token' => 'required|base62x']); // registered by the service provider
 */
class Base62xRule implements Rule
{
    /**
     * @param string $attribute
     * @param mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        return Base62x::isValid($value);
    }

    public function message(): string
    {
        return 'The :attribute must be a valid Base62x string.';
    }
}
