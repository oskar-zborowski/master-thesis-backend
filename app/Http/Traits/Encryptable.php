<?php

namespace App\Http\Traits;

use App\Http\Libraries\Encrypter;

/**
 * Trait przeprowadzający proces szyfrowania i deszyfrowania pól w bazie danych
 */
trait Encryptable
{
    public function setAttribute($key, $value) {

        if ($this->encryptable($key)) {
            $value = Encrypter::encrypt($value, $this->encryptable[$key]);
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key) {

        $value = parent::getAttribute($key);

        if ($this->encryptable($key)) {
            $value = Encrypter::decrypt($value);
        }

        return $value;
    }

    public function getArrayableAttributes() {

        $attributes = parent::getArrayableAttributes();

        foreach ($attributes as $key => $attribute) {
            if ($this->encryptable($key)) {
                $attributes[$key] = Encrypter::decrypt($attribute);
            }
        }

        return $attributes;
    }

    private function encryptable(string $key) {
        return in_array($key, array_keys($this->encryptable));
    }
}
