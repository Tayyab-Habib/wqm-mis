<?php

namespace App\Traits;

trait HasMobileCodes
{
    public array $mobileCodes = [
        300, 301, 302, 303, 304, 305, 306, 307, 308, 309, // Jazz
        310, 311, 312, 313, 314, 315, 316, 317, 318, // Zong
        320, 321, 322, 323, 324, 325, 326, // Warid
        330, 331, 332, 333, 334, 335, 336, 337, // Ufone
        340, 341, 342, 343, 344, 345, 346, 347, 348, 349, // Telenor
        355, // SCom
        364, // Instaphone
    ];

    /**
     * Get mobile codes concatenated with '|' delimiter
     *
     * @return string
     */
    public function getMobileCodes(): string
    {
        return implode('|', array_map(function ($code) {
            return str($code);
        }, $this->mobileCodes));
    }
}
