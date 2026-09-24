<?php

namespace App\Services;

use App\Enums\Ib39FeaDocumentType;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class Ib39FeaDraftSchema
{
    public const VERSION = 2;

    public const MAX_ROWS = 30;

    public function fields(Ib39FeaDocumentType $type): array
    {
        return match ($type) {
            Ib39FeaDocumentType::Tir => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'time', 'label' => 'Time', 'type' => 'time'],
                ['key' => 'fr_name', 'label' => 'NAME OF FR/FVE', 'type' => 'text'],
                ...$this->firearmFields(),
                ['key' => 'parts', 'label' => 'PARTS', 'type' => 'table', 'columns' => ['part' => 'PARTS', 'serviceable' => 'SERVICEABLE', 'unserviceable' => 'UNSERVICEABLE']],
                ['key' => 'condition', 'label' => 'Condition', 'type' => 'choice', 'choices' => ['GOOD', 'FAIR', 'SCRAP']],
                ['key' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea'],
            ],
            Ib39FeaDocumentType::Cvif => [
                ['key' => 'fr_name', 'label' => 'Name of FR or FVE', 'type' => 'text'],
                ['key' => 'rm_number', 'label' => 'RM No.', 'type' => 'text'],
                ...$this->firearmFields(),
                ['key' => 'date_of_inspection', 'label' => 'Date of inspection', 'type' => 'date'],
                ['key' => 'place_of_inspection', 'label' => 'Place of inspection', 'type' => 'text'],
                ['key' => 'condition', 'label' => 'Condition', 'type' => 'text'],
                ['key' => 'serviceability', 'label' => 'Serviceability', 'type' => 'text'],
                ['key' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea'],
                ['key' => 'amount_in_words', 'label' => 'Amount in words', 'type' => 'text'],
                ['key' => 'cost_valuation', 'label' => 'Cost valuation (Php)', 'type' => 'decimal'],
                ['key' => 'done_date', 'label' => 'DONE this (DD-MM-year)', 'type' => 'date'],
                ['key' => 'done_place', 'label' => 'Place', 'type' => 'text'],
            ],
            Ib39FeaDocumentType::Ptis => [
                ['key' => 'supply_classification_officer', 'label' => 'SUPPLY CLASSIFICATION OFFICER', 'type' => 'text'],
                ['key' => 'page_of_voucher_number', 'label' => 'PAGE ___ OF Voucher Number', 'type' => 'text'],
                ['key' => 'organization_unit', 'label' => 'Organization/ Unit', 'type' => 'text'],
                ['key' => 'turn_in_slip_number', 'label' => 'Turn-In Slip Number', 'type' => 'text'],
                ['key' => 'items', 'label' => 'Turn-in items', 'type' => 'table', 'columns' => ['item' => 'ITEM', 'stock' => 'STOCK', 'nomenclature' => 'NOMENCLATURE', 'unit' => 'UNIT', 'quantity' => 'QTY', 'remarks' => 'REMARKS', 'symbol_action' => 'SYMBOL ACTION']],
                ['key' => 'turned_in_by', 'label' => 'TURNED IN BY', 'type' => 'text', 'title' => '(Name, please specify if FR or FVE)'],
                ['key' => 'note', 'label' => 'NOTE', 'type' => 'textarea'],
                ['key' => 'organization_supply_officer_date', 'label' => 'DATE: (ORGANIZATION SUPPLY OFFICER)', 'type' => 'date'],
                ['key' => 'station_supply_classification_officer_date', 'label' => '(DATE :) For Station Supply or Classification Officer', 'type' => 'date'],
            ],
            Ib39FeaDocumentType::Justification => [
                ['key' => 'fr_name', 'label' => 'Name of FR/FVE', 'type' => 'text'],
                ...$this->firearmFields(),
                ['key' => 'declared_cost_valuation', 'label' => 'Declared Cost Valuation', 'type' => 'decimal'],
                ['key' => 'considerations', 'label' => '1. What are the considerations for the cost valuation of the FEA?', 'type' => 'textarea'],
                ['key' => 'classified_condition', 'label' => '1.1 What condition was the firearms classified under?', 'type' => 'textarea'],
                ['key' => 'condition_factors', 'label' => '1.2 What specific factors affect its condition?', 'type' => 'textarea'],
                ['key' => 'visible_damages', 'label' => '1.3 Are there any visible damages? If yes, which part are they?', 'type' => 'textarea'],
                ['key' => 'missing_parts', 'label' => '1.4 If there are parts missing, which parts are they?', 'type' => 'textarea'],
                ['key' => 'other_considerations', 'label' => '2. What other the considerations,criteria or basis aside from the DILG-DND JMC No.1,s.2021.are used for the cost valuation?', 'type' => 'textarea'],
                ['key' => 'market_analysis', 'label' => '2.1 If based on market analysis, what references where used?Also,when was the market analysis conducted,and by whom?', 'type' => 'textarea'],
                ['key' => 'other_source', 'label' => '2.2 If others,briefly state the source and justification.Also, how was the credibility of this sourced assessed?', 'type' => 'textarea'],
                ['key' => 'photo_notice_3', 'label' => '3. Photo documentation of the firearms surrendered', 'type' => 'notice'],
                ['key' => 'photo_notice_4', 'label' => '4. Photo of an example of the firearms surrendered that is good condition', 'type' => 'notice'],
                ['key' => 'prepared_by', 'label' => 'Prepared By — full name', 'type' => 'text'],
                ['key' => 'prepared_by_position', 'label' => 'Prepared By — rank/position', 'type' => 'text'],
                ['key' => 'reviewed_by', 'label' => 'Reviewed By — full name', 'type' => 'text'],
                ['key' => 'reviewed_by_position', 'label' => 'Reviewed By — rank/position', 'type' => 'text'],
            ],
            default => throw new InvalidArgumentException('This document does not have a draft editor.'),
        };
    }

    public function rules(Ib39FeaDocumentType $type): array
    {
        $fields = $this->fields($type);
        $rules = ['draft' => ['required', 'array:'.implode(',', $this->storedKeys($fields))]];
        foreach ($fields as $field) {
            if ($field['type'] === 'notice') {
                continue;
            }
            $path = 'draft.'.$field['key'];
            if ($field['type'] === 'table') {
                $rules[$path] = ['present', 'array', 'max:'.self::MAX_ROWS];
                $rules[$path.'.*'] = ['array:'.implode(',', array_keys($field['columns']))];
                foreach (array_keys($field['columns']) as $column) {
                    $rules[$path.'.*.'.$column] = $column === 'quantity'
                        ? ['present', 'nullable', 'integer', 'min:0', 'max:999999']
                        : ['present', 'nullable', 'string', 'max:500'];
                }
            } elseif ($field['type'] === 'choice') {
                $rules[$path] = ['present', 'nullable', Rule::in($field['choices'])];
            } elseif ($field['type'] === 'decimal') {
                $rules[$path] = ['present', 'nullable', 'decimal:0,2', 'min:0', 'max:999999999.99'];
            } elseif ($field['type'] === 'date') {
                $rules[$path] = ['present', 'nullable', 'date_format:Y-m-d'];
            } elseif ($field['type'] === 'time') {
                $rules[$path] = ['present', 'nullable', 'date_format:H:i'];
            } else {
                $rules[$path] = ['present', 'nullable', 'string', 'max:'.($field['type'] === 'textarea' ? 4000 : 500)];
            }
        }

        return $rules;
    }

    public function initial(Ib39FeaDocumentType $type, string $frName): array
    {
        $draft = [];
        foreach ($this->fields($type) as $field) {
            if ($field['type'] === 'notice') {
                continue;
            }
            $draft[$field['key']] = $field['type'] === 'table' ? [] : null;
        }
        if (array_key_exists('fr_name', $draft)) {
            $draft['fr_name'] = $frName;
        }

        return $draft;
    }

    public function changedFields(array $before, array $after): array
    {
        return array_values(array_keys(array_filter($after, fn ($value, $key) => ($before[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH)));
    }

    private function firearmFields(): array
    {
        return [
            ['key' => 'kind', 'label' => 'KIND', 'type' => 'text'],
            ['key' => 'caliber', 'label' => 'CALIBER', 'type' => 'text'],
            ['key' => 'make', 'label' => 'MAKE', 'type' => 'text'],
            ['key' => 'serial_number', 'label' => 'SERIAL NO.', 'type' => 'text'],
        ];
    }

    private function storedKeys(array $fields): array
    {
        return array_values(array_map(fn ($field) => $field['key'], array_filter($fields, fn ($field) => $field['type'] !== 'notice')));
    }
}
