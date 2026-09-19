<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductBuyerFieldService
{
    public const TYPES = ['text', 'textarea', 'number', 'url', 'phone'];

    /**
     * @return list<array{key: string, label: string, placeholder: string, type: string, required: bool}>
     */
    public static function normalize(mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        $out = [];
        $used = [];
        $sort = 1;

        foreach ($fields as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? $row['name'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = Str::slug((string) ($row['key'] ?? $row['name'] ?? $label), '_');
            if ($key === '') {
                $key = 'field_'.$sort;
            }
            while (isset($used[$key])) {
                $key .= '_'.$sort;
            }
            $used[$key] = true;

            $type = strtolower(trim((string) ($row['type'] ?? 'text')));
            if (! in_array($type, self::TYPES, true)) {
                $type = 'text';
            }

            $out[] = [
                'key' => $key,
                'label' => $label,
                'placeholder' => trim((string) ($row['placeholder'] ?? '')),
                'type' => $type,
                'required' => array_key_exists('required', $row) ? (bool) $row['required'] : true,
            ];
            $sort++;

            if (count($out) >= 20) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array{key: string, label: string, value: string}>
     */
    public static function validateValues(array $fields, mixed $values): array
    {
        $input = [];
        if (is_array($values)) {
            $isList = array_is_list($values);
            foreach ($values as $key => $value) {
                if ($isList && is_array($value)) {
                    $name = (string) ($value['key'] ?? $value['name'] ?? '');
                    $input[$name] = trim((string) ($value['value'] ?? ''));
                    continue;
                }
                $input[(string) $key] = is_array($value)
                    ? trim((string) ($value['value'] ?? ''))
                    : trim((string) $value);
            }
        }

        $out = [];
        $errors = [];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $key);
            if ($key === '') {
                continue;
            }

            $value = trim((string) ($input[$key] ?? ''));
            if ($value === '' && ! empty($field['required'])) {
                $errors[$key] = $label.' is required.';
                continue;
            }

            $out[] = [
                'key' => $key,
                'label' => $label,
                'value' => $value,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }
}
