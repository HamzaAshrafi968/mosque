<?php

namespace App\Services;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;

/**
 * Defines and stores configurable profile fields for students/teachers
 * without schema changes (spec §3).
 *
 * Storage normalisation:
 * - text/textarea/select/number/date   → plain string
 * - boolean                            → '1' | '0'
 * - multiselect                        → JSON array
 */
class CustomFieldService
{
    public function definitions(string $entityType, bool $activeOnly = true): Collection
    {
        return CustomField::query()
            ->where('entity_type', $entityType)
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, CustomField> keyed by field_key */
    public function definitionsByKey(string $entityType, bool $activeOnly = true): array
    {
        return $this->definitions($entityType, $activeOnly)
            ->keyBy('field_key')
            ->all();
    }

    /**
     * Build per-field validation rules according to the field type.
     *
     * @return array<string, array<int, string|In>>
     */
    public function rules(string $entityType): array
    {
        $rules = [];

        foreach ($this->definitions($entityType) as $field) {
            $fieldRules = match ($field->field_type) {
                CustomFieldType::Text => ['string', 'max:255'],
                CustomFieldType::Textarea => ['string', 'max:5000'],
                CustomFieldType::Number => ['numeric'],
                CustomFieldType::Date => ['date'],
                CustomFieldType::Boolean => ['boolean'],
                CustomFieldType::Select => ['string', Rule::in($field->options ?? [])],
                CustomFieldType::Multiselect => ['array', Rule::in($field->options ?? [])],
            };

            if ($field->required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $rules['custom_fields.'.$field->field_key] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Validate a keyed custom-fields payload against the field definitions.
     * Required fields are enforced even when the payload omits them.
     *
     * @throws ValidationException
     */
    public function validate(string $entityType, array $customFields): void
    {
        $validator = Validator::make(['custom_fields' => $customFields], $this->rules($entityType));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /** Normalise one raw value to its storage representation. */
    public function normalise(CustomField $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field->field_type) {
            CustomFieldType::Boolean => $value ? '1' : '0',
            CustomFieldType::Multiselect => json_encode(array_values((array) $value)),
            default => (string) $value,
        };
    }

    /** Convert a stored value back to its natural PHP representation. */
    public function deserialise(CustomField $field, ?string $stored): mixed
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        return match ($field->field_type) {
            CustomFieldType::Boolean => $stored === '1',
            CustomFieldType::Number => is_numeric($stored) ? (float) $stored : $stored,
            CustomFieldType::Multiselect => json_decode($stored, true) ?? [],
            default => $stored,
        };
    }

    /**
     * Persist a keyed payload of values for an entity, upserting by field.
     * Keys referencing inactive/unknown fields are ignored.
     */
    public function save(string $entityType, string $entityId, array $customFields, ?string $tenantId = null): void
    {
        if ($customFields === []) {
            return;
        }

        $definitions = $this->definitionsByKey($entityType);

        foreach ($customFields as $key => $value) {
            $field = $definitions[$key] ?? null;

            if (! $field) {
                continue;
            }

            $stored = $this->normalise($field, $value);

            CustomFieldValue::updateOrCreate(
                ['tenant_id' => $tenantId, 'custom_field_id' => $field->id, 'entity_id' => $entityId],
                ['value' => $stored]
            );
        }
    }

    /** @return array<string, mixed> map of field_key => natural value for one entity */
    public function valuesFor(string $entityType, string $entityId): array
    {
        $fields = $this->definitions($entityType, activeOnly: false)->keyBy('id');
        $values = [];

        CustomFieldValue::query()
            ->where('entity_id', $entityId)
            ->get()
            ->each(function (CustomFieldValue $row) use (&$values, $fields) {
                $field = $fields[$row->custom_field_id] ?? null;

                if ($field) {
                    $values[$field->field_key] = $this->deserialise($field, $row->value);
                }
            });

        return $values;
    }

    /** Pairs of [definition, stored value] for display purposes. */
    public function displayedValues(string $entityType, string $entityId): Collection
    {
        return $this->definitions($entityType)
            ->map(fn (CustomField $field) => [
                'field' => $field,
                'raw' => CustomFieldValue::query()
                    ->where('custom_field_id', $field->id)
                    ->where('entity_id', $entityId)
                    ->value('value'),
            ])
            ->filter(fn ($pair) => $pair['raw'] !== null && $pair['raw'] !== '')
            ->map(fn ($pair) => [
                'field' => $pair['field'],
                'value' => $this->deserialise($pair['field'], $pair['raw']),
            ])
            ->values();
    }

    /** Human-readable display string for a natural value. */
    public function toDisplay(CustomField $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field->field_type === CustomFieldType::Boolean) {
            return $value ? 'نعم' : 'لا';
        }

        if ($field->field_type === CustomFieldType::Multiselect) {
            return implode('، ', array_map('strval', (array) $value));
        }

        if ($field->field_type === CustomFieldType::Date) {
            return Carbon::parse($value)->format('Y-m-d');
        }

        return (string) $value;
    }

    /** Generate a unique key from a base when the admin leaves the key blank. */
    public static function uniqueKey(string $entityType, ?string $hint = null, ?string $tenantId = null): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $hint)));

        if ($base === '' || $base === '_') {
            $base = 'field_'.now()->format('YmdHis');
        }

        $candidate = $base;
        $suffix = 1;

        while (CustomField::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('field_key', $candidate)
            ->exists()) {
            $candidate = $base.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
