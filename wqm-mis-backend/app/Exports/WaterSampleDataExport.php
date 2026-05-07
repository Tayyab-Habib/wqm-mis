<?php

namespace App\Exports;

use App\Models\Test;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WaterSampleDataExport implements FromCollection, WithHeadings
{

    protected $waterSamples;
    private array $tests;

    public function __construct($waterSamples)
    {
        $this->waterSamples = $waterSamples;
        $this->tests = Test::query()->select('water_quality_parameter')->pluck('water_quality_parameter')->toArray();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection(): Collection
    {
        return $this->waterSamples->transform(function ($sample) {

            $invoice = $sample->waterSampleInvoice;
            $desiredTest = $sample->desired_test;
            $sampleDetail = [
                'test_type' => $sample->test_type->value,
                'slug' => $sample->slug,
                'water_scheme_name' => $sample->waterScheme?->name ?? '',
                'source_type' => $sample->source_type->value,
                'sampling_point' => $sample->sampling_point->value,
                'collected_by' => $sample->collected_by->value,
                'latitude' => $sample->latitude,
                'longitude' => $sample->longitude,
                'status' => $sample->status->value,
                'temperature_in_celsius' => $sample->temperature_in_celsius . ' C',
                'sampled_at' => $sample->sampled_at,
                'analyzed_at' => $sample->analyzed_at,
                'collected_in' => $sample->collected_in->value,
                'collected_in_other' => $sample->collected_in_other ?? '',
                'complaint' => $sample->complaint->value,
                'complaint_by_other' => $sample->complaint_by_other ?? '',
                'desired_test' => implode(', ', $desiredTest),
                'laboratory_name' => $sample->laboratory?->name ?? '',
                'union_council_id' => $sample->unionCouncil->name ?? '',
                'tehsil_id' => $sample->tehsil?->name ?? '',
                'district_id' => $sample->district?->name ?? '',
                'division_id' => $sample->division?->name ?? '',
                'province_id' => $sample->province?->name ?? '',
                'collectable' => $sample->collectable?->name ?? '',
                'created_at' => $sample->created_at ?? '',
                'result' => $sample->result ?? '',
                'remarks' => $sample->remarks ?? '',
                'price' => $invoice ? $invoice->price : '',
                'created_by' => $sample->createdByUser?->name ?? '',
            ];

            $tests = array_fill_keys($this->tests, '');

            $waterSampleTests = collect($sample->waterSampleDetails)
                ->mapWithKeys(fn($waterSampleDetail) => [$waterSampleDetail->test->water_quality_parameter => $waterSampleDetail->analysis_result])->toArray();

            $mergedTests = array_merge($tests, $waterSampleTests);

            return array_merge($sampleDetail, $mergedTests);
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return array_merge([
            'Test Type',
            'Slug',
            'Water Scheme',
            'Source Type',
            'Sampling Point',
            'Collected By',
            'Latitude',
            'Longitude',
            'Status',
            'Temperature',
            'Sampled At',
            'Analyzed At',
            'Collected In',
            'Collected In Other',
            'Complaint',
            'Complaint By Other',
            'Desired Test',
            'Laboratory',
            'Union Council',
            'Tehsil',
            'District',
            'Division',
            'Province',
            'Collect By',
            'Created At',
            'Result',
            'Remarks',
            'Price',
            'Created By',
        ], $this->tests);
    }
}
