<?php

namespace App\Http\Resources\Api\V1;

use App\Services\ProgramService;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'description' => $this->description,
            'color' => $this->color,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'periods' => $this->whenLoaded('periods', fn () => $this->periods->map(fn ($period) => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_at' => $period->starts_at ? substr($period->starts_at, 0, 5) : null,
                'ends_at' => $period->ends_at ? substr($period->ends_at, 0, 5) : null,
                'sort_order' => (int) $period->sort_order,
                'is_active' => (bool) $period->is_active,
            ])),
            'attributes' => $this->whenLoaded('attributes', fn () => $this->attributes->map(fn ($attribute) => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'field_key' => $attribute->field_key,
                'field_type' => $attribute->field_type->value,
                'required' => (bool) $attribute->required,
                'options' => app(ProgramService::class)->resolvedOptions($attribute),
                'options_source' => $attribute->options_source->value,
                'options_config' => $attribute->options_config,
                'value' => $attribute->value,
                'sort_order' => (int) $attribute->sort_order,
                'is_active' => (bool) $attribute->is_active,
            ])),
            'schedules_count' => $this->whenCounted('schedules'),
            'study_sessions' => $this->whenLoaded('studySessions', fn () => $this->studySessions->map(fn ($session) => [
                'id' => $session->id,
                'name' => $session->name,
            ])),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
